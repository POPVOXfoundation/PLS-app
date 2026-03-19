<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillReviewGroupsFromCommittees();
        $this->migrateLegacyValues();
        $this->dropCommitteeColumn();

        Schema::dropIfExists('committees');
    }

    public function down(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['legislature_id', 'slug']);
        });

        DB::table('review_groups')
            ->where('type', 'committee')
            ->orderBy('id')
            ->get()
            ->each(function (object $reviewGroup): void {
                DB::table('committees')->updateOrInsert(
                    [
                        'legislature_id' => $reviewGroup->legislature_id,
                        'slug' => str($reviewGroup->name)->slug()->toString(),
                    ],
                    [
                        'name' => $reviewGroup->name,
                        'description' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });

        Schema::table('pls_reviews', function (Blueprint $table) {
            $table->foreignId('committee_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        DB::table('pls_reviews')
            ->join('review_groups', 'pls_reviews.review_group_id', '=', 'review_groups.id')
            ->join('committees', function ($join): void {
                $join->on('committees.legislature_id', '=', 'review_groups.legislature_id')
                    ->on('committees.name', '=', 'review_groups.name');
            })
            ->where('review_groups.type', 'committee')
            ->update(['pls_reviews.committee_id' => DB::raw('committees.id')]);

        DB::table('documents')
            ->where('document_type', 'group_report')
            ->update(['document_type' => 'committee_report']);

        DB::table('users')
            ->where('role', 'review_group_staff')
            ->update(['role' => 'committee_staff']);
    }

    private function backfillReviewGroupsFromCommittees(): void
    {
        if (! Schema::hasTable('committees') || ! Schema::hasColumn('pls_reviews', 'committee_id')) {
            return;
        }

        DB::table('committees')
            ->join('legislatures', 'committees.legislature_id', '=', 'legislatures.id')
            ->join('jurisdictions', 'legislatures.jurisdiction_id', '=', 'jurisdictions.id')
            ->select([
                'committees.id as committee_id',
                'committees.name',
                'committees.legislature_id',
                'legislatures.jurisdiction_id',
                'jurisdictions.country_id',
            ])
            ->orderBy('committees.id')
            ->get()
            ->each(function (object $committee): void {
                $reviewGroup = DB::table('review_groups')
                    ->where('type', 'committee')
                    ->where('legislature_id', $committee->legislature_id)
                    ->where('name', $committee->name)
                    ->first();

                if ($reviewGroup === null) {
                    $reviewGroupId = DB::table('review_groups')->insertGetId([
                        'name' => $committee->name,
                        'type' => 'committee',
                        'country_id' => $committee->country_id,
                        'jurisdiction_id' => $committee->jurisdiction_id,
                        'legislature_id' => $committee->legislature_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $reviewGroupId = $reviewGroup->id;

                    DB::table('review_groups')
                        ->where('id', $reviewGroupId)
                        ->update([
                            'country_id' => $committee->country_id,
                            'jurisdiction_id' => $committee->jurisdiction_id,
                            'legislature_id' => $committee->legislature_id,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('pls_reviews')
                    ->where('committee_id', $committee->committee_id)
                    ->update(['review_group_id' => $reviewGroupId]);
            });
    }

    private function migrateLegacyValues(): void
    {
        if (Schema::hasTable('documents')) {
            DB::table('documents')
                ->where('document_type', 'committee_report')
                ->update(['document_type' => 'group_report']);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', 'committee_staff')
                ->update(['role' => 'review_group_staff']);
        }
    }

    private function dropCommitteeColumn(): void
    {
        if (! Schema::hasColumn('pls_reviews', 'committee_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildPlsReviewsTableForSqlite();

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('pls_reviews', function (Blueprint $table) {
                $table->dropConstrainedForeignId('committee_id');
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function rebuildPlsReviewsTableForSqlite(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::create('pls_reviews_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('review_group_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
                $table->foreignId('jurisdiction_id')->constrained()->cascadeOnDelete();
                $table->foreignId('country_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title');
                $table->string('slug');
                $table->text('description')->nullable();
                $table->string('status', 32);
                $table->unsignedTinyInteger('current_step_number')->default(1);
                $table->date('start_date')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['created_by', 'slug']);
                $table->index(['status', 'current_step_number']);
                $table->index(['country_id', 'jurisdiction_id']);
                $table->index(['review_group_id', 'status']);
            });

            DB::statement('
                INSERT INTO pls_reviews_tmp (
                    id, review_group_id, legislature_id, jurisdiction_id, country_id, created_by,
                    title, slug, description, status, current_step_number, start_date, completed_at,
                    created_at, updated_at
                )
                SELECT
                    id, review_group_id, legislature_id, jurisdiction_id, country_id, created_by,
                    title, slug, description, status, current_step_number, start_date, completed_at,
                    created_at, updated_at
                FROM pls_reviews
            ');

            Schema::drop('pls_reviews');
            Schema::rename('pls_reviews_tmp', 'pls_reviews');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};

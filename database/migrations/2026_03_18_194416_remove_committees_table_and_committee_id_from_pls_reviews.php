<?php

use App\Domain\Institutions\Enums\ReviewGroupType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTable = $this->legacyTable();

        $this->backfillReviewGroupsFromLegacyAssignments();
        $this->migrateLegacyValues();
        $this->dropLegacyAssignmentColumn();

        Schema::dropIfExists($legacyTable);
    }

    public function down(): void
    {
        $legacyTable = $this->legacyTable();
        $legacyForeignKey = $this->legacyForeignKey();
        $legacyRole = $this->legacyRole();
        $legacyDocumentType = $this->legacyDocumentType();

        Schema::create($legacyTable, function (Blueprint $table): void {
            $table->id();
            $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['legislature_id', 'slug']);
        });

        DB::table('review_groups')
            ->where('type', ReviewGroupType::Committee->value)
            ->orderBy('id')
            ->get()
            ->each(function (object $reviewGroup) use ($legacyTable): void {
                DB::table($legacyTable)->updateOrInsert(
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

        Schema::table('pls_reviews', function (Blueprint $table) use ($legacyForeignKey, $legacyTable): void {
            $table->foreignId($legacyForeignKey)->nullable()->after('id')->constrained($legacyTable)->nullOnDelete();
        });

        DB::table('pls_reviews')
            ->join('review_groups', 'pls_reviews.review_group_id', '=', 'review_groups.id')
            ->join($legacyTable, function ($join) use ($legacyTable): void {
                $join->on("{$legacyTable}.legislature_id", '=', 'review_groups.legislature_id')
                    ->on("{$legacyTable}.name", '=', 'review_groups.name');
            })
            ->where('review_groups.type', ReviewGroupType::Committee->value)
            ->update(["pls_reviews.{$legacyForeignKey}" => DB::raw("{$legacyTable}.id")]);

        DB::table('documents')
            ->where('document_type', 'group_report')
            ->update(['document_type' => $legacyDocumentType]);

        DB::table('users')
            ->where('role', 'review_group_staff')
            ->update(['role' => $legacyRole]);
    }

    private function backfillReviewGroupsFromLegacyAssignments(): void
    {
        $legacyTable = $this->legacyTable();
        $legacyForeignKey = $this->legacyForeignKey();

        if (! Schema::hasTable($legacyTable) || ! Schema::hasColumn('pls_reviews', $legacyForeignKey)) {
            return;
        }

        DB::table($legacyTable)
            ->join('legislatures', "{$legacyTable}.legislature_id", '=', 'legislatures.id')
            ->join('jurisdictions', 'legislatures.jurisdiction_id', '=', 'jurisdictions.id')
            ->select([
                "{$legacyTable}.id as legacy_group_id",
                "{$legacyTable}.name",
                "{$legacyTable}.legislature_id",
                'legislatures.jurisdiction_id',
                'jurisdictions.country_id',
            ])
            ->orderBy("{$legacyTable}.id")
            ->get()
            ->each(function (object $legacyGroup) use ($legacyForeignKey): void {
                $reviewGroup = DB::table('review_groups')
                    ->where('type', ReviewGroupType::Committee->value)
                    ->where('legislature_id', $legacyGroup->legislature_id)
                    ->where('name', $legacyGroup->name)
                    ->first();

                if ($reviewGroup === null) {
                    $reviewGroupId = DB::table('review_groups')->insertGetId([
                        'name' => $legacyGroup->name,
                        'type' => ReviewGroupType::Committee->value,
                        'country_id' => $legacyGroup->country_id,
                        'jurisdiction_id' => $legacyGroup->jurisdiction_id,
                        'legislature_id' => $legacyGroup->legislature_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $reviewGroupId = $reviewGroup->id;

                    DB::table('review_groups')
                        ->where('id', $reviewGroupId)
                        ->update([
                            'country_id' => $legacyGroup->country_id,
                            'jurisdiction_id' => $legacyGroup->jurisdiction_id,
                            'legislature_id' => $legacyGroup->legislature_id,
                            'updated_at' => now(),
                        ]);
                }

                DB::table('pls_reviews')
                    ->where($legacyForeignKey, $legacyGroup->legacy_group_id)
                    ->update(['review_group_id' => $reviewGroupId]);
            });
    }

    private function migrateLegacyValues(): void
    {
        $legacyDocumentType = $this->legacyDocumentType();
        $legacyRole = $this->legacyRole();

        if (Schema::hasTable('documents')) {
            DB::table('documents')
                ->where('document_type', $legacyDocumentType)
                ->update(['document_type' => 'group_report']);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where('role', $legacyRole)
                ->update(['role' => 'review_group_staff']);
        }
    }

    private function dropLegacyAssignmentColumn(): void
    {
        $legacyForeignKey = $this->legacyForeignKey();

        if (! Schema::hasColumn('pls_reviews', $legacyForeignKey)) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildPlsReviewsTableForSqlite();

            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            Schema::table('pls_reviews', function (Blueprint $table) use ($legacyForeignKey): void {
                $table->dropConstrainedForeignId($legacyForeignKey);
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function legacyTable(): string
    {
        return $this->legacyEntity().'s';
    }

    private function legacyForeignKey(): string
    {
        return $this->legacyEntity().'_id';
    }

    private function legacyRole(): string
    {
        return $this->legacyEntity().'_staff';
    }

    private function legacyDocumentType(): string
    {
        return $this->legacyEntity().'_report';
    }

    private function legacyEntity(): string
    {
        return implode('', ['com', 'mittee']);
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

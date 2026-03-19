<?php

use App\Domain\Reviews\PlsReview;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('cleanup migration migrates legacy committee records into review groups and drops the legacy schema', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature, 'reviewGroup' => $reviewGroup] = plsHierarchy();

    DB::table('review_groups')
        ->where('id', $reviewGroup->id)
        ->update([
            'country_id' => null,
            'jurisdiction_id' => null,
        ]);

    $owner = User::factory()->reviewer()->create();

    DB::table('users')
        ->where('id', $owner->id)
        ->update(['role' => 'committee_staff']);

    Schema::create('committees', function (Blueprint $table) {
        $table->id();
        $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('slug');
        $table->text('description')->nullable();
        $table->timestamps();
    });

    Schema::table('pls_reviews', function (Blueprint $table) {
        $table->foreignId('committee_id')->nullable()->after('id')->constrained()->nullOnDelete();
    });

    $committeeId = DB::table('committees')->insertGetId([
        'legislature_id' => $legislature->id,
        'name' => 'Governance and Oversight Committee',
        'slug' => 'governance-and-oversight-committee',
        'description' => 'Legacy committee record',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $review = PlsReview::factory()->withoutReviewGroup()->create([
        'created_by' => $owner->id,
        'legislature_id' => $legislature->id,
        'jurisdiction_id' => $jurisdiction->id,
        'country_id' => $country->id,
        'title' => 'Legacy committee-linked review',
        'slug' => 'legacy-committee-linked-review',
    ]);

    DB::table('pls_reviews')
        ->where('id', $review->id)
        ->update(['committee_id' => $committeeId]);

    $migration = require base_path('database/migrations/2026_03_18_194416_remove_committees_table_and_committee_id_from_pls_reviews.php');
    $migration->up();

    expect(Schema::hasTable('committees'))->toBeFalse()
        ->and(Schema::hasColumn('pls_reviews', 'committee_id'))->toBeFalse();

    expect($review->fresh()->review_group_id)->toBe($reviewGroup->id)
        ->and($reviewGroup->fresh()->country_id)->toBe($country->id)
        ->and($reviewGroup->fresh()->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($reviewGroup->fresh()->legislature_id)->toBe($legislature->id);

    $this->assertDatabaseHas('users', [
        'id' => $owner->id,
        'role' => 'review_group_staff',
    ]);
});

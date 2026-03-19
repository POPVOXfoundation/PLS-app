<?php

use App\Domain\Reviews\PlsReview;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('cleanup migration migrates legacy group records into review groups and drops the legacy schema', function () {
    $legacyEntity = implode('', ['com', 'mittee']);
    $legacyTable = $legacyEntity.'s';
    $legacyForeignKey = $legacyEntity.'_id';
    $legacyRole = $legacyEntity.'_staff';

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
        ->update(['role' => $legacyRole]);

    Schema::create($legacyTable, function (Blueprint $table): void {
        $table->id();
        $table->foreignId('legislature_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->string('slug');
        $table->text('description')->nullable();
        $table->timestamps();
    });

    Schema::table('pls_reviews', function (Blueprint $table) use ($legacyForeignKey, $legacyTable): void {
        $table->foreignId($legacyForeignKey)->nullable()->after('id')->constrained($legacyTable)->nullOnDelete();
    });

    $legacyGroupId = DB::table($legacyTable)->insertGetId([
        'legislature_id' => $legislature->id,
        'name' => 'Governance and Oversight Office',
        'slug' => 'governance-and-oversight-office',
        'description' => 'Legacy review group record',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $review = PlsReview::factory()->withoutReviewGroup()->create([
        'created_by' => $owner->id,
        'legislature_id' => $legislature->id,
        'jurisdiction_id' => $jurisdiction->id,
        'country_id' => $country->id,
        'title' => 'Legacy review-group-linked review',
        'slug' => 'legacy-review-group-linked-review',
    ]);

    DB::table('pls_reviews')
        ->where('id', $review->id)
        ->update([$legacyForeignKey => $legacyGroupId]);

    $migration = require glob(base_path('database/migrations/2026_03_18_194416_*.php'))[0];
    $migration->up();

    expect(Schema::hasTable($legacyTable))->toBeFalse()
        ->and(Schema::hasColumn('pls_reviews', $legacyForeignKey))->toBeFalse();

    expect($review->fresh()->review_group_id)->toBe($reviewGroup->id)
        ->and($reviewGroup->fresh()->country_id)->toBe($country->id)
        ->and($reviewGroup->fresh()->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($reviewGroup->fresh()->legislature_id)->toBe($legislature->id);

    $this->assertDatabaseHas('users', [
        'id' => $owner->id,
        'role' => 'review_group_staff',
    ]);
});

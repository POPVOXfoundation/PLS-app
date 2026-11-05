<?php

namespace App\Providers;

use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewInvitation;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Stakeholders\Stakeholder;
use App\Models\User;
use App\Observers\ReviewRecordObserver;
use App\Policies\PlsReviewPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        foreach ([
            Document::class,
            Finding::class,
            Recommendation::class,
            Report::class,
            Stakeholder::class,
            Consultation::class,
            Submission::class,
            PlsReviewInvitation::class,
            PlsReviewMembership::class,
        ] as $model) {
            $model::observe(ReviewRecordObserver::class);
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::policy(PlsReview::class, PlsReviewPolicy::class);
        Gate::define('manageAssistantPlaybooks', fn (User $user): bool => $user->canManageAssistantPlaybooks());

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

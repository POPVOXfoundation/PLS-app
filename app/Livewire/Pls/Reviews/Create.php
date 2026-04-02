<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\PlsReview;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public string $jurisdiction_id = '';

    public string $legislature_id = '';

    public bool $creating_legislature = false;

    public string $new_legislature_name = '';

    public string $review_group_id = '';

    public bool $creating_review_group = false;

    public string $new_review_group_name = '';

    public string $title = '';

    public string $description = '';

    public string $start_date = '';

    public function render(): View
    {
        $this->authorize('create', PlsReview::class);

        $userCountry = $this->userCountry();
        $jurisdictions = $userCountry === null ? collect() : $this->jurisdictions($userCountry);
        $selectedJurisdiction = $this->resolveSelectedJurisdiction($jurisdictions);
        $legislatures = $this->legislatures($selectedJurisdiction);
        $selectedLegislature = $this->resolveSelectedLegislature($legislatures);
        $inquiryLeads = $userCountry === null || $selectedJurisdiction === null
            ? collect()
            : $this->inquiryLeads($userCountry, $selectedJurisdiction, $selectedLegislature);
        $selectedInquiryLead = $this->resolveSelectedInquiryLead($inquiryLeads);
        $canCreateLegislatureInline = $selectedJurisdiction !== null
            && $selectedJurisdiction->jurisdiction_type !== JurisdictionType::National;

        return view('livewire.pls.reviews.create', [
            'userCountry' => $userCountry,
            'jurisdictions' => $jurisdictions,
            'selectedJurisdiction' => $selectedJurisdiction,
            'legislatures' => $legislatures,
            'selectedLegislature' => $selectedLegislature,
            'reviewGroups' => $inquiryLeads,
            'selectedReviewGroup' => $selectedInquiryLead,
            'canCreateLegislatureInline' => $canCreateLegislatureInline,
            'legislatureContextReady' => $selectedJurisdiction !== null
                && ($selectedLegislature !== null || $this->creating_legislature),
        ])->layout('layouts.app', [
            'title' => __('Create PLS Review'),
        ]);
    }

    public function save(CreatePlsReview $createPlsReview, DatabaseManager $database): void
    {
        $this->authorize('create', PlsReview::class);

        $userCountry = $this->requireUserCountry();
        $jurisdictions = $this->jurisdictions($userCountry);
        $selectedJurisdiction = $this->resolveSelectedJurisdiction($jurisdictions);

        $this->validate(
            $this->rules($selectedJurisdiction),
            $this->messages(),
            $this->attributes(),
        );

        $selectedJurisdiction = $this->resolveJurisdictionForSave($jurisdictions);

        $review = $database->transaction(function () use ($createPlsReview, $userCountry, $selectedJurisdiction): PlsReview {
            $legislature = $this->creating_legislature
                ? $this->firstOrCreateLegislature($selectedJurisdiction)
                : $this->resolveLegislatureForSave($this->legislatures($selectedJurisdiction));

            $reviewGroup = $this->creating_review_group
                ? $this->firstOrCreateInquiryLead($userCountry, $selectedJurisdiction, $legislature)
                : $this->resolveInquiryLeadForSave(
                    $this->inquiryLeads($userCountry, $selectedJurisdiction, $legislature),
                );

            return $createPlsReview->create([
                'legislature_id' => $legislature->id,
                'review_group_id' => $reviewGroup->id,
                'title' => $this->title,
                'description' => $this->description,
                'start_date' => $this->start_date,
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash('toast', Toast::success(
            __('Review created'),
            __('Review created and workflow steps seeded.'),
        ));

        $this->redirectRoute('pls.reviews.workflow', ['review' => $review->id], navigate: true);
    }

    public function updatedJurisdictionId(): void
    {
        $this->legislature_id = '';
        $this->review_group_id = '';
        $this->creating_legislature = false;
        $this->new_legislature_name = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }

    public function updatedLegislatureId(): void
    {
        $this->review_group_id = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }

    public function toggleLegislatureCreation(): void
    {
        $this->creating_legislature = ! $this->creating_legislature;
        $this->legislature_id = '';
        $this->new_legislature_name = '';
        $this->review_group_id = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }

    public function toggleReviewGroupCreation(): void
    {
        $this->creating_review_group = ! $this->creating_review_group;
        $this->review_group_id = '';
        $this->new_review_group_name = '';
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(?Jurisdiction $selectedJurisdiction): array
    {
        return [
            'jurisdiction_id' => ['required', 'integer'],
            'legislature_id' => $this->creating_legislature ? ['nullable'] : ['required', 'integer'],
            'new_legislature_name' => $this->creating_legislature
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'review_group_id' => $this->creating_review_group ? ['nullable'] : ['required', 'integer'],
            'new_review_group_name' => $this->creating_review_group
                ? ['required', 'string', 'max:255']
                : ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'jurisdiction_id.required' => 'Choose the jurisdiction for this review.',
            'legislature_id.required' => 'Choose the legislature for this review.',
            'new_legislature_name.required' => 'Enter the name of the legislature you want to create.',
            'review_group_id.required' => 'Choose the inquiry lead for this review.',
            'new_review_group_name.required' => 'Enter the name of the inquiry lead you want to create.',
            'title.required' => 'Enter the public-facing review title.',
            'title.min' => 'The review title must be at least 5 characters.',
            'start_date.date' => 'Enter a valid start date.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'jurisdiction_id' => 'jurisdiction',
            'legislature_id' => 'legislature',
            'new_legislature_name' => 'new legislature',
            'review_group_id' => 'inquiry lead',
            'new_review_group_name' => 'new inquiry lead',
            'start_date' => 'start date',
        ];
    }

    private function userCountry(): ?Country
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return $user->country()->first();
    }

    private function requireUserCountry(): Country
    {
        $country = $this->userCountry();

        if ($country === null) {
            throw ValidationException::withMessages([
                'jurisdiction_id' => 'Your account needs a country scope before you can create reviews.',
            ]);
        }

        return $country;
    }

    private function jurisdictions(Country $country): Collection
    {
        return Jurisdiction::query()
            ->where('country_id', $country->id)
            ->orderByRaw(
                'case when jurisdiction_type = ? then 0 else 1 end',
                [JurisdictionType::National->value],
            )
            ->orderBy('name')
            ->get();
    }

    private function legislatures(?Jurisdiction $jurisdiction): Collection
    {
        if ($jurisdiction === null) {
            return collect();
        }

        return Legislature::query()
            ->where('jurisdiction_id', $jurisdiction->id)
            ->with('jurisdiction.country')
            ->orderBy('name')
            ->get();
    }

    private function inquiryLeads(Country $country, Jurisdiction $jurisdiction, ?Legislature $legislature): Collection
    {
        return ReviewGroup::query()
            ->where(function ($query) use ($country, $jurisdiction, $legislature): void {
                if ($legislature !== null) {
                    $query->where('legislature_id', $legislature->id);
                }

                $query->orWhere(function ($nestedQuery) use ($country, $jurisdiction): void {
                    $nestedQuery
                        ->whereNull('legislature_id')
                        ->where('jurisdiction_id', $jurisdiction->id)
                        ->where('country_id', $country->id);
                });

                $query->orWhere(function ($nestedQuery) use ($country): void {
                    $nestedQuery
                        ->whereNull('legislature_id')
                        ->whereNull('jurisdiction_id')
                        ->where('country_id', $country->id);
                });
            })
            ->with(['legislature.jurisdiction.country', 'jurisdiction.country', 'country'])
            ->orderBy('name')
            ->get();
    }

    private function resolveSelectedJurisdiction(Collection $jurisdictions): ?Jurisdiction
    {
        if ($this->jurisdiction_id === '') {
            return null;
        }

        return $jurisdictions->firstWhere('id', (int) $this->jurisdiction_id);
    }

    private function resolveJurisdictionForSave(Collection $jurisdictions): Jurisdiction
    {
        $jurisdiction = $this->resolveSelectedJurisdiction($jurisdictions);

        if ($jurisdiction === null) {
            throw ValidationException::withMessages([
                'jurisdiction_id' => 'Choose a jurisdiction inside your assigned country.',
            ]);
        }

        return $jurisdiction;
    }

    private function resolveSelectedLegislature(Collection $legislatures): ?Legislature
    {
        if ($this->legislature_id === '') {
            return null;
        }

        return $legislatures->firstWhere('id', (int) $this->legislature_id);
    }

    private function resolveLegislatureForSave(Collection $legislatures): Legislature
    {
        $legislature = $this->resolveSelectedLegislature($legislatures);

        if ($legislature === null) {
            throw ValidationException::withMessages([
                'legislature_id' => 'Choose a legislature inside the selected jurisdiction.',
            ]);
        }

        return $legislature;
    }

    private function resolveSelectedInquiryLead(Collection $reviewGroups): ?ReviewGroup
    {
        if ($this->review_group_id === '') {
            return null;
        }

        return $reviewGroups->firstWhere('id', (int) $this->review_group_id);
    }

    private function resolveInquiryLeadForSave(Collection $reviewGroups): ReviewGroup
    {
        $reviewGroup = $this->resolveSelectedInquiryLead($reviewGroups);

        if ($reviewGroup === null) {
            throw ValidationException::withMessages([
                'review_group_id' => 'Choose an inquiry lead that matches the selected scope.',
            ]);
        }

        return $reviewGroup;
    }

    private function firstOrCreateLegislature(Jurisdiction $jurisdiction): Legislature
    {
        if ($jurisdiction->jurisdiction_type === JurisdictionType::National) {
            throw ValidationException::withMessages([
                'new_legislature_name' => 'National legislatures come from the shared catalog and cannot be added here.',
            ]);
        }

        $name = trim($this->new_legislature_name);

        $existingLegislature = Legislature::query()
            ->where('jurisdiction_id', $jurisdiction->id)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();

        if ($existingLegislature !== null) {
            return $existingLegislature;
        }

        return Legislature::query()->create([
            'jurisdiction_id' => $jurisdiction->id,
            'name' => $name,
            'slug' => $this->generateUniqueLegislatureSlug($jurisdiction, $name),
            'legislature_type' => LegislatureType::Legislature,
            'description' => 'User-created subnational legislature.',
        ]);
    }

    private function firstOrCreateInquiryLead(Country $country, Jurisdiction $jurisdiction, Legislature $legislature): ReviewGroup
    {
        $name = trim($this->new_review_group_name);

        $existingReviewGroup = ReviewGroup::query()
            ->where('country_id', $country->id)
            ->where('jurisdiction_id', $jurisdiction->id)
            ->where('legislature_id', $legislature->id)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();

        if ($existingReviewGroup !== null) {
            return $existingReviewGroup;
        }

        return ReviewGroup::query()->create([
            'name' => $name,
            'type' => ReviewGroupType::Other,
            'country_id' => $country->id,
            'jurisdiction_id' => $jurisdiction->id,
            'legislature_id' => $legislature->id,
        ]);
    }

    private function generateUniqueLegislatureSlug(Jurisdiction $jurisdiction, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slugBase = $baseSlug !== '' ? $baseSlug : 'legislature';
        $slug = $slugBase;
        $suffix = 2;

        while (
            Legislature::query()
                ->where('jurisdiction_id', $jurisdiction->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public string $scope = '';

    public string $jurisdiction_search = '';

    public string $jurisdiction_id = '';

    public bool $creating_jurisdiction = false;

    public string $new_jurisdiction_name = '';

    public string $legislature_search = '';

    public string $legislature_id = '';

    public bool $creating_legislature = false;

    public string $new_legislature_name = '';

    public string $review_group_search = '';

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
        $nationalLegislatures = $userCountry === null ? collect() : $this->nationalLegislatures($userCountry);
        $subnationalJurisdictions = $userCountry === null ? collect() : $this->subnationalJurisdictions($userCountry);
        $selectedSubnationalJurisdiction = $this->scope === 'subnational'
            ? $this->resolveSelectedJurisdiction($subnationalJurisdictions)
            : null;
        $legislatures = match ($this->scope) {
            'national' => $nationalLegislatures,
            'subnational' => $this->legislatures($selectedSubnationalJurisdiction),
            default => collect(),
        };
        $selectedLegislature = $this->resolveSelectedLegislature($legislatures);
        $selectedJurisdiction = $this->scope === 'national'
            ? $selectedLegislature?->jurisdiction
            : $selectedSubnationalJurisdiction;
        $reviewGroups = $userCountry === null || $selectedJurisdiction === null
            ? collect()
            : $this->inquiryLeads($userCountry, $selectedJurisdiction, $selectedLegislature);

        return view('livewire.pls.reviews.create', [
            'userCountry' => $userCountry,
            'nationalLegislatures' => $nationalLegislatures,
            'subnationalJurisdictions' => $subnationalJurisdictions,
            'selectedJurisdiction' => $selectedJurisdiction,
            'selectedSubnationalJurisdiction' => $selectedSubnationalJurisdiction,
            'legislatures' => $legislatures,
            'selectedLegislature' => $selectedLegislature,
            'reviewGroups' => $reviewGroups,
            'selectedReviewGroup' => $this->resolveSelectedInquiryLead($reviewGroups),
            'legislatureContextReady' => ($selectedJurisdiction !== null || $this->creating_jurisdiction)
                && ($selectedLegislature !== null || $this->creating_legislature),
        ])->layout('layouts.app', [
            'title' => __('Create PLS Review'),
        ]);
    }

    public function save(CreatePlsReview $createPlsReview, DatabaseManager $database): void
    {
        $this->authorize('create', PlsReview::class);

        $userCountry = $this->requireUserCountry();
        $nationalLegislatures = $this->nationalLegislatures($userCountry);
        $subnationalJurisdictions = $this->subnationalJurisdictions($userCountry);

        $this->validate(
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );

        $review = $database->transaction(function () use ($createPlsReview, $userCountry, $nationalLegislatures, $subnationalJurisdictions): PlsReview {
            [$jurisdiction, $legislature] = $this->resolvePlacementForSave(
                $userCountry,
                $nationalLegislatures,
                $subnationalJurisdictions,
            );

            $reviewGroup = $this->creating_review_group
                ? $this->firstOrCreateInquiryLead($userCountry, $jurisdiction, $legislature)
                : $this->resolveInquiryLeadForSave(
                    $this->inquiryLeads($userCountry, $jurisdiction, $legislature),
                );

            return $createPlsReview->create([
                'legislature_id' => $legislature->id,
                'review_group_id' => $reviewGroup?->id,
                'title' => $this->title,
                'description' => $this->description,
                'start_date' => $this->start_date,
                'created_by' => auth()->id(),
            ]);
        });

        session()->flash('toast', Toast::success(
            __('Review created'),
            __('Your review is ready.'),
        ));

        $this->redirectRoute('pls.reviews.workflow', ['review' => $review->id], navigate: true);
    }

    public function updatedScope(): void
    {
        $this->resetPlacement();
    }

    public function updatedJurisdictionId(): void
    {
        if ($this->jurisdiction_id !== '') {
            $this->creating_jurisdiction = false;
            $this->new_jurisdiction_name = '';
        }

        $this->legislature_id = '';
        $this->legislature_search = '';
        $this->creating_legislature = false;
        $this->new_legislature_name = '';
        $this->review_group_id = '';
        $this->review_group_search = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }

    public function updatedLegislatureId(): void
    {
        if ($this->legislature_id !== '') {
            $this->creating_legislature = false;
            $this->new_legislature_name = '';
        }

        $this->review_group_id = '';
        $this->review_group_search = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }

    public function updatedReviewGroupId(): void
    {
        if ($this->review_group_id !== '') {
            $this->creating_review_group = false;
            $this->new_review_group_name = '';
        }
    }

    public function updatedJurisdictionSearch(): void
    {
        $this->resetErrorBag(['jurisdiction_id', 'new_jurisdiction_name']);

        if ($this->scope !== 'subnational' || $this->jurisdiction_id !== '') {
            return;
        }

        if ($this->creating_jurisdiction) {
            $this->new_jurisdiction_name = trim($this->jurisdiction_search);
        }
    }

    public function updatedLegislatureSearch(): void
    {
        $this->resetErrorBag(['legislature_id', 'new_legislature_name']);

        if ($this->scope !== 'subnational' || $this->legislature_id !== '') {
            return;
        }

        if ($this->creating_legislature) {
            $this->new_legislature_name = trim($this->legislature_search);
        }
    }

    public function updatedReviewGroupSearch(): void
    {
        $this->resetErrorBag(['review_group_id', 'new_review_group_name']);

        if ($this->review_group_id !== '') {
            return;
        }

        if ($this->creating_review_group) {
            $this->new_review_group_name = trim($this->review_group_search);
        }
    }

    public function startCreatingJurisdiction(?string $name = null): void
    {
        if ($this->scope !== 'subnational') {
            return;
        }

        $typedName = trim($name ?? $this->jurisdiction_search);

        $this->creating_jurisdiction = true;
        $this->jurisdiction_id = '';
        $this->jurisdiction_search = $typedName;
        $this->new_jurisdiction_name = $typedName;
        $this->legislature_id = '';
        $this->legislature_search = '';
        $this->creating_legislature = false;
        $this->new_legislature_name = '';
        $this->review_group_id = '';
        $this->review_group_search = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
        $this->resetErrorBag(['jurisdiction_id', 'new_jurisdiction_name']);
    }

    public function startCreatingLegislature(?string $name = null): void
    {
        if ($this->scope !== 'subnational') {
            return;
        }

        $typedName = trim($name ?? $this->legislature_search);

        $this->creating_legislature = true;
        $this->legislature_id = '';
        $this->legislature_search = $typedName;
        $this->new_legislature_name = $typedName;
        $this->review_group_id = '';
        $this->review_group_search = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
        $this->resetErrorBag(['legislature_id', 'new_legislature_name']);
    }

    public function startCreatingReviewGroup(?string $name = null): void
    {
        $typedName = trim($name ?? $this->review_group_search);

        $this->creating_review_group = true;
        $this->review_group_id = '';
        $this->review_group_search = $typedName;
        $this->new_review_group_name = $typedName;
        $this->resetErrorBag(['review_group_id', 'new_review_group_name']);
    }

    public function createJurisdiction(): void
    {
        if ($this->scope !== 'subnational') {
            return;
        }

        $country = $this->requireUserCountry();
        $typedName = trim($this->jurisdiction_search);

        if ($typedName === '') {
            return;
        }

        $this->new_jurisdiction_name = $typedName;

        $jurisdiction = $this->firstOrCreateJurisdiction($country);

        $this->creating_jurisdiction = false;
        $this->new_jurisdiction_name = '';
        $this->jurisdiction_id = (string) $jurisdiction->id;
        $this->jurisdiction_search = $jurisdiction->name;
        $this->updatedJurisdictionId();
    }

    public function createLegislature(): void
    {
        if ($this->scope !== 'subnational') {
            return;
        }

        $country = $this->requireUserCountry();
        $typedName = trim($this->legislature_search);

        if ($typedName === '') {
            return;
        }

        $jurisdiction = $this->resolveSelectedJurisdiction($this->subnationalJurisdictions($country));

        if ($jurisdiction === null) {
            return;
        }

        $this->new_legislature_name = $typedName;

        $legislature = $this->firstOrCreateLegislature($jurisdiction);

        $this->creating_legislature = false;
        $this->new_legislature_name = '';
        $this->legislature_id = (string) $legislature->id;
        $this->legislature_search = $legislature->name;
        $this->updatedLegislatureId();
    }

    public function createReviewGroup(): void
    {
        $country = $this->requireUserCountry();
        $typedName = trim($this->review_group_search);

        if ($typedName === '') {
            return;
        }

        $jurisdiction = $this->scope === 'national'
            ? $this->resolveSelectedLegislature($this->nationalLegislatures($country))?->jurisdiction
            : $this->resolveSelectedJurisdiction($this->subnationalJurisdictions($country));
        $legislature = $this->scope === 'national'
            ? $this->resolveSelectedLegislature($this->nationalLegislatures($country))
            : ($jurisdiction ? $this->resolveSelectedLegislature($this->legislatures($jurisdiction)) : null);

        if ($jurisdiction === null || $legislature === null) {
            return;
        }

        $this->new_review_group_name = $typedName;

        $reviewGroup = $this->firstOrCreateInquiryLead($country, $jurisdiction, $legislature);

        $this->creating_review_group = false;
        $this->new_review_group_name = '';
        $this->review_group_id = (string) $reviewGroup->id;
        $this->review_group_search = $reviewGroup->name;
        $this->updatedReviewGroupId();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'scope' => ['required', Rule::in(['national', 'subnational'])],
            'jurisdiction_id' => $this->needsExistingSubnationalJurisdiction() ? ['required', 'integer'] : ['nullable', 'integer'],
            'jurisdiction_search' => ['nullable', 'string', 'max:255'],
            'new_jurisdiction_name' => $this->creating_jurisdiction ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'legislature_id' => $this->needsExistingLegislature() ? ['required', 'integer'] : ['nullable'],
            'new_legislature_name' => $this->creating_legislature ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'review_group_id' => ['nullable', 'integer'],
            'new_review_group_name' => $this->creating_review_group ? ['required', 'string', 'max:255'] : ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'scope.required' => 'Choose whether this review is national or sub-national.',
            'jurisdiction_id.required' => 'Choose the sub-national jurisdiction for this review.',
            'new_jurisdiction_name.required' => 'Enter the name of the sub-national jurisdiction you want to create.',
            'legislature_id.required' => 'Choose the legislature for this review.',
            'new_legislature_name.required' => 'Enter the name of the legislature you want to create.',
            'new_review_group_name.required' => 'Enter the name of the inquiry lead you want to create.',
            'title.required' => 'Enter a title for this review.',
            'description.required' => 'Enter a working summary for this review.',
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
            'scope' => 'scope',
            'jurisdiction_id' => 'sub-national jurisdiction',
            'new_jurisdiction_name' => 'new sub-national jurisdiction',
            'legislature_id' => 'legislature',
            'new_legislature_name' => 'new legislature',
            'review_group_id' => 'inquiry lead',
            'new_review_group_name' => 'new inquiry lead',
            'description' => 'description',
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
                'scope' => 'Your account needs a country scope before you can create reviews.',
            ]);
        }

        return $country;
    }

    private function nationalLegislatures(Country $country): Collection
    {
        return Legislature::query()
            ->whereHas('jurisdiction', function ($query) use ($country): void {
                $query
                    ->where('country_id', $country->id)
                    ->whereIn('jurisdiction_type', $this->nationalJurisdictionTypeValues());
            })
            ->with('jurisdiction.country')
            ->orderBy('name')
            ->get();
    }

    private function subnationalJurisdictions(Country $country): Collection
    {
        return Jurisdiction::query()
            ->where('country_id', $country->id)
            ->whereNotIn('jurisdiction_type', $this->nationalJurisdictionTypeValues())
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

    private function resolveSelectedLegislature(Collection $legislatures): ?Legislature
    {
        if ($this->legislature_id === '') {
            return null;
        }

        return $legislatures->firstWhere('id', (int) $this->legislature_id);
    }

    private function resolveSelectedInquiryLead(Collection $reviewGroups): ?ReviewGroup
    {
        if ($this->review_group_id === '') {
            return null;
        }

        return $reviewGroups->firstWhere('id', (int) $this->review_group_id);
    }

    private function resolvePlacementForSave(
        Country $country,
        Collection $nationalLegislatures,
        Collection $subnationalJurisdictions,
    ): array {
        if ($this->scope === 'national') {
            $legislature = $this->resolveLegislatureForSave($nationalLegislatures);

            return [$legislature->jurisdiction, $legislature];
        }

        if ($this->scope === 'subnational') {
            $jurisdiction = $this->creating_jurisdiction
                ? $this->firstOrCreateJurisdiction($country)
                : $this->resolveJurisdictionForSave($subnationalJurisdictions);
            $legislature = $this->creating_legislature
                ? $this->firstOrCreateLegislature($jurisdiction)
                : $this->resolveLegislatureForSave($this->legislatures($jurisdiction));

            return [$jurisdiction, $legislature];
        }

        throw ValidationException::withMessages([
            'scope' => 'Choose whether this review is national or sub-national.',
        ]);
    }

    private function resolveJurisdictionForSave(Collection $jurisdictions): Jurisdiction
    {
        $jurisdiction = $this->resolveSelectedJurisdiction($jurisdictions);

        if ($jurisdiction === null) {
            throw ValidationException::withMessages([
                'jurisdiction_id' => 'Choose a sub-national jurisdiction inside your assigned country.',
            ]);
        }

        return $jurisdiction;
    }

    private function resolveLegislatureForSave(Collection $legislatures): Legislature
    {
        $legislature = $this->resolveSelectedLegislature($legislatures);

        if ($legislature === null) {
            throw ValidationException::withMessages([
                'legislature_id' => 'Choose a legislature that matches the selected scope.',
            ]);
        }

        return $legislature;
    }

    private function resolveInquiryLeadForSave(Collection $reviewGroups): ?ReviewGroup
    {
        return $this->resolveSelectedInquiryLead($reviewGroups);
    }

    private function firstOrCreateJurisdiction(Country $country): Jurisdiction
    {
        $name = trim($this->new_jurisdiction_name);

        $existingJurisdiction = Jurisdiction::query()
            ->where('country_id', $country->id)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();

        if ($existingJurisdiction !== null) {
            return $existingJurisdiction;
        }

        return Jurisdiction::query()->create([
            'country_id' => $country->id,
            'name' => $name,
            'slug' => $this->generateUniqueJurisdictionSlug($country, $name),
            'jurisdiction_type' => JurisdictionType::Region,
            'parent_id' => $this->nationalParentJurisdiction($country)?->id,
        ]);
    }

    private function firstOrCreateLegislature(Jurisdiction $jurisdiction): Legislature
    {
        if (in_array($jurisdiction->jurisdiction_type, $this->nationalJurisdictionTypes(), true)) {
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

    private function generateUniqueJurisdictionSlug(Country $country, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slugBase = $baseSlug !== '' ? $baseSlug : 'jurisdiction';
        $slug = $slugBase;
        $suffix = 2;

        while (
            Jurisdiction::query()
                ->where('country_id', $country->id)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        return $slug;
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

    private function nationalParentJurisdiction(Country $country): ?Jurisdiction
    {
        return Jurisdiction::query()
            ->where('country_id', $country->id)
            ->whereIn('jurisdiction_type', $this->nationalJurisdictionTypeValues())
            ->orderByRaw(
                'case when jurisdiction_type = ? then 0 else 1 end',
                [JurisdictionType::National->value],
            )
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<JurisdictionType>
     */
    private function nationalJurisdictionTypes(): array
    {
        return [
            JurisdictionType::National,
            JurisdictionType::Federal,
        ];
    }

    /**
     * @return list<string>
     */
    private function nationalJurisdictionTypeValues(): array
    {
        return array_map(
            static fn (JurisdictionType $jurisdictionType): string => $jurisdictionType->value,
            $this->nationalJurisdictionTypes(),
        );
    }

    private function needsExistingLegislature(): bool
    {
        if ($this->scope === 'national') {
            return true;
        }

        return $this->scope === 'subnational' && ! $this->creating_legislature;
    }

    private function needsExistingSubnationalJurisdiction(): bool
    {
        return $this->scope === 'subnational' && ! $this->creating_jurisdiction;
    }

    private function resetPlacement(): void
    {
        $this->jurisdiction_search = '';
        $this->jurisdiction_id = '';
        $this->creating_jurisdiction = false;
        $this->new_jurisdiction_name = '';
        $this->legislature_search = '';
        $this->legislature_id = '';
        $this->creating_legislature = false;
        $this->new_legislature_name = '';
        $this->review_group_search = '';
        $this->review_group_id = '';
        $this->creating_review_group = false;
        $this->new_review_group_name = '';
    }
}

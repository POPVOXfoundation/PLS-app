<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Set up a PLS review workspace') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-3xl">
                {{ __('Tell PLSAssist what you want to review, place the inquiry institutionally, and then add the legislation or source text for AI-assisted setup.') }}
            </flux:subheading>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
            {{ __('Back to reviews') }}
        </flux:button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
        <flux:card class="space-y-8">
            @if ($userCountry === null)
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.heading>{{ __('Country scope required') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Your account needs a country assignment before you can create reviews.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <form wire:submit="save" class="w-full space-y-4 pt-2">
                    <div class="space-y-8">
                        <section class="space-y-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-900 dark:bg-violet-900/50 dark:text-violet-100">1</span>
                                    <flux:heading size="lg">{{ __('Tell us about the review') }}</flux:heading>
                                </div>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Start with a plain-language description. The title can be a working title and can be refined after legislation is uploaded.') }}
                                </flux:text>
                            </div>

                            <flux:textarea
                                wire:model="description"
                                error:class="!block min-h-5 mt-1.5"
                                :invalid="$errors->has('description')"
                                :label="__('What review would you like to run?')"
                                :badge="__('Required')"
                                rows="6"
                                placeholder="Example: We want to review whether the Domestic Abuse Act has improved protection for victims, where implementation is delayed, and what evidence the committee should gather."
                            />

                            <div class="grid gap-x-6 gap-y-4 md:grid-cols-[minmax(0,1fr)_14rem] md:items-end">
                                <flux:input
                                    wire:model="title"
                                    error:class="!block min-h-5 mt-1.5"
                                    :invalid="$errors->has('title')"
                                    :label="__('Working title')"
                                    :badge="__('Required')"
                                    :placeholder="__('Example: Review of the Domestic Abuse Act')"
                                />

                                <flux:input
                                    wire:model="start_date"
                                    error:class="!block min-h-5 mt-1.5"
                                    :invalid="$errors->has('start_date')"
                                    :label="__('Start date')"
                                    :badge="__('Optional')"
                                    type="date"
                                />
                            </div>
                        </section>

                        <section class="space-y-4 border-t border-zinc-200/80 pt-6 dark:border-zinc-800">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-7 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-900 dark:bg-violet-900/50 dark:text-violet-100">2</span>
                                    <flux:heading size="lg">{{ __('Place the inquiry') }}</flux:heading>
                                </div>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('This keeps the workspace connected to the right country, legislature, and committee or office.') }}
                                </flux:text>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
                                <div>
                                    <flux:select
                                        wire:model.live="scope"
                                        error:class="!block min-h-5 mt-1.5"
                                        :invalid="$errors->has('scope')"
                                        :label="__('Scope')"
                                        :badge="__('Required')"
                                        :placeholder="__('Choose national or sub-national')"
                                    >
                                        <flux:select.option value="national">{{ __('National') }}</flux:select.option>
                                        <flux:select.option value="subnational">{{ __('Sub-national') }}</flux:select.option>
                                    </flux:select>
                                </div>

                                <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
                                    {{ __('Choose whether the inquiry sits at the national level or inside a sub-national place.') }}
                                </div>
                            </div>

                        @if ($scope === 'subnational')
                            @php
                                $jurisdictionComboboxClass = '[&_[data-flux-options]:has(>ui-options>:where([data-flux-listbox-empty],ui-option-empty):only-child)]:hidden';

                                if ($subnationalJurisdictions->isEmpty() && mb_strlen(trim($jurisdiction_search)) === 0) {
                                    $jurisdictionComboboxClass .= ' [&_[data-flux-options]]:hidden';
                                }
                            @endphp

                            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
                                <div>
                                    <flux:select
                                        wire:model.live="jurisdiction_id"
                                        wire:key="create-review-jurisdiction-{{ $scope !== '' ? $scope : 'none' }}"
                                        variant="combobox"
                                        :class="$jurisdictionComboboxClass"
                                        error:class="!block min-h-5 mt-1.5"
                                        :invalid="$errors->has('jurisdiction_id') || $errors->has('new_jurisdiction_name')"
                                        :label="__('Sub-national jurisdiction')"
                                        :placeholder="__('Search or create a jurisdiction')"
                                    >
                                        <x-slot name="input">
                                            <flux:select.input
                                                wire:model.live="jurisdiction_search"
                                                :invalid="$errors->has('jurisdiction_id') || $errors->has('new_jurisdiction_name')"
                                                :placeholder="__('Search or create a jurisdiction')"
                                            />
                                        </x-slot>

                                        <x-slot name="empty">
                                            <flux:select.option.empty class="hidden" />
                                        </x-slot>
                                        @foreach ($subnationalJurisdictions as $jurisdiction)
                                            <flux:select.option :value="$jurisdiction->id">
                                                {{ $jurisdiction->name }}
                                            </flux:select.option>
                                        @endforeach

                                        @if (mb_strlen(trim($jurisdiction_search)) >= 1)
                                            <flux:select.option.create wire:click="createJurisdiction" min-length="1">
                                                {{ __('Create jurisdiction') }}
                                            </flux:select.option.create>
                                        @endif

                                    </flux:select>
                                </div>

                                <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
                                    {{ __('Use this only for sub-national reviews to choose or create the state, province, region, or other local unit.') }}
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
                            <div class="space-y-4">
                                @php
                                    $legislatureComboboxClass = '[&_[data-flux-options]:has(>ui-options>:where([data-flux-listbox-empty],ui-option-empty):only-child)]:hidden';

                                    if ($scope === 'subnational' && $legislatures->isEmpty() && mb_strlen(trim($legislature_search)) === 0) {
                                        $legislatureComboboxClass .= ' [&_[data-flux-options]]:hidden';
                                    }
                                @endphp

                                @if ($usingSingleLegislature && $selectedLegislature)
                                    <div class="space-y-1">
                                        <flux:label>{{ __('Legislature') }}</flux:label>
                                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-white">
                                            {{ $selectedLegislature->name }}
                                        </div>
                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Only one matching legislature is available, so PLSAssist will use it automatically.') }}
                                        </flux:text>
                                    </div>
                                @else
                                    <flux:select
                                        wire:model.live="legislature_id"
                                        wire:key="create-review-legislature-{{ $scope !== '' ? $scope : 'none' }}-{{ $selectedSubnationalJurisdiction?->id ?? 'none' }}-{{ $creating_jurisdiction ? 'creating' : 'existing' }}"
                                        variant="combobox"
                                        :class="$legislatureComboboxClass"
                                        label:class="!opacity-100 !text-zinc-800 dark:!text-white"
                                        error:class="!block min-h-5 mt-1.5"
                                        :invalid="$errors->has('legislature_id') || $errors->has('new_legislature_name')"
                                        :label="__('Legislature')"
                                        :badge="__('Required')"
                                        :placeholder="$scope === 'national'
                                            ? __('Search national legislatures')
                                            : ($scope === 'subnational'
                                            ? (($creating_jurisdiction || $selectedSubnationalJurisdiction) ? __('Search or create a legislature') : __('Choose or create the jurisdiction first'))
                                            : __('Choose the inquiry scope first'))"
                                        :empty="$scope === 'national'
                                            ? __('No national legislatures found for this country.')
                                            : __('No legislatures found for this sub-national context.')"
                                        :disabled="$scope === '' || ($scope === 'subnational' && ! $creating_jurisdiction && ! $selectedSubnationalJurisdiction)"
                                    >
                                        <x-slot name="input">
                                            <flux:select.input
                                                wire:model.live="legislature_search"
                                                :invalid="$errors->has('legislature_id') || $errors->has('new_legislature_name')"
                                                :placeholder="$scope === 'national'
                                                    ? __('Search national legislatures')
                                                : ($scope === 'subnational' ? __('Search or create a legislature') : __('Choose the inquiry scope first'))"
                                            />
                                        </x-slot>

                                        <x-slot name="empty">
                                            @if ($scope === 'national')
                                                <flux:select.option.empty>
                                                    {{ __('No national legislatures found for this country.') }}
                                                </flux:select.option.empty>
                                            @else
                                                <flux:select.option.empty class="hidden" />
                                            @endif
                                        </x-slot>

                                        @foreach ($legislatures as $legislature)
                                            <flux:select.option :value="$legislature->id">
                                                {{ $legislature->name }}
                                            </flux:select.option>
                                        @endforeach

                                        @if ($scope === 'subnational' && ($creating_jurisdiction || $selectedSubnationalJurisdiction) && mb_strlen(trim($legislature_search)) >= 1)
                                            <flux:select.option.create wire:click="createLegislature" min-length="1">
                                                {{ __('Create legislature') }}
                                            </flux:select.option.create>
                                        @endif

                                    </flux:select>
                                @endif

                            </div>

                            <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
                                {{ __('National reviews use the imported legislature list. Sub-national reviews can use an existing legislature or create one inline.') }}
                            </div>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
                            <div class="space-y-4">
                                @php
                                    $inquiryLeadComboboxClass = '[&_[data-flux-options]:has(>ui-options>:where([data-flux-listbox-empty],ui-option-empty):only-child)]:hidden';

                                    if ($reviewGroups->isEmpty() && mb_strlen(trim($review_group_search)) === 0) {
                                        $inquiryLeadComboboxClass .= ' [&_[data-flux-options]]:hidden';
                                    }
                                @endphp

                                @if ($usingSingleReviewGroup && $selectedReviewGroup)
                                    <div class="space-y-1">
                                        <flux:label>{{ __('What committee or office is leading this inquiry?') }}</flux:label>
                                        <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-white">
                                            {{ $selectedReviewGroup->name }}
                                        </div>
                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Only one matching committee or office is available, so PLSAssist will use it automatically.') }}
                                        </flux:text>
                                    </div>
                                @else
                                    <flux:select
                                        wire:model.live="review_group_id"
                                        wire:key="create-review-inquiry-lead-{{ $scope !== '' ? $scope : 'none' }}-{{ $selectedJurisdiction?->id ?? 'none' }}-{{ $selectedLegislature?->id ?? 'none' }}-{{ $creating_review_group ? 'creating' : 'existing' }}"
                                        variant="combobox"
                                        :class="$inquiryLeadComboboxClass"
                                        label:class="!opacity-100 !text-zinc-800 dark:!text-white"
                                        error:class="!block min-h-5 mt-1.5"
                                        :invalid="$errors->has('review_group_id') || $errors->has('new_review_group_name')"
                                        :label="__('What committee or office is leading this inquiry?')"
                                        :badge="__('Optional')"
                                        :placeholder="$scope === ''
                                            ? __('Choose the inquiry scope first')
                                            : ($legislatureContextReady ? __('Search or create a committee, office, or unit') : __('Choose the legislature first'))"
                                        :disabled="$scope === '' || ! $legislatureContextReady"
                                    >
                                        <x-slot name="input">
                                            <flux:select.input
                                                wire:model.live="review_group_search"
                                                :invalid="$errors->has('review_group_id') || $errors->has('new_review_group_name')"
                                                :placeholder="$scope === ''
                                                    ? __('Choose the inquiry scope first')
                                                    : __('Search or create a committee, office, or unit')"
                                            />
                                        </x-slot>

                                        <x-slot name="empty">
                                            <flux:select.option.empty class="hidden" />
                                        </x-slot>

                                        @foreach ($reviewGroups as $reviewGroup)
                                            <flux:select.option :value="$reviewGroup->id">
                                                {{ $reviewGroup->name }}
                                            </flux:select.option>
                                        @endforeach

                                        @if ($legislatureContextReady && mb_strlen(trim($review_group_search)) >= 1)
                                            <flux:select.option.create wire:click="createReviewGroup" min-length="1">
                                                {{ __('Create committee or office') }}
                                            </flux:select.option.create>
                                        @endif

                                    </flux:select>
                                @endif

                            </div>

                            <div class="rounded-lg border border-zinc-200/80 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-300">
                                {{ __('Use the committee, office, organisation, or unit that will own the inquiry. You can create it here if it is missing.') }}
                            </div>
                        </div>
                        </section>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-800 sm:flex-row sm:justify-end">
                        <flux:button variant="ghost" :href="route('pls.reviews.index')" wire:navigate>
                            {{ __('Cancel') }}
                        </flux:button>

                        <flux:button variant="primary" type="submit" icon="sparkles">
                            {{ __('Create workspace and add legislation') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('What happens next') }}</flux:heading>
                    <flux:text>{{ __('After the workspace is created, PLSAssist will take you to the legislation page so you can upload the legislation or source text.') }}</flux:text>
                </div>

                <div class="rounded-lg border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950 dark:border-violet-900 dark:bg-violet-950/30 dark:text-violet-100">
                    {{ __('The AI can then suggest legislation details such as title, short title, type, date, summary, themes, and possible duplicates. You will review those suggestions before they are saved.') }}
                </div>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Workspace preview') }}</flux:heading>
                    <flux:text>{{ __('This preview updates as the inquiry is placed institutionally.') }}</flux:text>
                </div>

                <flux:table>
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Scope') }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $scope === 'national' ? __('National') : ($scope === 'subnational' ? __('Sub-national') : '—') }}
                            </flux:table.cell>
                        </flux:table.row>
                        @if ($scope === 'subnational')
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Jurisdiction') }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $creating_jurisdiction && $new_jurisdiction_name !== '' ? $new_jurisdiction_name : ($selectedJurisdiction?->name ?? '—') }}
                                </flux:table.cell>
                            </flux:table.row>
                        @endif
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Legislature') }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $creating_legislature && $new_legislature_name !== '' ? $new_legislature_name : ($selectedLegislature?->name ?? '—') }}
                            </flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Lead office') }}</flux:table.cell>
                            <flux:table.cell>
                                {{ $creating_review_group && $new_review_group_name !== '' ? $new_review_group_name : ($selectedReviewGroup?->name ?? '—') }}
                            </flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </flux:card>

        </div>
    </div>
</div>

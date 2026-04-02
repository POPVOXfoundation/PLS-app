<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Create PLS Review') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-3xl">
                {{ __('Create a new post-legislative scrutiny inquiry inside your country scope. Set the institutional placement first, then add collaborators later.') }}
            </flux:subheading>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
            {{ __('Back to reviews') }}
        </flux:button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
        <flux:card class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Institutional placement') }}</flux:heading>
                <flux:text>{{ __('Country comes from your account. Use the fields below to place the inquiry within that country, then add the review details.') }}</flux:text>
            </div>

            @if ($userCountry === null)
                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.heading>{{ __('Country scope required') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Your account needs a country assignment before you can create reviews.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <form wire:submit="save" class="space-y-6">
                    <div class="rounded-2xl border border-zinc-200 bg-zinc-50/80 px-4 py-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                        <div class="space-y-1">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                {{ __('Country scope') }}
                            </flux:text>
                            <flux:heading size="sm">{{ $userCountry->name }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('This review will be created inside your assigned country. Country is no longer selected on the inquiry form.') }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <flux:select
                                wire:model.live="jurisdiction_id"
                                :invalid="$errors->has('jurisdiction_id')"
                                :label="__('Jurisdiction')"
                                :description="__('Choose where in your country this inquiry sits.')"
                                :placeholder="__('Select a jurisdiction')"
                            >
                                @foreach ($jurisdictions as $jurisdiction)
                                    <flux:select.option :value="$jurisdiction->id">
                                        {{ $jurisdiction->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex-1">
                                        <flux:select
                                            wire:model.live="legislature_id"
                                            :invalid="$errors->has('legislature_id')"
                                            :label="__('Legislature')"
                                            :description="__('Choose the legislature tied to this jurisdiction.')"
                                            :placeholder="$selectedJurisdiction ? __('Select a legislature') : __('Choose a jurisdiction first')"
                                            :disabled="$selectedJurisdiction === null || $creating_legislature"
                                        >
                                            @foreach ($legislatures as $legislature)
                                                <flux:select.option :value="$legislature->id">
                                                    {{ $legislature->name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>

                                    @if ($selectedJurisdiction && $canCreateLegislatureInline)
                                        <flux:button
                                            type="button"
                                            size="sm"
                                            :variant="$creating_legislature ? 'filled' : 'ghost'"
                                            wire:click="toggleLegislatureCreation"
                                        >
                                            {{ $creating_legislature ? __('Use existing legislature') : __('Add legislature') }}
                                        </flux:button>
                                    @endif
                                </div>

                                @if ($creating_legislature)
                                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                                        <flux:input
                                            wire:model="new_legislature_name"
                                            :invalid="$errors->has('new_legislature_name')"
                                            :label="__('New legislature')"
                                            :description="__('Create a legislature inside the selected non-national jurisdiction.')"
                                            :placeholder="__('Tennessee General Assembly')"
                                        />
                                    </div>
                                @elseif ($selectedJurisdiction && ! $canCreateLegislatureInline)
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('National legislatures come from the shared catalog for this country.') }}
                                    </flux:text>
                                @elseif ($selectedJurisdiction)
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Can’t find the right legislature? You can add one inline for this non-national jurisdiction.') }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex-1">
                                        <flux:select
                                            wire:model.live="review_group_id"
                                            :invalid="$errors->has('review_group_id')"
                                            :label="__('Inquiry lead')"
                                            :description="__('The committee, office, organization, or unit leading this inquiry.')"
                                            :placeholder="$legislatureContextReady ? __('Select an inquiry lead') : __('Choose the jurisdiction and legislature first')"
                                            :disabled="! $legislatureContextReady || $creating_review_group"
                                        >
                                            @foreach ($reviewGroups as $reviewGroup)
                                                <flux:select.option :value="$reviewGroup->id">
                                                    {{ $reviewGroup->name }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </div>

                                    <flux:button
                                        type="button"
                                        size="sm"
                                        :variant="$creating_review_group ? 'filled' : 'ghost'"
                                        :disabled="! $legislatureContextReady"
                                        wire:click="toggleReviewGroupCreation"
                                    >
                                        {{ $creating_review_group ? __('Use existing inquiry lead') : __('Add inquiry lead') }}
                                    </flux:button>
                                </div>

                                @if ($creating_review_group)
                                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-4 dark:border-zinc-700 dark:bg-zinc-900/50">
                                        <flux:input
                                            wire:model="new_review_group_name"
                                            :invalid="$errors->has('new_review_group_name')"
                                            :label="__('New inquiry lead')"
                                            :description="__('Create a scoped inquiry lead for this legislature and jurisdiction.')"
                                            :placeholder="__('Public Accounts Committee')"
                                        />
                                    </div>
                                @elseif ($legislatureContextReady)
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('If the right inquiry lead is missing, add it inline and it will stay scoped to this institutional context.') }}
                                    </flux:text>
                                @endif
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <flux:input
                                wire:model="title"
                                :invalid="$errors->has('title')"
                                :label="__('Review title')"
                                :description="__('Use the public-facing title for the inquiry.')"
                                :placeholder="__('Post-Legislative Review of the Access to Information Act')"
                            />
                        </div>

                        <flux:input
                            wire:model="start_date"
                            :invalid="$errors->has('start_date')"
                            :label="__('Start date')"
                            :description="__('Optional. Leave blank if the review has not started formally.')"
                            type="date"
                        />

                        <div class="flex items-end">
                            <flux:callout icon="information-circle" class="w-full">
                                <flux:callout.text>
                                    {{ __('The signed-in creator remains the review owner. Collaborators are added separately after setup.') }}
                                </flux:callout.text>
                            </flux:callout>
                        </div>

                        <div class="md:col-span-2">
                            <flux:textarea
                                wire:model="description"
                                :invalid="$errors->has('description')"
                                :label="__('Description')"
                                :description="__('Optional working summary of the review objective and expected scope.')"
                                rows="6"
                                placeholder="Assess implementation outcomes, institutional bottlenecks, and follow-up obligations under the target legislation."
                            />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-zinc-200 pt-6 dark:border-zinc-700 sm:flex-row sm:justify-end">
                        <flux:button variant="ghost" :href="route('pls.reviews.index')" wire:navigate>
                            {{ __('Cancel') }}
                        </flux:button>

                        <flux:button variant="primary" type="submit" icon="sparkles">
                            {{ __('Create review') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Institution preview') }}</flux:heading>
                    <flux:text>{{ __('This preview shows the institutional placement for the inquiry. Ownership stays with the signed-in creator.') }}</flux:text>
                </div>

                @if ($userCountry)
                    <flux:table>
                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Country scope') }}</flux:table.cell>
                                <flux:table.cell>{{ $userCountry->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Jurisdiction') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedJurisdiction?->name ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Legislature') }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $creating_legislature && $new_legislature_name !== '' ? $new_legislature_name : ($selectedLegislature?->name ?? '—') }}
                                </flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Inquiry lead') }}</flux:table.cell>
                                <flux:table.cell>
                                    {{ $creating_review_group && $new_review_group_name !== '' ? $new_review_group_name : ($selectedReviewGroup?->name ?? '—') }}
                                </flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:callout icon="arrow-left">
                        <flux:callout.text>
                            {{ __('Assign a country to the account before creating a review.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </flux:card>

            <flux:card class="space-y-3">
                <flux:heading size="lg">{{ __('Ownership') }}</flux:heading>
                <flux:text>
                    {{ __('Institutional fields describe where the inquiry sits. They do not replace the technical review owner or the later collaborator workflow.') }}
                </flux:text>
            </flux:card>
        </div>
    </div>
</div>

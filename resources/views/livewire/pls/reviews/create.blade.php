<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Create PLS Review') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-3xl">
                {{ __('Start a new post-legislative scrutiny inquiry. The selected committee determines the legislature, jurisdiction, country, and seeded 11-step workflow.') }}
            </flux:subheading>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
            {{ __('Back to reviews') }}
        </flux:button>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
        <flux:card class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Review details') }}</flux:heading>
                <flux:text>{{ __('Capture the minimal review metadata now. Supporting legislation, documents, and findings can be added from the review workspace.') }}</flux:text>
            </div>

            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <flux:select
                            wire:model.live="committee_id"
                            :invalid="$errors->has('committee_id')"
                            :label="__('Committee')"
                            :description="__('The committee drives the institutional hierarchy for this review.')"
                            :placeholder="__('Select a committee')"
                        >
                            @foreach ($committees as $committee)
                                <flux:select.option :value="$committee->id">
                                    {{ $committee->name }} · {{ $committee->legislature->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
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
                                {{ __('The review is created in draft status with step 1 selected and all 11 workflow steps seeded automatically.') }}
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
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Institution preview') }}</flux:heading>
                    <flux:text>{{ __('Derived automatically from the chosen committee.') }}</flux:text>
                </div>

                @if ($selectedCommittee)
                    <flux:table>
                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Committee') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedCommittee->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Legislature') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedCommittee->legislature->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Jurisdiction') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedCommittee->legislature->jurisdiction->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Country') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedCommittee->legislature->jurisdiction->country->name }}</flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:callout icon="arrow-left">
                        <flux:callout.text>
                            {{ __('Choose a committee to preview the legislature, jurisdiction, and country.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Seeded workflow') }}</flux:heading>
                    <flux:text>{{ __('Every new review starts with the canonical 11-step post-legislative scrutiny workflow.') }}</flux:text>
                </div>

                <flux:timeline>
                    @foreach ($workflowSteps as $step)
                        <flux:timeline.item :status="$loop->first ? 'current' : 'incomplete'">
                            <flux:timeline.indicator>{{ $step['number'] }}</flux:timeline.indicator>
                            <flux:timeline.content>
                                <flux:heading size="sm">{{ $step['title'] }}</flux:heading>
                            </flux:timeline.content>
                        </flux:timeline.item>
                    @endforeach
                </flux:timeline>
            </flux:card>
        </div>
    </div>
</div>

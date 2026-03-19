<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('Create PLS Review') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-3xl">
                {{ __('Start a new post-legislative scrutiny inquiry. Choose the legislature first, then optionally link the review to a review group for institutional context.') }}
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
                            wire:model.live="legislature_id"
                            :invalid="$errors->has('legislature_id')"
                            :label="__('Legislature')"
                            :description="__('The legislature anchors the review’s institutional context.')"
                            :placeholder="__('Select a legislature')"
                        >
                            @foreach ($legislatures as $legislature)
                                <flux:select.option :value="$legislature->id">
                                    {{ $legislature->name }} · {{ $legislature->jurisdiction->name }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="md:col-span-2">
                        <flux:select
                            wire:model.live="review_group_id"
                            :invalid="$errors->has('review_group_id')"
                            :label="__('Review group')"
                            :description="__('Optional. Add a review group for organizational context only. Access is managed separately through collaborators.')"
                            :placeholder="$selectedLegislature ? __('Select a review group') : __('Choose a legislature first')"
                        >
                            @foreach ($reviewGroups as $reviewGroup)
                                <flux:select.option :value="$reviewGroup->id">
                                    {{ $reviewGroup->name }} · {{ \Illuminate\Support\Str::headline($reviewGroup->type->value) }}
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
                    <flux:text>{{ __('The legislature sets the required institutional context. Review group assignment is optional and does not control access.') }}</flux:text>
                </div>

                @if ($selectedLegislature)
                    <flux:table>
                        <flux:table.rows>
                            @if ($selectedReviewGroup)
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Review group') }}</flux:table.cell>
                                    <flux:table.cell>{{ $selectedReviewGroup->name }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Type') }}</flux:table.cell>
                                    <flux:table.cell>{{ \Illuminate\Support\Str::headline($selectedReviewGroup->type->value) }}</flux:table.cell>
                                </flux:table.row>
                            @endif
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Legislature') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedLegislature->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Jurisdiction') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedLegislature->jurisdiction->name }}</flux:table.cell>
                            </flux:table.row>
                            <flux:table.row>
                                <flux:table.cell variant="strong">{{ __('Country') }}</flux:table.cell>
                                <flux:table.cell>{{ $selectedLegislature->jurisdiction->country->name }}</flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                @else
                    <flux:callout icon="arrow-left">
                        <flux:callout.text>
                            {{ __('Choose a legislature to preview the jurisdiction and country, then optionally add a review group for context.') }}
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

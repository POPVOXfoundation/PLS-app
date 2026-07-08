<div
    data-assistant-sidebar
    x-data="{
        pendingMessage: '',
        assistantOpen: false,
        scrollToBottom() {
            $nextTick(() => {
                const scroll = () => {
                    if (this.$refs.messages) {
                        this.$refs.messages.scrollTo({ top: this.$refs.messages.scrollHeight, behavior: 'smooth' });
                    }
                };

                scroll();
                requestAnimationFrame(scroll);
                setTimeout(scroll, 180);
                setTimeout(scroll, 420);
            });
        },
    }"
    x-on:assistant-message-added.window="pendingMessage = ''; assistantOpen = true; scrollToBottom()"
    x-on:assistant-open-requested.window="pendingMessage = $event.detail.prompt || ''; assistantOpen = true; scrollToBottom()"
    class="fixed inset-x-3 bottom-3 z-50 xl:left-[16rem] xl:right-6 2xl:left-[17rem] print:hidden"
>
    <section
        x-show="assistantOpen"
        x-cloak
        x-transition
        class="mb-3 flex max-h-[58vh] min-h-[22rem] flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_24px_80px_-36px_rgba(15,23,42,0.45)] sm:max-h-[34rem] dark:border-zinc-700 dark:bg-zinc-900"
    >
        <div class="border-b border-violet-300/30 bg-gradient-to-r from-slate-500 via-indigo-500 to-violet-500 px-4 py-2.5">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="base" class="!text-white">{{ __('PLS Assistant') }}</flux:heading>
                        <flux:badge size="sm" class="!border-white/20 !bg-white/15 !text-white" rounded>{{ $assistantContext['workspace_label'] }}</flux:badge>
                    </div>
                    <flux:text class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-300">
                        {{ $assistantContext['playbook']['role'] }}
                    </flux:text>
                </div>

                <button
                    type="button"
                    x-on:click="assistantOpen = false"
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg text-white/80 hover:bg-white/10 hover:text-white"
                    aria-label="{{ __('Collapse assistant') }}"
                >
                    <flux:icon icon="chevron-down" class="size-4" />
                </button>
            </div>
        </div>

        <div
            x-ref="messages"
            x-init="requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight })"
            class="min-h-0 flex-1 overflow-y-auto bg-[linear-gradient(180deg,rgba(250,250,252,0.9)_0%,rgba(255,255,255,1)_18%)] px-4 py-3.5 dark:[background:var(--color-zinc-900)]"
        >
            <div class="space-y-2.5">
                @if ($assistantMessages === [] && $assistantError === null)
                    <div class="rounded-2xl border border-violet-200 bg-violet-50/80 px-3.5 py-3 dark:border-violet-400/20 dark:bg-violet-500/10">
                        <div class="flex items-start gap-2.5">
                            <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <img src="{{ asset('images/PLS bot sq.png') }}" alt="{{ __('PLSAssist') }}" class="size-full object-contain" />
                            </div>
                            <div class="min-w-0">
                                <flux:text class="text-sm font-semibold text-zinc-900 dark:!text-zinc-100">
                                    {{ __('How this assistant can help here') }}
                                </flux:text>
                                <flux:text class="mt-0.5 text-[13px] leading-5 text-zinc-600 dark:!text-zinc-400">
                                    {{ $assistantContext['intro'] }}
                                </flux:text>

                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    @foreach ($assistantContext['playbook']['suggested_prompts'] as $prompt)
                                        <button
                                            type="button"
                                            x-on:click="assistantOpen = true"
                                            wire:click="sendPrompt(@js($prompt))"
                                            wire:loading.attr="disabled"
                                            wire:target="submitAssistantPrompt, sendPrompt"
                                            class="rounded-full border border-violet-200 bg-white px-2.5 py-1 text-left text-xs font-medium text-violet-700 hover:bg-violet-100 disabled:pointer-events-none disabled:opacity-50 dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-400 dark:hover:bg-violet-500/20"
                                        >
                                            {{ $prompt }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @foreach ($assistantMessages as $message)
                    <div data-message @class([
                        'flex justify-end pl-10' => $message['role'] === 'user',
                        'flex items-start gap-2 pr-2' => $message['role'] === 'assistant',
                    ])>
                        @if ($message['role'] === 'assistant')
                            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                                <img src="{{ asset('images/PLS bot sq.png') }}" alt="{{ __('PLSAssist') }}" class="size-full object-contain" />
                            </div>
                        @endif

                        <div @class([
                            'text-left',
                            'max-w-[86%] rounded-[1.25rem] rounded-tl-md border border-violet-200 bg-violet-50 px-3.5 py-2.5 text-violet-900 dark:border-violet-400/20 dark:bg-violet-500/10 dark:text-zinc-100' => $message['role'] === 'assistant',
                            'ml-auto inline-flex w-auto max-w-[70%] rounded-[1rem] rounded-br-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-700/50 dark:text-zinc-100' => $message['role'] === 'user',
                        ])>
                            @if ($message['role'] === 'assistant')
                                <div class="text-[13px] leading-5 [&>*+*]:mt-1.5 [&>p+p]:mt-1.5 [&>p.heading]:mt-3 [&>p.heading:first-child]:mt-0 [&>p.heading+*]:mt-0.5 [&>ul+p:not(.heading)]:mt-1.5">
                                    @foreach ($this->assistantMessageBlocks($message['content']) as $block)
                                        @if ($block['type'] === 'heading')
                                            <p class="heading m-0 font-semibold text-violet-800 dark:text-violet-300">
                                                {{ $block['content'] }}
                                            </p>
                                        @elseif ($block['type'] === 'list')
                                            <ul class="m-0 list-disc space-y-0.5 pl-5 marker:text-violet-400">
                                                @foreach ($block['items'] as $item)
                                                    <li>{{ $item }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <p class="m-0">{{ $block['content'] }}</p>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-[13px] leading-5">
                                    {{ $message['content'] }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                @if ($assistantError)
                    <div class="flex gap-2">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">
                            <flux:icon icon="exclamation-triangle" class="size-3.5" />
                        </div>

                        <div class="max-w-[92%] rounded-2xl rounded-tl-md border border-rose-200 bg-rose-50 px-3.5 py-2 text-[13px] leading-5 text-rose-900 dark:border-rose-400/20 dark:bg-rose-500/10 dark:text-rose-200">
                            {{ $assistantError }}
                        </div>
                    </div>
                @endif

                <div
                    x-show="pendingMessage.trim() !== ''"
                    x-cloak
                    class="flex justify-end pl-10"
                    data-message
                >
                    <div class="ml-auto inline-flex w-auto max-w-[70%] rounded-[1rem] rounded-br-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-zinc-800 dark:border-zinc-600 dark:bg-zinc-700/50 dark:text-zinc-100">
                        <div class="text-[13px] leading-5" x-text="pendingMessage"></div>
                    </div>
                </div>

                <div wire:loading.flex wire:target="submitAssistantPrompt, sendPrompt" data-thinking class="items-start gap-2 pr-2">
                    <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <img src="{{ asset('images/PLS bot sq.png') }}" alt="{{ __('PLSAssist') }}" class="size-full animate-pulse object-contain" />
                    </div>
                    <div class="rounded-[1.25rem] rounded-tl-md border border-violet-200 bg-violet-50 px-3.5 py-2.5 dark:border-violet-400/20 dark:bg-violet-500/10">
                        <div class="flex items-center gap-1.5">
                            <span class="size-1.5 animate-pulse rounded-full bg-violet-400"></span>
                            <span class="size-1.5 animate-pulse rounded-full bg-violet-400 [animation-delay:150ms]"></span>
                            <span class="size-1.5 animate-pulse rounded-full bg-violet-400 [animation-delay:300ms]"></span>
                            <flux:text class="ml-1 text-[13px] text-violet-600">{{ __('Thinking...') }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="overflow-hidden rounded-2xl border border-zinc-200/90 bg-white shadow-[0_18px_70px_-34px_rgba(15,23,42,0.5)] dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-3 border-b border-zinc-200/80 px-3 py-2 dark:border-zinc-700">
            <button
                type="button"
                x-on:click="assistantOpen = ! assistantOpen; if (assistantOpen) scrollToBottom()"
                class="flex min-w-0 flex-1 items-center gap-2 text-left"
                x-bind:aria-expanded="assistantOpen.toString()"
            >
                <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <img src="{{ asset('images/PLS bot sq.png') }}" alt="{{ __('PLSAssist') }}" class="size-full object-contain" />
                </span>
                <span class="min-w-0">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('PLS Assistant') }}</span>
                        <flux:badge size="sm" color="violet">{{ $assistantContext['workspace_label'] }}</flux:badge>
                    </span>
                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $this->assistantPlaceholder($assistantContext['workspace_key']) }}</span>
                </span>
            </button>

            <button
                type="button"
                x-on:click="assistantOpen = ! assistantOpen; if (assistantOpen) scrollToBottom()"
                class="flex size-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                aria-label="{{ __('Toggle assistant') }}"
            >
                <flux:icon icon="chevron-up" x-show="! assistantOpen" class="size-4" />
                <flux:icon icon="chevron-down" x-show="assistantOpen" x-cloak class="size-4" />
            </button>
        </div>

        <div class="px-3 py-2.5">
            @if ($assistantMessages !== [])
                <div class="mb-2 flex flex-wrap gap-1">
                    @foreach ($assistantContext['playbook']['suggested_prompts'] as $prompt)
                        <button
                            type="button"
                            x-on:click="assistantOpen = true"
                            wire:click="sendPrompt(@js($prompt))"
                            wire:loading.attr="disabled"
                            wire:target="submitAssistantPrompt, sendPrompt"
                            class="rounded-full px-2 py-0.5 text-[11px] font-medium text-violet-600 hover:bg-violet-50 hover:text-violet-800 disabled:pointer-events-none disabled:opacity-50 dark:text-violet-400 dark:hover:bg-violet-500/10 dark:hover:text-violet-300"
                        >
                            {{ $prompt }}
                        </button>
                    @endforeach
                </div>
            @endif

            <form
                wire:submit="submitAssistantPrompt"
                x-on:submit="
                    assistantOpen = true;
                    pendingMessage = $wire.assistantInput;
                    scrollToBottom();
                "
            >
                <flux:composer
                    wire:model="assistantInput"
                    submit="enter"
                    rows="1"
                    placeholder="{{ $this->assistantPlaceholder($assistantContext['workspace_key']) }}"
                >
                    <x-slot:actionsTrailing>
                        <flux:modal.trigger name="confirm-reset-chat">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="arrow-path"
                                wire:loading.attr="disabled"
                                wire:target="resetAssistantConversation, submitAssistantPrompt, sendPrompt"
                                class="!text-zinc-400 hover:!text-zinc-500"
                            />
                        </flux:modal.trigger>
                        <flux:button
                            variant="primary"
                            size="sm"
                            icon="paper-airplane"
                            type="submit"
                            class="data-loading:opacity-50"
                        />
                    </x-slot:actionsTrailing>
                </flux:composer>
            </form>
        </div>
    </div>

    <flux:modal name="confirm-reset-chat" class="max-w-sm">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Reset chat?') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    {{ __('This will clear the conversation and remove all saved messages. This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:modal.close>
                    <flux:button variant="danger" wire:click="resetAssistantConversation">
                        {{ __('Reset chat') }}
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>

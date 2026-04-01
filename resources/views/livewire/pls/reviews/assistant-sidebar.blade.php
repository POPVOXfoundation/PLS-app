<div class="flex max-h-[calc(100vh-16rem)] min-h-0 flex-col overflow-hidden rounded-2xl border border-zinc-200/80 bg-white shadow-[0_18px_45px_-28px_rgba(15,23,42,0.16)] dark:border-zinc-200 dark:bg-white">
    <div class="border-b border-violet-300/30 bg-gradient-to-r from-slate-500 via-indigo-500 to-violet-500 px-4 py-2.5">
        <div class="flex items-center gap-2">
            <flux:heading size="base" class="!text-white">{{ __('PLS Assistant') }}</flux:heading>
            <flux:badge size="sm" class="!border-white/20 !bg-white/15 !text-white" rounded>{{ $assistantContext['workspace_label'] }}</flux:badge>
        </div>
        <flux:text class="mt-0.5 text-[10px] font-semibold uppercase tracking-[0.15em] text-slate-300">
            {{ $assistantContext['playbook']['role'] }}
        </flux:text>
    </div>

    <div
        x-data="{
            scrollToBottom() { $nextTick(() => this.$el.scrollTo({ top: this.$el.scrollHeight, behavior: 'smooth' })) },
            scrollToLastMessage() {
                $nextTick(() => {
                    const messages = this.$el.querySelectorAll('[data-message]');
                    const last = messages[messages.length - 1];
                    if (last) {
                        const containerTop = this.$el.getBoundingClientRect().top;
                        const messageTop = last.getBoundingClientRect().top;
                        this.$el.scrollTo({ top: this.$el.scrollTop + (messageTop - containerTop), behavior: 'smooth' });
                    } else {
                        this.scrollToBottom();
                    }
                });
            },
        }"
        x-init="
            requestAnimationFrame(() => { $el.scrollTop = $el.scrollHeight });
            const thinkingEl = $el.querySelector('[data-thinking]');
            if (thinkingEl) {
                new MutationObserver(() => {
                    if (thinkingEl.style.display !== 'none') scrollToBottom();
                }).observe(thinkingEl, { attributes: true, attributeFilter: ['style'] });
            }
        "
        @assistant-message-added.window="scrollToLastMessage()"
        class="min-h-0 flex-1 overflow-y-auto bg-[linear-gradient(180deg,rgba(250,250,252,0.9)_0%,rgba(255,255,255,1)_18%)] px-4 py-3.5"
    >
        <div class="space-y-2.5">
            @if ($assistantMessages === [] && $assistantError === null)
                <div class="rounded-2xl border border-violet-200 bg-violet-50/80 px-3.5 py-3">
                    <div class="flex items-start gap-2.5">
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                            <flux:icon icon="sparkles" class="size-3.5" />
                        </div>
                        <div class="min-w-0">
                            <flux:text class="text-sm font-semibold text-zinc-900 dark:text-zinc-900">
                                {{ __('How this assistant can help here') }}
                            </flux:text>
                            <flux:text class="mt-0.5 text-[13px] leading-5 text-zinc-600 dark:text-zinc-500">
                                {{ $assistantContext['intro'] }}
                            </flux:text>

                            <div class="mt-2.5 flex flex-wrap gap-1.5">
                                @foreach ($assistantContext['playbook']['suggested_prompts'] as $prompt)
                                    <button
                                        type="button"
                                        wire:click="sendPrompt(@js($prompt))"
                                        wire:loading.attr="disabled"
                                        wire:target="submitAssistantPrompt, sendPrompt"
                                        class="rounded-full border border-violet-200 bg-white px-2.5 py-1 text-left text-xs font-medium text-violet-700 transition hover:bg-violet-100 disabled:pointer-events-none disabled:opacity-50"
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
                        <div class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                            <flux:icon icon="sparkles" class="size-3" />
                        </div>
                    @endif

                    <div @class([
                        'text-left',
                        'max-w-[86%] rounded-[1.25rem] rounded-tl-md border border-violet-200 bg-violet-50 px-3.5 py-2.5 text-violet-900' => $message['role'] === 'assistant',
                        'ml-auto inline-flex w-auto max-w-[58%] rounded-[1rem] rounded-br-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-zinc-800' => $message['role'] === 'user',
                    ])>
                        @if ($message['role'] === 'assistant')
                            <div class="text-[13px] leading-5 [&>*+*]:mt-1.5 [&>p+p]:mt-1.5 [&>p.heading]:mt-3 [&>p.heading:first-child]:mt-0 [&>p.heading+*]:mt-0.5 [&>ul+p:not(.heading)]:mt-1.5">
                                @foreach ($this->assistantMessageBlocks($message['content']) as $block)
                                    @if ($block['type'] === 'heading')
                                        <p class="heading m-0 font-semibold text-violet-800">
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
                    <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-700">
                        <flux:icon icon="exclamation-triangle" class="size-3.5" />
                    </div>

                    <div class="max-w-[92%] rounded-2xl rounded-tl-md border border-rose-200 bg-rose-50 px-3.5 py-2 text-[13px] leading-5 text-rose-900">
                        {{ $assistantError }}
                    </div>
                </div>
            @endif

            <div wire:loading.flex wire:target="submitAssistantPrompt, sendPrompt" data-thinking class="items-start gap-2 pr-2">
                <div class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-violet-700">
                    <flux:icon icon="sparkles" class="size-3 animate-spin" />
                </div>
                <div class="rounded-[1.25rem] rounded-tl-md border border-violet-200 bg-violet-50 px-3.5 py-2.5">
                    <div class="flex items-center gap-1.5">
                        <span class="size-1.5 animate-pulse rounded-full bg-violet-400"></span>
                        <span class="size-1.5 animate-pulse rounded-full bg-violet-400 [animation-delay:150ms]"></span>
                        <span class="size-1.5 animate-pulse rounded-full bg-violet-400 [animation-delay:300ms]"></span>
                        <flux:text class="ml-1 text-[13px] text-violet-600">{{ __('Thinking…') }}</flux:text>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-zinc-200/80 bg-white px-4 py-2.5 dark:border-zinc-800">
        @if ($assistantMessages !== [])
            <div class="mb-2 flex flex-wrap gap-1">
                @foreach ($assistantContext['playbook']['suggested_prompts'] as $prompt)
                    <button
                        type="button"
                        wire:click="sendPrompt(@js($prompt))"
                        wire:loading.attr="disabled"
                        wire:target="submitAssistantPrompt, sendPrompt"
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium text-violet-600 transition hover:bg-violet-50 hover:text-violet-800 disabled:pointer-events-none disabled:opacity-50"
                    >
                        {{ $prompt }}
                    </button>
                @endforeach
            </div>
        @endif

        <form wire:submit="submitAssistantPrompt">
            <flux:composer
                wire:model="assistantInput"
                submit="enter"
                rows="1"
                placeholder="{{ __('Ask about this step...') }}"
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

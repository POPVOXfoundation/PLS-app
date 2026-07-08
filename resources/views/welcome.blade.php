<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('AI-PLS Bot')])
    </head>
    <body class="min-h-screen bg-[#f7f7f2] text-slate-950 antialiased">
        <header class="border-b border-slate-200 bg-white/95">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-4" aria-label="{{ __('AI-PLS Bot home') }}">
                    <img src="{{ asset('images/wfd-logo.svg') }}" alt="{{ __('Westminster Foundation for Democracy') }}" class="h-10 w-auto shrink-0 sm:h-12">
                    <span class="hidden h-8 w-px bg-slate-200 sm:block"></span>
                    <img src="{{ asset('images/pls.png') }}" alt="{{ __('AI-PLS Bot') }}" class="h-10 w-auto shrink-0 sm:h-12">
                </a>

                <nav class="flex shrink-0 items-center gap-2 text-sm font-medium">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-lg bg-slate-950 px-4 py-2 text-white transition hover:bg-slate-800">
                            {{ __('Open dashboard') }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-slate-800 transition hover:border-slate-500">
                            {{ __('Log in') }}
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            <section class="bg-white">
                <div class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-10 px-5 py-10 sm:px-6 lg:grid-cols-[1.08fr_0.92fr] lg:px-8 lg:py-14">
                    <div class="max-w-3xl">
                        <p class="mb-5 inline-flex rounded-lg border border-[#70a7a0]/40 bg-[#e7f2ef] px-3 py-1 text-sm font-semibold text-[#245f58]">
                            {{ __('Westminster Foundation for Democracy + POPVOX') }}
                        </p>

                        <h1 class="text-4xl font-semibold leading-tight text-slate-950 sm:text-5xl lg:text-6xl">
                            {{ __('AI support for practical post-legislative scrutiny') }}
                        </h1>

                        <p class="mt-6 max-w-2xl text-lg leading-8 text-slate-700">
                            {{ __('The AI-PLS Bot helps parliamentary teams organise a post-legislative scrutiny review, work through WFD methodology, analyse supporting documents, and draft grounded outputs while keeping human judgement in control.') }}
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            @auth
                                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    {{ __('Open the workspace') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                    {{ __('Log in to begin') }}
                                </a>
                            @endauth

                            <a href="https://www.wfd.org/accountability-and-transparency/post-legislative-scrutiny" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-800 transition hover:border-slate-500">
                                {{ __("Read WFD's PLS resources") }}
                            </a>
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-[#fbfbf7] p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold uppercase text-[#7a3f1b]">{{ __('Inside the bot') }}</p>
                                <h2 class="mt-2 text-2xl font-semibold text-slate-950">{{ __('A guided PLS review workspace') }}</h2>
                            </div>
                            <img src="{{ asset('images/PLS bot sq.png') }}" alt="" class="h-16 w-16 rounded-lg border border-slate-200 bg-white object-cover">
                        </div>

                        <ol class="mt-6 space-y-4 text-sm leading-6 text-slate-700">
                            <li class="flex gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#245f58] text-xs font-semibold text-white">1</span>
                                <span>{{ __('Create a review and define its scope, objectives, jurisdiction, and collaborators.') }}</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#245f58] text-xs font-semibold text-white">2</span>
                                <span>{{ __('Upload legislation, reports, consultation materials, and evidence for source-grounded assistance.') }}</span>
                            </li>
                            <li class="flex gap-3">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-[#245f58] text-xs font-semibold text-white">3</span>
                                <span>{{ __('Use tab-aware prompts to move from evidence to findings, recommendations, and report drafting.') }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section class="border-y border-slate-200 bg-[#f7f7f2]">
                <div class="mx-auto grid max-w-7xl gap-6 px-5 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <p class="text-sm font-semibold uppercase text-[#7a3f1b]">{{ __('What is PLS?') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ __('Checking whether laws work after adoption') }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-700">
                            {{ __('Post-legislative scrutiny is the practice of monitoring how laws are implemented and evaluating whether they achieve their intended impact for citizens. It helps parliaments learn what happened after a law passed and where follow-up may be needed.') }}
                        </p>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <p class="text-sm font-semibold uppercase text-[#7a3f1b]">{{ __('PLS methodology') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ __('A structured but flexible review process') }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-700">
                            {{ __("WFD's PLS manual sets out practical guidance for preparing, organising, and following up on PLS activities. The bot reflects the 11-step approach while allowing teams to enter the process where their review actually is.") }}
                        </p>
                    </article>

                    <article class="rounded-lg border border-slate-200 bg-white p-6">
                        <p class="text-sm font-semibold uppercase text-[#7a3f1b]">{{ __('Purpose of the bot') }}</p>
                        <h2 class="mt-3 text-2xl font-semibold text-slate-950">{{ __('Make expert guidance usable in daily work') }}</h2>
                        <p class="mt-4 text-sm leading-6 text-slate-700">
                            {{ __('The AI-PLS Bot turns WFD research, methodology, and user-supplied evidence into a practical workspace for parliamentary staff. It supports, but does not replace, the judgement of reviewers and committees.') }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="bg-white">
                <div class="mx-auto max-w-7xl px-5 py-12 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase text-[#245f58]">{{ __('How the AI-PLS Bot helps') }}</p>
                        <h2 class="mt-3 text-3xl font-semibold text-slate-950">{{ __('From source material to review-ready outputs') }}</h2>
                        <p class="mt-4 text-base leading-7 text-slate-700">
                            {{ __('The bot combines a structured PLS workflow with document analysis and a tab-aware assistant. It is designed for parliamentary staff who need to gather evidence, organise findings, and prepare draft materials without losing track of sources and limitations.') }}
                        </p>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-lg border border-slate-200 bg-[#fbfbf7] p-5">
                            <h3 class="font-semibold text-slate-950">{{ __('Workflow guidance') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ __('Move through scoping, legislation, evidence, stakeholders, consultations, analysis, and reports in one place.') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-[#fbfbf7] p-5">
                            <h3 class="font-semibold text-slate-950">{{ __('Document grounding') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ __('Upload review materials so assistant responses can be tied to the evidence available in the workspace.') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-[#fbfbf7] p-5">
                            <h3 class="font-semibold text-slate-950">{{ __('Drafting support') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ __('Generate provisional summaries, report structures, findings, and recommendations for human review.') }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-[#fbfbf7] p-5">
                            <h3 class="font-semibold text-slate-950">{{ __('Human control') }}</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-700">{{ __('Keep decisions, interpretations, and final publication authority with the parliamentary team.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-t border-slate-200 bg-slate-950 text-white">
                <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-10 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div class="max-w-2xl">
                        <h2 class="text-2xl font-semibold">{{ __('Built from WFD practice, tested with parliamentary users') }}</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-300">
                            {{ __('WFD has helped parliaments around the world pioneer post-legislative scrutiny. This prototype applies that experience to a practical AI-assisted workspace for real PLS reviews.') }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="https://www.wfd.org/sites/default/files/2023-06/wfd_pls_guide_pls_series_4_new.pdf" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-slate-100">
                            {{ __('Open the WFD PLS manual') }}
                        </a>
                        @auth
                            <a href="{{ route('pls.reviews.index') }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-5 py-3 text-sm font-semibold text-white transition hover:border-white">
                                {{ __('View PLS reviews') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-white/30 px-5 py-3 text-sm font-semibold text-white transition hover:border-white">
                                {{ __('Log in') }}
                            </a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>

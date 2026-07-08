<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('PLSAssist')])
        <style>
            .landing-page {
                min-height: 100vh;
                margin: 0;
                background: #f7f7f2;
                color: #07091d;
                font-family: "Instrument Sans", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            .landing-header {
                border-bottom: 1px solid #e5e7eb;
                background: rgba(255, 255, 255, 0.96);
            }

            .landing-shell {
                width: min(100% - 2rem, 1180px);
                margin: 0 auto;
            }

            .landing-header-inner {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1.25rem;
                padding: 1rem 0;
            }

            .app-brand,
            .wfd-brand {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                min-width: 0;
            }

            .app-brand {
                text-decoration: none;
                color: inherit;
            }

            .app-icon {
                width: 3rem;
                height: 3rem;
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
                object-fit: cover;
                background: #fff;
            }

            .app-name {
                display: block;
                font-size: 1.25rem;
                font-weight: 750;
                letter-spacing: 0;
                line-height: 1.1;
            }

            .app-subtitle,
            .wfd-label {
                display: block;
                margin-top: 0.2rem;
                color: #64748b;
                font-size: 0.78rem;
                font-weight: 600;
                line-height: 1.2;
            }

            .wfd-brand {
                justify-content: flex-end;
                text-align: right;
            }

            .wfd-logo {
                width: 7.5rem;
                height: auto;
            }

            .header-actions {
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 0.5rem;
                padding: 0.72rem 1rem;
                border: 1px solid #d7dee8;
                background: #fff;
                color: #10172a;
                font-size: 0.9rem;
                font-weight: 700;
                text-decoration: none;
                transition: border-color 0.16s ease, background 0.16s ease, color 0.16s ease;
            }

            .button:hover {
                border-color: #94a3b8;
            }

            .button-primary {
                border-color: #07091d;
                background: #07091d;
                color: #fff;
            }

            .button-primary:hover {
                background: #1a2038;
            }

            .hero {
                background: #fff;
            }

            .hero-grid {
                display: grid;
                grid-template-columns: minmax(0, 1.08fr) minmax(20rem, 0.92fr);
                align-items: center;
                gap: 4rem;
                min-height: calc(100vh - 5rem);
                padding: 4.5rem 0;
            }

            .eyebrow {
                display: inline-flex;
                align-items: center;
                border-radius: 0.5rem;
                border: 1px solid rgba(36, 95, 88, 0.22);
                background: #e7f2ef;
                color: #245f58;
                padding: 0.42rem 0.7rem;
                font-size: 0.86rem;
                font-weight: 800;
            }

            .hero h1,
            .section-heading {
                margin: 0;
                font-weight: 780;
                letter-spacing: 0;
                color: #07091d;
            }

            .hero h1 {
                margin-top: 1.4rem;
                max-width: 48rem;
                font-size: clamp(2.8rem, 6vw, 5.25rem);
                line-height: 1.02;
            }

            .hero-copy {
                max-width: 45rem;
                margin: 1.35rem 0 0;
                color: #475569;
                font-size: 1.13rem;
                line-height: 1.75;
            }

            .hero-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                margin-top: 2rem;
            }

            .partner-note {
                display: flex;
                align-items: center;
                gap: 0.85rem;
                max-width: 36rem;
                margin-top: 1.75rem;
                padding-top: 1.25rem;
                border-top: 1px solid #e5e7eb;
                color: #64748b;
                font-size: 0.92rem;
                line-height: 1.55;
            }

            .partner-note img {
                width: 6rem;
                height: auto;
                flex: 0 0 auto;
            }

            .bot-card {
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                background: #fbfbf7;
                padding: 1.55rem;
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            }

            .bot-card-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
            }

            .bot-card-label,
            .card-label,
            .feature-label {
                margin: 0;
                color: #7a3f1b;
                font-size: 0.78rem;
                font-weight: 850;
                letter-spacing: 0;
                text-transform: uppercase;
            }

            .bot-card h2 {
                margin: 0.5rem 0 0;
                color: #07091d;
                font-size: 1.55rem;
                line-height: 1.18;
            }

            .bot-card-icon {
                width: 4rem;
                height: 4rem;
                border-radius: 0.75rem;
                border: 1px solid #e5e7eb;
                object-fit: cover;
                background: #fff;
            }

            .bot-card ol {
                display: grid;
                gap: 1rem;
                margin: 1.5rem 0 0;
                padding: 0;
                list-style: none;
                color: #475569;
                font-size: 0.95rem;
                line-height: 1.55;
            }

            .bot-card li {
                display: flex;
                gap: 0.8rem;
            }

            .step-number {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.75rem;
                height: 1.75rem;
                flex: 0 0 auto;
                border-radius: 0.5rem;
                background: #245f58;
                color: #fff;
                font-size: 0.75rem;
                font-weight: 800;
            }

            .info-band {
                border-top: 1px solid #e5e7eb;
                border-bottom: 1px solid #e5e7eb;
                background: #f7f7f2;
                padding: 3rem 0;
            }

            .info-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 1.25rem;
            }

            .info-card,
            .feature-card {
                border: 1px solid #e5e7eb;
                border-radius: 0.75rem;
                background: #fff;
                padding: 1.55rem;
            }

            .info-card h2,
            .feature-card h3 {
                margin: 0.7rem 0 0;
                color: #07091d;
                font-size: 1.35rem;
                line-height: 1.2;
            }

            .info-card p,
            .feature-card p {
                margin: 0.95rem 0 0;
                color: #475569;
                font-size: 0.95rem;
                line-height: 1.65;
            }

            .features {
                background: #fff;
                padding: 3rem 0;
            }

            .section-heading {
                margin-top: 0.7rem;
                max-width: 44rem;
                font-size: clamp(2rem, 4vw, 3rem);
                line-height: 1.08;
            }

            .section-copy {
                max-width: 47rem;
                margin: 1rem 0 0;
                color: #475569;
                font-size: 1rem;
                line-height: 1.7;
            }

            .feature-grid {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 1rem;
                margin-top: 2rem;
            }

            .footer-cta {
                border-top: 1px solid #1f2937;
                background: #07091d;
                color: #fff;
            }

            .footer-inner {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 2rem;
                padding: 2.5rem 0;
            }

            .footer-cta h2 {
                margin: 0;
                font-size: 1.6rem;
                line-height: 1.2;
            }

            .footer-cta p {
                max-width: 44rem;
                margin: 0.85rem 0 0;
                color: #cbd5e1;
                font-size: 0.95rem;
                line-height: 1.65;
            }

            .footer-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.75rem;
                flex: 0 0 auto;
            }

            .footer-actions .button {
                border-color: rgba(255, 255, 255, 0.32);
                background: transparent;
                color: #fff;
            }

            .footer-actions .button-primary {
                border-color: #fff;
                background: #fff;
                color: #07091d;
            }

            @media (max-width: 900px) {
                .landing-header-inner,
                .footer-inner {
                    align-items: flex-start;
                    flex-direction: column;
                }

                .header-actions {
                    width: 100%;
                    justify-content: space-between;
                }

                .wfd-brand {
                    text-align: left;
                }

                .hero-grid,
                .info-grid,
                .feature-grid {
                    grid-template-columns: 1fr;
                }

                .hero-grid {
                    min-height: auto;
                    gap: 2rem;
                    padding: 3rem 0;
                }
            }

            @media (max-width: 560px) {
                .landing-shell {
                    width: min(100% - 1.25rem, 1180px);
                }

                .app-name {
                    font-size: 1.05rem;
                }

                .app-subtitle,
                .wfd-label {
                    font-size: 0.72rem;
                }

                .header-actions {
                    gap: 0.65rem;
                }

                .wfd-logo {
                    width: 6.4rem;
                }

                .hero h1 {
                    font-size: 2.55rem;
                }

                .hero-actions,
                .footer-actions {
                    flex-direction: column;
                    width: 100%;
                }
            }
        </style>
    </head>
    <body class="landing-page">
        <header class="landing-header">
            <div class="landing-shell landing-header-inner">
                <a href="{{ route('home') }}" class="app-brand" aria-label="{{ __('PLSAssist home') }}">
                    <img src="{{ asset('images/PLS bot sq.png') }}" alt="" class="app-icon">
                    <span>
                        <span class="app-name">{{ __('PLSAssist') }}</span>
                        <span class="app-subtitle">{{ __('AI workspace for post-legislative scrutiny') }}</span>
                    </span>
                </a>

                <div class="header-actions">
                    <div class="wfd-brand" aria-label="{{ __('Westminster Foundation for Democracy') }}">
                        <span>
                            <span class="wfd-label">{{ __('Built with methodology and resources from') }}</span>
                            <img src="{{ asset('images/wfd-logo.svg') }}" alt="{{ __('Westminster Foundation for Democracy') }}" class="wfd-logo">
                        </span>
                    </div>

                    <nav>
                        @auth
                            <a href="{{ route('dashboard') }}" class="button button-primary">
                                {{ __('Open dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="button">
                                {{ __('Log in') }}
                            </a>
                        @endauth
                    </nav>
                </div>
            </div>
        </header>

        <main>
            <section class="hero">
                <div class="landing-shell hero-grid">
                    <div>
                        <p class="eyebrow">{{ __('PLSAssist app') }}</p>

                        <h1>{{ __('PLSAssist helps parliaments review whether laws work after adoption') }}</h1>

                        <p class="hero-copy">
                            {{ __('PLSAssist is an AI-supported workspace for post-legislative scrutiny. It helps parliamentary teams organise a review, work through WFD methodology, analyse supporting documents, and draft grounded outputs while keeping human judgement in control.') }}
                        </p>

                        <div class="hero-actions">
                            @auth
                                <a href="{{ route('dashboard') }}" class="button button-primary">
                                    {{ __('Open the workspace') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="button button-primary">
                                    {{ __('Log in to begin') }}
                                </a>
                            @endauth

                            <a href="https://www.wfd.org/accountability-and-transparency/post-legislative-scrutiny" target="_blank" rel="noopener" class="button">
                                {{ __("Read WFD's PLS resources") }}
                            </a>
                        </div>

                        <div class="partner-note">
                            <img src="{{ asset('images/wfd-logo.svg') }}" alt="{{ __('Westminster Foundation for Democracy') }}">
                            <span>{{ __('WFD is the partner organisation and source of PLS expertise. PLSAssist is the app that turns WFD guidance and user-supplied evidence into a practical review workspace.') }}</span>
                        </div>
                    </div>

                    <div class="bot-card">
                        <div class="bot-card-header">
                            <div>
                                <p class="bot-card-label">{{ __('Inside PLSAssist') }}</p>
                                <h2>{{ __('A guided PLS review workspace') }}</h2>
                            </div>
                            <img src="{{ asset('images/PLS bot sq.png') }}" alt="" class="bot-card-icon">
                        </div>

                        <ol>
                            <li>
                                <span class="step-number">1</span>
                                <span>{{ __('Create a review and define its scope, objectives, jurisdiction, and collaborators.') }}</span>
                            </li>
                            <li>
                                <span class="step-number">2</span>
                                <span>{{ __('Upload legislation, reports, consultation materials, and evidence for source-grounded assistance.') }}</span>
                            </li>
                            <li>
                                <span class="step-number">3</span>
                                <span>{{ __('Use tab-aware prompts to move from evidence to findings, recommendations, and report drafting.') }}</span>
                            </li>
                        </ol>
                    </div>
                </div>
            </section>

            <section class="info-band">
                <div class="landing-shell info-grid">
                    <article class="info-card">
                        <p class="card-label">{{ __('What is PLS?') }}</p>
                        <h2>{{ __('Checking whether laws work after adoption') }}</h2>
                        <p>
                            {{ __('Post-legislative scrutiny is the practice of monitoring how laws are implemented and evaluating whether they achieve their intended impact for citizens. It helps parliaments learn what happened after a law passed and where follow-up may be needed.') }}
                        </p>
                    </article>

                    <article class="info-card">
                        <p class="card-label">{{ __('WFD methodology') }}</p>
                        <h2>{{ __('A structured but flexible review process') }}</h2>
                        <p>
                            {{ __("WFD's PLS manual sets out practical guidance for preparing, organising, and following up on PLS activities. PLSAssist reflects the 11-step approach while allowing teams to enter the process where their review actually is.") }}
                        </p>
                    </article>

                    <article class="info-card">
                        <p class="card-label">{{ __('Purpose of PLSAssist') }}</p>
                        <h2>{{ __('Make expert guidance usable in daily work') }}</h2>
                        <p>
                            {{ __('PLSAssist turns WFD research, methodology, and user-supplied evidence into a practical workspace for parliamentary staff. It supports, but does not replace, the judgement of reviewers and committees.') }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="features">
                <div class="landing-shell">
                    <p class="feature-label">{{ __('How PLSAssist helps') }}</p>
                    <h2 class="section-heading">{{ __('From source material to review-ready outputs') }}</h2>
                    <p class="section-copy">
                        {{ __('The app combines a structured PLS workflow with document analysis and a tab-aware assistant. It is designed for parliamentary staff who need to gather evidence, organise findings, and prepare draft materials without losing track of sources and limitations.') }}
                    </p>

                    <div class="feature-grid">
                        <div class="feature-card">
                            <h3>{{ __('Workflow guidance') }}</h3>
                            <p>{{ __('Move through scoping, legislation, evidence, stakeholders, consultations, analysis, and reports in one place.') }}</p>
                        </div>
                        <div class="feature-card">
                            <h3>{{ __('Document grounding') }}</h3>
                            <p>{{ __('Upload review materials so assistant responses can be tied to the evidence available in the workspace.') }}</p>
                        </div>
                        <div class="feature-card">
                            <h3>{{ __('Drafting support') }}</h3>
                            <p>{{ __('Generate provisional summaries, report structures, findings, and recommendations for human review.') }}</p>
                        </div>
                        <div class="feature-card">
                            <h3>{{ __('Human control') }}</h3>
                            <p>{{ __('Keep decisions, interpretations, and final publication authority with the parliamentary team.') }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="footer-cta">
                <div class="landing-shell footer-inner">
                    <div>
                        <h2>{{ __('PLSAssist is built from WFD practice and tested with parliamentary users') }}</h2>
                        <p>
                            {{ __('WFD has helped parliaments around the world pioneer post-legislative scrutiny. PLSAssist applies that experience to a practical AI-assisted workspace for real PLS reviews.') }}
                        </p>
                    </div>
                    <div class="footer-actions">
                        <a href="https://www.wfd.org/sites/default/files/2023-06/wfd_pls_guide_pls_series_4_new.pdf" target="_blank" rel="noopener" class="button button-primary">
                            {{ __('Open the WFD PLS manual') }}
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="button">
                                {{ __('Open dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="button">
                                {{ __('Log in') }}
                            </a>
                        @endauth
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>

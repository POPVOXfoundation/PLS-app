<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@voletStyles
<style>
    :root {
        --volet-primary: rgb(124 58 237);
        --volet-primary-hover: rgb(109 40 217);
        --volet-primary-light: rgb(196 181 253);
        --volet-text: rgb(63 63 70);
        --volet-header-text: white;
        --volet-background: white;
        --volet-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        --volet-shadow-large: 0 20px 25px -5px rgb(0 0 0 / 0.1);
        --volet-panel-width: 22rem;
    }
    .dark {
        --volet-primary: rgb(124 58 237);
        --volet-primary-hover: rgb(109 40 217);
        --volet-primary-light: rgb(196 181 253);
        --volet-text: rgb(228 228 231);
        --volet-header-text: white;
        --volet-background: rgb(24 24 27);
        --volet-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.3);
        --volet-shadow-large: 0 20px 25px -5px rgb(0 0 0 / 0.4);
    }
    #volet .volet-panel {
        border: 1px solid rgb(228 228 231);
        border-radius: 1rem;
    }
    .dark #volet .volet-panel {
        border-color: rgb(63 63 70);
    }
    #volet .volet-feature-button:hover,
    #volet .volet-feedback-category:hover {
        background-color: rgb(244 244 245);
    }
    .dark #volet .volet-feature-button:hover,
    .dark #volet .volet-feedback-category:hover {
        background-color: rgb(39 39 42);
    }
    #volet .volet-feedback-textarea {
        background-color: white;
        border-color: rgb(228 228 231);
        color: rgb(63 63 70);
    }
    .dark #volet .volet-feedback-textarea {
        background-color: rgb(39 39 42);
        border-color: rgb(63 63 70);
        color: rgb(228 228 231);
    }
    #volet .volet-feedback-textarea:focus {
        border-color: rgb(124 58 237);
        box-shadow: 0 0 0 1px rgb(124 58 237);
    }
</style>

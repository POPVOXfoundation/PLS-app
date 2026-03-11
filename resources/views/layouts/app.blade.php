<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="min-h-screen bg-zinc-100 px-4 py-4 sm:px-6 sm:py-6 lg:px-8 dark:bg-zinc-950">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>

{{--
    Livewire's page layout (`shell::app`, see config/livewire.php). Phase 10's
    generator is the only component rendered as a full page, so this just
    brands the shared shell for it.

    It lives under `shell/` rather than `layouts/` so the Livewire namespace
    can never shadow the `<x-layouts.*>` Blade components.
--}}
<x-layouts.app game="phase10" :title="$title ?? null">
    {{ $slot }}
</x-layouts.app>

{{--
    Chromeless wrapper for the scoring screen, same reasoning as Qwixx's
    room page — this is meant to sit on a table, not carry the site header.
    All the actual state (setup / host / spectator) lives in the embedded
    Livewire component so this view only has to pick the layout.
--}}
<x-layouts.fullscreen :title="$code ? 'Game '.$code.' — Phase 10' : 'Phase 10 — Score'" game="phase10">
    <livewire:phase10-play :code="$code" />
</x-layouts.fullscreen>

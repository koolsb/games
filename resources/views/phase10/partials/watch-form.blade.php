{{--
    The join box for anyone who is not the host. Rendered by every screen
    that mixes in App\Livewire\Concerns\WatchesScoreGames, so the code entry
    looks and behaves identically wherever it turns up.
--}}
<div class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
    <flux:icon.eye class="size-5 shrink-0 text-zinc-400" />
    <flux:text class="min-w-48 flex-1 text-sm">
        Someone else keeping score? Type their game code to follow the phases and scores live.
    </flux:text>

    <form class="flex items-start gap-2" wire:submit="watchGame">
        <flux:input
            wire:model="watchCode"
            placeholder="ABCD"
            size="sm"
            maxlength="{{ \App\Support\Phase\ScoreRoomCode::LENGTH }}"
            autocapitalize="characters"
            autocomplete="off"
            aria-label="Game code"
            class="w-28 text-center font-mono uppercase tracking-[0.3em]"
        />
        <flux:button type="submit" size="sm" variant="primary">Follow</flux:button>
    </form>

    @if ($watchError)
        <p class="w-full text-sm font-medium text-red-600 dark:text-red-400">{{ $watchError }}</p>
    @endif
</div>

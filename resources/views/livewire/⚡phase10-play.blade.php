<?php

declare(strict_types=1);

use App\Services\Phase\PhaseLibrary;
use App\Services\Phase\ScoreRoomStore;
use App\Support\Phase\Phase;
use App\Support\Phase\ScoreRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?string $code = null;

    public bool $isHost = false;

    /** @var list<string> */
    public array $setupNames = ['', ''];

    /** @var list<string> */
    public array $setupPhaseSignatures = [];

    /** @var array<string, array{score: string, completed: bool}> */
    public array $pending = [];

    public function mount(?string $code = null): void
    {
        $this->code = $code;

        if ($this->code === null) {
            $phases = request()->query('phases');

            if (is_string($phases) && $phases !== '') {
                $this->setupPhaseSignatures = array_values(array_filter(explode(',', $phases)));
            }

            return;
        }

        $room = $this->store()->find($this->code);

        if ($room === null) {
            return;
        }

        $this->isHost = $this->ownsRoom($room);

        if ($this->isHost) {
            $this->resetPending($room);
        }
    }

    public function addSetupName(): void
    {
        if (count($this->setupNames) < $this->store()->maxPlayers()) {
            $this->setupNames[] = '';
        }
    }

    public function removeSetupName(int $index): void
    {
        if (count($this->setupNames) <= 2) {
            return;
        }

        unset($this->setupNames[$index]);
        $this->setupNames = array_values($this->setupNames);
    }

    public function useClassicPhases(): void
    {
        $this->setupPhaseSignatures = app(PhaseLibrary::class)->classics()
            ->map(fn (Phase $p): string => $p->signature)
            ->all();
    }

    public function startScoring(): void
    {
        $signatures = $this->setupPhases->map(fn (Phase $p): string => $p->signature)->all();
        $names = $this->cleanedSetupNames();

        if ($signatures === [] || count($names) < 2) {
            return;
        }

        $room = $this->store()->create($signatures, $names);

        Cookie::queue(cookie(
            name: "phase10_host_{$room->code}",
            value: $room->hostToken,
            minutes: $this->store()->ttlMinutes(),
            httpOnly: true,
            sameSite: 'lax',
        ));

        $this->redirect(route('phase10.play', $room->code));
    }

    public function finalizeRound(): void
    {
        $room = $this->room;

        if ($room === null || ! $this->isHost || ! $this->ownsRoom($room) || ! $this->pendingReady($room)) {
            return;
        }

        $entries = [];

        foreach ($room->players as $player) {
            $entries[$player->id] = [
                'score' => (int) $this->pending[$player->id]['score'],
                'completed' => (bool) ($this->pending[$player->id]['completed'] ?? false),
            ];
        }

        $updated = $this->store()->mutate($room->code, fn (ScoreRoom $r): ScoreRoom => $r->addRound($entries));

        if ($updated !== null) {
            $this->resetPending($updated);
        }

        unset($this->room, $this->phases);
    }

    public function undoLastRound(): void
    {
        $room = $this->room;

        if ($room === null || ! $this->isHost || ! $this->ownsRoom($room)) {
            return;
        }

        $updated = $this->store()->mutate($room->code, fn (ScoreRoom $r): ScoreRoom => $r->undoLastRound());

        if ($updated !== null) {
            $this->resetPending($updated);
        }

        unset($this->room, $this->phases);
    }

    public function resolveTiebreak(string $playerId): void
    {
        $room = $this->room;

        if ($room === null || ! $this->isHost || ! $this->ownsRoom($room)) {
            return;
        }

        $this->store()->mutate($room->code, fn (ScoreRoom $r): ScoreRoom => $r->resolveTiebreak($playerId));

        unset($this->room, $this->phases);
    }

    #[Computed]
    public function room(): ?ScoreRoom
    {
        return $this->code ? $this->store()->find($this->code) : null;
    }

    /**
     * @return list<Phase>
     */
    #[Computed]
    public function phases(): array
    {
        $room = $this->room;

        if ($room === null) {
            return [];
        }

        $library = app(PhaseLibrary::class);

        return array_values(array_filter(array_map(
            fn (string $sig): ?Phase => $library->find($sig),
            $room->phases,
        )));
    }

    /**
     * @return Collection<int, Phase>
     */
    #[Computed]
    public function setupPhases(): Collection
    {
        $library = app(PhaseLibrary::class);

        return collect($this->setupPhaseSignatures)
            ->map(fn (string $sig): ?Phase => $library->find($sig))
            ->filter()
            ->values();
    }

    public function setupValid(): bool
    {
        return count($this->cleanedSetupNames()) >= 2 && $this->setupPhases->isNotEmpty();
    }

    public function pendingReady(?ScoreRoom $room = null): bool
    {
        $room ??= $this->room;

        if ($room === null || $this->pending === []) {
            return false;
        }

        foreach ($room->players as $player) {
            $score = $this->pending[$player->id]['score'] ?? '';

            if ($score === '' || ! ctype_digit((string) $score)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function cleanedSetupNames(): array
    {
        return array_values(array_filter(
            array_map(static fn (string $name): string => trim($name), $this->setupNames),
            static fn (string $name): bool => $name !== '',
        ));
    }

    private function ownsRoom(ScoreRoom $room): bool
    {
        $token = request()->cookie("phase10_host_{$room->code}");

        return is_string($token) && $token !== '' && hash_equals($room->hostToken, $token);
    }

    private function resetPending(ScoreRoom $room): void
    {
        $this->pending = [];

        foreach ($room->players as $player) {
            $this->pending[$player->id] = ['score' => '', 'completed' => false];
        }
    }

    public function store(): ScoreRoomStore
    {
        return app(ScoreRoomStore::class);
    }
}; ?>

<div class="flex h-full min-h-0 flex-col" x-data="{ scoresOpen: false }" x-cloak>
    @php($room = $this->code !== null ? $this->room : null)

    {{-- Setup: no code in the URL yet --}}
    @if ($this->code === null)
        <div class="flex items-center gap-2 px-4 py-2">
            <flux:button :href="route('phase10.generator')" variant="ghost" size="sm" icon="arrow-left">
                Phase 10
            </flux:button>
        </div>

        <div class="flex min-h-0 flex-1 items-center justify-center overflow-y-auto p-6">
            <div class="w-full max-w-lg space-y-6">
                <div class="space-y-1 text-center">
                    <flux:heading size="xl" class="font-black">Start scoring</flux:heading>
                    <flux:text>Name the players, pick the phases, then hand the tablet round.</flux:text>
                </div>

                <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:label>Players</flux:label>
                    @foreach ($setupNames as $i => $name)
                        <div class="flex items-center gap-2">
                            <flux:input wire:model.live="setupNames.{{ $i }}" placeholder="Player {{ $i + 1 }}" />
                            @if (count($setupNames) > 2)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeSetupName({{ $i }})" tooltip="Remove" />
                            @endif
                        </div>
                    @endforeach
                    @if (count($setupNames) < $this->store()->maxPlayers())
                        <flux:button size="sm" variant="subtle" icon="plus" wire:click="addSetupName">Add player</flux:button>
                    @endif
                </div>

                <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:label>Phases</flux:label>

                    @if ($this->setupPhases->isEmpty())
                        <flux:text class="text-sm">No phases picked yet.</flux:text>
                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="sparkles" wire:click="useClassicPhases">
                                Use the standard 10 phases
                            </flux:button>
                            <flux:button :href="route('phase10.generator')" variant="subtle" icon="adjustments-horizontal">
                                Build a custom game
                            </flux:button>
                        </div>
                    @else
                        <flux:text class="text-sm">{{ $this->setupPhases->count() }} phases ready.</flux:text>
                        <x-phase10.card :phases="$this->setupPhases" :show-bands="true" subtitle="Your game" />
                        <flux:button
                            :href="route('phase10.generator', ['phases' => implode(',', $this->setupPhaseSignatures)])"
                            variant="ghost"
                            size="sm"
                            icon="adjustments-horizontal"
                        >
                            Change phases
                        </flux:button>
                    @endif
                </div>

                <flux:button variant="primary" class="w-full" wire:click="startScoring" wire:loading.attr="disabled" :disabled="! $this->setupValid()">
                    Start scoring
                </flux:button>
            </div>
        </div>
    @elseif ($room === null)
        {{-- The code is not (or no longer) an active game --}}
        <div class="flex min-h-0 flex-1 items-center justify-center p-6">
            <div class="max-w-sm space-y-4 text-center">
                <flux:heading size="lg">This game is not here</flux:heading>
                <flux:text>
                    The code may be mistyped, or the game finished a while ago and was cleared.
                </flux:text>
                <flux:button :href="route('phase10.play')" variant="primary">Start a new game</flux:button>
            </div>
        </div>
    @else
        {{-- Playing: split screen on wide/landscape, toggled overlay on narrow/portrait.
             Spectators poll to stay live; the host already re-renders on every action. --}}
        <div @unless ($this->isHost) wire:poll.3s @endunless class="flex items-center justify-between gap-2 px-4 py-2">
            <flux:button :href="route('phase10.generator')" variant="ghost" size="sm" icon="arrow-left">
                Phase 10
            </flux:button>
            <span class="rounded-lg bg-zinc-900/5 px-2 py-0.5 font-mono text-sm font-black tracking-[0.3em] text-zinc-700 dark:bg-white/10 dark:text-zinc-200">
                {{ $room->code }}
            </span>
            <span class="text-xs font-semibold text-zinc-400">
                {{ $this->isHost ? 'Hosting' : 'Watching' }}
            </span>
        </div>

        <div class="grid min-h-0 flex-1 gap-3 p-3 lg:grid-cols-2 lg:gap-4 lg:p-4">
            <div class="flex min-h-0 items-center justify-center overflow-y-auto rounded-2xl">
                <x-phase10.card
                    :phases="$this->phases"
                    :players="$room->players"
                    :phase-count="$room->phaseCount()"
                    :show-bands="true"
                    size="lg"
                    subtitle="Game {{ $room->code }}"
                />
            </div>

            <div class="hidden min-h-0 lg:block">
                @include('phase10.partials.score-panel', ['room' => $room, 'readOnly' => ! $this->isHost])
            </div>
        </div>

        {{-- Narrow screens: the score panel is a full-screen overlay instead of a permanent pane --}}
        <button
            type="button"
            @click="scoresOpen = true"
            class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-zinc-900 px-5 py-3 font-semibold text-white shadow-lg lg:hidden dark:bg-zinc-100 dark:text-zinc-900"
        >
            <flux:icon.trophy class="size-4" />
            Scores
        </button>

        <div
            x-show="scoresOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @keydown.escape.window="scoresOpen = false"
            class="fixed inset-0 z-50 flex flex-col bg-zinc-100 p-3 lg:hidden dark:bg-zinc-950"
            style="display: none"
        >
            <button type="button" @click="scoresOpen = false" class="mb-2 self-end rounded-full bg-zinc-900/10 px-4 py-2 text-sm font-medium dark:bg-white/10">
                Close
            </button>
            @include('phase10.partials.score-panel', ['room' => $room, 'readOnly' => ! $this->isHost])
        </div>
    @endif
</div>

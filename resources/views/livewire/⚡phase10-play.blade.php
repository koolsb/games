<?php

declare(strict_types=1);

use App\Livewire\Concerns\WatchesScoreGames;
use App\Services\Phase\PhaseLibrary;
use App\Services\Phase\ScoreRoomStore;
use App\Support\Phase\Phase;
use App\Support\Phase\ScorePlayer;
use App\Support\Phase\ScoreRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use WatchesScoreGames;

    public ?string $code = null;

    public bool $isHost = false;

    /** @var list<string> */
    public array $setupNames = ['', ''];

    /** @var list<string> */
    public array $setupPhaseSignatures = [];

    public ?string $setupError = null;

    /**
     * The round the host is filling in. Scores start at '0' rather than
     * blank because most hands most players did not go out with cards in
     * hand worth nothing — but zero is still by far the most common single
     * entry, and typing it out for everyone who scored nothing is the sort
     * of busywork the paper pad never asked for.
     *
     * @var array<string, array{score: string, completed: bool}>
     */
    public array $pending = [];

    public function mount(?string $code = null): void
    {
        $this->code = $code;

        if ($this->code === null) {
            $this->setupPhaseSignatures = $this->queryList('phases');

            $names = $this->queryList('players');

            if (count($names) >= 2) {
                $this->setupNames = array_slice($names, 0, $this->store()->maxPlayers());
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

    /* ---------------------------------------------------------------- setup */

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

    /**
     * The generator is a separate screen, so the names typed here have to
     * travel with the link or they are lost the moment someone goes to
     * change the phases.
     */
    public function generatorUrl(): string
    {
        return route('phase10.generator', array_filter([
            'phases' => implode(',', $this->setupPhaseSignatures),
            'players' => implode(',', $this->cleanedSetupNames()),
        ]));
    }

    public function startScoring(): void
    {
        $signatures = $this->setupPhases->map(fn (Phase $p): string => $p->signature)->all();
        $names = $this->cleanedSetupNames();

        if ($signatures === []) {
            $this->setupError = 'Pick the phases you are playing first.';

            return;
        }

        if (count($names) < 2) {
            $this->setupError = 'Name at least two players.';

            return;
        }

        $this->redirect(route('phase10.play', $this->openGame($signatures, $names)->code));
    }

    /* ----------------------------------------------------------- scoring */

    public function bumpScore(string $playerId, int $delta): void
    {
        if (! isset($this->pending[$playerId])) {
            return;
        }

        $this->pending[$playerId]['score'] = (string) max(0, (int) $this->pending[$playerId]['score'] + $delta);
    }

    public function toggleCompleted(string $playerId): void
    {
        if (! isset($this->pending[$playerId])) {
            return;
        }

        $this->pending[$playerId]['completed'] = ! $this->pending[$playerId]['completed'];
    }

    /**
     * Keeps a typed score to something `addRound()` can take: digits only,
     * and an emptied box means zero rather than "unfinished".
     */
    public function updatedPending(mixed $value, ?string $key = null): void
    {
        if ($key === null || ! str_ends_with($key, '.score')) {
            return;
        }

        $id = substr($key, 0, -strlen('.score'));
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        $this->pending[$id]['score'] = (string) (int) ($digits === '' ? '0' : $digits);
    }

    public function finalizeRound(): void
    {
        $room = $this->room;

        if (! $this->canWrite($room) || $room->status !== ScoreRoom::ACTIVE) {
            return;
        }

        $entries = [];

        foreach ($room->players as $player) {
            $entries[$player->id] = [
                'score' => (int) ($this->pending[$player->id]['score'] ?? 0),
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

        if (! $this->canWrite($room) || $room->roundsPlayed() === 0) {
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

        if (! $this->canWrite($room) || $room->status !== ScoreRoom::TIEBREAK) {
            return;
        }

        $this->store()->mutate($room->code, fn (ScoreRoom $r): ScoreRoom => $r->resolveTiebreak($playerId));

        unset($this->room, $this->phases);
    }

    /**
     * Same table, same phases, fresh scores. A new game rather than a reset
     * of this one, so a finished game stays readable on its own link for
     * anyone still looking at it.
     */
    public function rematch(): void
    {
        $room = $this->room;

        if (! $this->canWrite($room)) {
            return;
        }

        $this->redirect(route('phase10.play', $this->openGame($room->phases, $room->playerNames())->code));
    }

    /* -------------------------------------------------------------- reads */

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

    public function shareUrl(): string
    {
        return route('phase10.play', $this->code);
    }

    /**
     * Who is ahead right now: furthest through the phases, lowest score
     * breaking a tie — the same order the game itself settles on.
     */
    public function leaderId(?ScoreRoom $room): ?string
    {
        if ($room === null || $room->players === [] || $room->roundsPlayed() === 0) {
            return null;
        }

        return collect($room->players)
            ->sortBy([
                fn (ScorePlayer $p): int => -$p->phasesCompleted(),
                fn (ScorePlayer $p): int => $p->totalScore(),
            ])
            ->first()?->id;
    }

    public function store(): ScoreRoomStore
    {
        return app(ScoreRoomStore::class);
    }

    /* ------------------------------------------------------------ private */

    /**
     * @param  list<string>  $signatures
     * @param  list<string>  $names
     */
    private function openGame(array $signatures, array $names): ScoreRoom
    {
        $room = $this->store()->create($signatures, $names);

        Cookie::queue(cookie(
            name: "phase10_host_{$room->code}",
            value: $room->hostToken,
            minutes: $this->store()->ttlMinutes(),
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $room;
    }

    /**
     * @phpstan-assert-if-true !null $room
     */
    private function canWrite(?ScoreRoom $room): bool
    {
        return $room !== null && $this->isHost && $this->ownsRoom($room);
    }

    /**
     * @return list<string>
     */
    private function queryList(string $key): array
    {
        $value = request()->query($key);

        return is_string($value) && $value !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $value))))
            : [];
    }

    /**
     * @return list<string>
     */
    private function cleanedSetupNames(): array
    {
        $max = $this->store()->nameMax();

        return array_slice(array_values(array_filter(
            array_map(static fn (string $name): string => mb_substr(trim($name), 0, $max), $this->setupNames),
            static fn (string $name): bool => $name !== '',
        )), 0, $this->store()->maxPlayers());
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
            $this->pending[$player->id] = ['score' => '0', 'completed' => false];
        }
    }
}; ?>

<div class="flex h-full min-h-0 flex-col">
    @php($room = $this->code !== null ? $this->room : null)

    {{-- Setup: no code in the URL yet --}}
    @if ($this->code === null)
        <div class="flex items-center gap-2 px-4 py-2">
            <flux:button :href="route('phase10.generator')" variant="ghost" size="sm" icon="arrow-left">
                Phase 10
            </flux:button>
        </div>

        <div class="flex min-h-0 flex-1 justify-center overflow-y-auto p-6">
            <div class="w-full max-w-lg space-y-6 pb-10">
                <div class="space-y-1 text-center">
                    <flux:heading size="xl" class="font-black">Keep score</flux:heading>
                    <flux:text>
                        Name the players and pick the phases. You'll get a code everyone else can
                        follow the game on from their own phone.
                    </flux:text>
                </div>

                {{-- Step 1 --}}
                <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-black text-white dark:bg-zinc-100 dark:text-zinc-900">1</span>
                        <flux:label>Who's playing?</flux:label>
                    </div>

                    @foreach ($setupNames as $i => $name)
                        <div class="flex items-center gap-2" wire:key="setup-name-{{ $i }}">
                            <flux:input
                                wire:model.live.debounce.400ms="setupNames.{{ $i }}"
                                placeholder="Player {{ $i + 1 }}"
                                maxlength="{{ $this->store()->nameMax() }}"
                                aria-label="Player {{ $i + 1 }} name"
                            />
                            @if (count($setupNames) > 2)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeSetupName({{ $i }})" tooltip="Remove player" />
                            @endif
                        </div>
                    @endforeach

                    @if (count($setupNames) < $this->store()->maxPlayers())
                        <flux:button size="sm" variant="subtle" icon="plus" wire:click="addSetupName">Add player</flux:button>
                    @endif
                </div>

                {{-- Step 2 --}}
                <div class="space-y-3 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-900 text-xs font-black text-white dark:bg-zinc-100 dark:text-zinc-900">2</span>
                        <flux:label>Which phases?</flux:label>
                        @if ($this->setupPhases->isNotEmpty())
                            <flux:badge size="sm" class="ml-auto">{{ $this->setupPhases->count() }} ready</flux:badge>
                        @endif
                    </div>

                    @if ($this->setupPhases->isEmpty())
                        <div class="flex flex-wrap gap-2">
                            <flux:button variant="primary" icon="rectangle-stack" wire:click="useClassicPhases">
                                Use the standard 10
                            </flux:button>
                            <flux:button :href="$this->generatorUrl()" variant="subtle" icon="sparkles">
                                Generate a custom game
                            </flux:button>
                        </div>
                    @else
                        <x-phase10.card :phases="$this->setupPhases" :show-bands="true" subtitle="Your game" />
                        <flux:button :href="$this->generatorUrl()" variant="ghost" size="sm" icon="adjustments-horizontal">
                            Change phases
                        </flux:button>
                    @endif
                </div>

                @if ($setupError)
                    <p class="text-center text-sm font-medium text-red-600 dark:text-red-400">{{ $setupError }}</p>
                @endif

                <flux:button
                    variant="primary"
                    class="w-full"
                    icon-trailing="arrow-right"
                    wire:click="startScoring"
                    wire:loading.attr="disabled"
                >
                    Start the game
                </flux:button>

                @include('phase10.partials.watch-form')
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
        @php($rounds = $room->roundsPlayed())

        {{--
            The card and the scores sit side by side on a tablet; on a phone
            they are two tabs of the same screen, so each pane is rendered
            once and `wide` decides whether the tab state applies at all.
        --}}
        <div
            x-cloak
            class="flex h-full min-h-0 flex-col"
            x-data="{
                pane: @js($this->isHost ? 'scores' : 'card'),
                wide: window.matchMedia('(min-width: 1024px)').matches,
                init() {
                    window.matchMedia('(min-width: 1024px)')
                        .addEventListener('change', (e) => { this.wide = e.matches });
                },
            }"
            @if (! $this->isHost) wire:poll.{{ $room->status === 'active' ? '3s' : '15s' }} @endif
        >
            <div class="flex items-center gap-2 px-3 py-2 sm:px-4">
                <flux:button :href="route('phase10.generator')" variant="ghost" size="sm" icon="arrow-left" class="shrink-0">
                    <span class="sr-only sm:not-sr-only">Phase 10</span>
                </flux:button>

                {{-- Phone tabs --}}
                <div class="flex rounded-lg bg-zinc-900/5 p-0.5 text-sm font-semibold lg:hidden dark:bg-white/10">
                    <button
                        type="button"
                        @click="pane = 'card'"
                        :class="pane === 'card' ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-500'"
                        class="rounded-md px-3 py-1"
                    >Phases</button>
                    <button
                        type="button"
                        @click="pane = 'scores'"
                        :class="pane === 'scores' ? 'bg-white shadow-sm dark:bg-zinc-700' : 'text-zinc-500'"
                        class="rounded-md px-3 py-1"
                    >Scores</button>
                </div>

                <div class="ml-auto flex shrink-0 items-center gap-2">
                    <span class="hidden text-xs font-semibold text-zinc-400 sm:inline">
                        {{ $this->isHost ? 'You are keeping score' : 'Following along' }}
                    </span>
                    <flux:modal.trigger name="share-game">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg bg-zinc-900/5 px-2.5 py-1 font-mono text-sm font-black tracking-[0.25em] text-zinc-700 transition hover:bg-zinc-900/10 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/20"
                            title="Share this game"
                        >
                            {{ $room->code }}
                            <flux:icon.share class="size-3.5 shrink-0 tracking-normal text-zinc-400" />
                        </button>
                    </flux:modal.trigger>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 gap-3 p-3 pt-0 lg:grid-cols-2 lg:gap-4 lg:p-4 lg:pt-0">
                <div class="min-h-0 justify-center overflow-y-auto" :class="(wide || pane === 'card') ? 'flex' : 'hidden'">
                    <x-phase10.card
                        :phases="$this->phases"
                        :players="$room->players"
                        :phase-count="$room->phaseCount()"
                        :show-bands="true"
                        size="lg"
                        class="self-start"
                        subtitle="Game {{ $room->code }}"
                    />
                </div>

                <div class="min-h-0" :class="(wide || pane === 'scores') ? 'block' : 'hidden'">
                    @include('phase10.partials.score-panel', [
                        'room' => $room,
                        'rounds' => $rounds,
                        'readOnly' => ! $this->isHost,
                        'leaderId' => $this->leaderId($room),
                    ])
                </div>
            </div>
        </div>

        <flux:modal name="share-game" class="min-w-[22rem]">
            {{--
                Same share sheet Qwixx rooms use. `navigator.clipboard` is
                refused outside a secure context, so the link is on screen as
                selectable text and a refusal says so rather than leaving the
                button looking broken.
            --}}
            <div
                class="space-y-4"
                x-data="{
                    copied: false,
                    failed: false,
                    async copy() {
                        try {
                            await navigator.clipboard.writeText('{{ $this->shareUrl() }}');
                            this.copied = true;
                            this.failed = false;
                            setTimeout(() => { this.copied = false }, 2000);
                        } catch {
                            this.failed = true;
                        }
                    },
                }"
            >
                <div class="space-y-1">
                    <flux:heading size="lg">Follow this game</flux:heading>
                    <flux:text>
                        Anyone with the code sees the phases and the scores as they're entered — no app,
                        no name to type. Only this device can enter scores.
                    </flux:text>
                </div>

                <div class="rounded-xl bg-zinc-100 py-5 text-center dark:bg-zinc-800">
                    <div class="font-mono text-4xl font-black tracking-[0.35em] text-zinc-900 dark:text-zinc-50">
                        {{ $room->code }}
                    </div>
                    <div class="mt-1 text-xs font-medium text-zinc-500">
                        Enter it at {{ parse_url(config('app.url'), PHP_URL_HOST) }}/phase10
                    </div>
                </div>

                <flux:input readonly value="{{ $this->shareUrl() }}" x-on:focus="$el.select()" aria-label="Link to this game" />

                <flux:button variant="primary" class="w-full" icon="clipboard" x-on:click="copy()">
                    <span x-text="copied ? 'Copied!' : 'Copy link'">Copy link</span>
                </flux:button>

                <flux:text class="text-center text-sm" x-show="failed" x-cloak>
                    This browser wouldn't let the page copy — tap the link above to select it.
                </flux:text>
            </div>
        </flux:modal>
    @endif
</div>

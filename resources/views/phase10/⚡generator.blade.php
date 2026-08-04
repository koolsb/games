<?php

declare(strict_types=1);

use App\Enums\Phase\DifficultyBand;
use App\Enums\Phase\GenerationMode;
use App\Livewire\Concerns\WatchesScoreGames;
use App\Services\Phase\PhaseGeneratorService;
use App\Services\Phase\PhaseLibrary;
use App\Support\Phase\GeneratedGame;
use App\Support\Phase\GenerationOptions;
use App\Support\Phase\Phase;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    use WatchesScoreGames;

    public int $count = 10;

    public string $mode = 'ramp';

    public string $band = 'medium';

    /** @var array<int, string> */
    public array $slotBands = [];

    public ?int $seed = null;

    /** @var list<string> ordered phase signatures = the current game */
    public array $signatures = [];

    /** @var list<int> */
    public array $widenedSlots = [];

    public bool $generated = false;

    /** @var array{0: int, 1: int} Indices into DifficultyBand::ordered() — 0=Easy, 3=Brutal */
    public array $difficultyRange = [0, 3];

    /**
     * Player names passed through from the scoring setup screen. This page
     * is a detour on the way to a game, so the names typed there ride along
     * in the URL and go straight back — otherwise "change the phases" costs
     * you everyone's name.
     *
     * @var list<string>
     */
    public array $carriedPlayers = [];

    public function mount(PhaseLibrary $library): void
    {
        $players = request()->query('players');

        if (is_string($players) && $players !== '') {
            $this->carriedPlayers = array_values(array_filter(array_map('trim', explode(',', $players))));
        }

        $phases = request()->query('phases');

        if (is_string($phases) && $phases !== '') {
            $signatures = array_values(array_filter(
                explode(',', $phases),
                static fn (string $sig): bool => $library->find($sig) !== null,
            ));

            if ($signatures !== []) {
                $this->signatures = $signatures;
                $this->count = count($signatures);
                $this->generated = true;
            }
        }

        $this->syncSlotBands();
    }

    public function updatedMode(): void
    {
        $this->syncSlotBands();
    }

    public function updatedCount(): void
    {
        $this->count = max(1, min(20, (int) $this->count));
        $this->syncSlotBands();
    }

    public function generate(PhaseGeneratorService $generator, PhaseLibrary $library): void
    {
        $this->applyGame($generator->generateGame($library->all(), $this->options()));
    }

    public function useClassics(PhaseLibrary $library): void
    {
        $this->applyGame(new GeneratedGame($library->classics()->all(), seed: null, mode: GenerationMode::from($this->mode)));
        $this->count = count($this->signatures);
        $this->syncSlotBands();
    }

    public function generateOne(PhaseGeneratorService $generator, PhaseLibrary $library): void
    {
        $band = $this->mode === GenerationMode::FLAT->value ? DifficultyBand::from($this->band) : null;
        [$minBand, $maxBand] = $this->bandRange();

        $pool = $library->all()
            ->reject(fn (Phase $p): bool => in_array($p->signature, $this->signatures, true))
            ->values();

        if ($pool->isEmpty()) {
            $pool = $library->all();
        }

        $phase = $generator->generateOne($pool, new GenerationOptions(count: 1, mode: GenerationMode::from($this->mode), band: $band, minBand: $minBand, maxBand: $maxBand));

        $this->signatures[] = $phase->signature;
        $this->count = count($this->signatures);
        $this->generated = true;
        $this->syncSlotBands();
    }

    public function regenerate(int $index, PhaseGeneratorService $generator, PhaseLibrary $library): void
    {
        $game = $this->currentGame($library);

        if ($game === null) {
            return;
        }

        $this->applyGame($generator->regenerateSlot($library->all(), $game, $index, $this->options()));
    }

    public function remove(int $index): void
    {
        unset($this->signatures[$index]);
        $this->signatures = array_values($this->signatures);
        $this->count = max(1, count($this->signatures));
        $this->widenedSlots = [];
        $this->syncSlotBands();
    }

    public function clear(): void
    {
        $this->signatures = [];
        $this->widenedSlots = [];
        $this->seed = null;
        $this->generated = false;
    }

    public function printUrl(): string
    {
        return route('phase10.print', ['phases' => implode(',', $this->signatures)]);
    }

    public function scoreUrl(): string
    {
        return route('phase10.play', array_filter([
            'phases' => implode(',', $this->signatures),
            'players' => implode(',', $this->carriedPlayers),
        ]));
    }

    #[Computed]
    public function phases(): array
    {
        $library = app(PhaseLibrary::class);

        return array_values(array_filter(array_map(
            fn (string $sig): ?Phase => $library->find($sig),
            $this->signatures,
        )));
    }

    #[Computed]
    public function bandCounts(): array
    {
        return app(PhaseLibrary::class)->bandCounts();
    }

    private function options(): GenerationOptions
    {
        [$minBand, $maxBand] = $this->bandRange();

        return new GenerationOptions(
            count: $this->count,
            mode: GenerationMode::from($this->mode),
            band: DifficultyBand::from($this->band),
            slotBands: array_map(
                static fn (string $b): DifficultyBand => DifficultyBand::from($b),
                array_values($this->slotBands),
            ),
            minBand: $minBand,
            maxBand: $maxBand,
        );
    }

    /** @return array{?DifficultyBand, ?DifficultyBand} */
    private function bandRange(): array
    {
        $bands = DifficultyBand::ordered();

        return [
            $bands[$this->difficultyRange[0]] ?? null,
            $bands[$this->difficultyRange[1]] ?? null,
        ];
    }

    private function currentGame(PhaseLibrary $library): ?GeneratedGame
    {
        if ($this->signatures === []) {
            return null;
        }

        $phases = array_values(array_filter(array_map(
            fn (string $sig): ?Phase => $library->find($sig),
            $this->signatures,
        )));

        return new GeneratedGame($phases, $this->seed, GenerationMode::from($this->mode), $this->widenedSlots);
    }

    private function applyGame(GeneratedGame $game): void
    {
        $this->signatures = array_map(static fn (Phase $p): string => $p->signature, $game->phases);
        $this->widenedSlots = $game->widenedSlots;
        $this->seed = $game->seed;
        $this->generated = true;
    }

    private function syncSlotBands(): void
    {
        $out = [];

        for ($i = 0; $i < $this->count; $i++) {
            $out[$i] = $this->slotBands[$i] ?? 'medium';
        }

        $this->slotBands = $out;
    }

    public function with(): array
    {
        return [
            'modes' => GenerationMode::cases(),
            'bandOptions' => DifficultyBand::ordered(),
        ];
    }
}; ?>

<div
    x-data="{
        fullscreen: false,
        wlActive: false,
        wlLock: null,
        wlSupported: 'wakeLock' in navigator,
        init() {
            document.addEventListener('visibilitychange', async () => {
                if (document.visibilityState === 'visible' && this.wlActive && !this.wlLock) {
                    await this.wlAcquire();
                }
            });
        },
        async wlAcquire() {
            try {
                this.wlLock = await navigator.wakeLock.request('screen');
                this.wlLock.addEventListener('release', () => { this.wlLock = null; this.wlActive = false; });
                this.wlActive = true;
            } catch(e) { this.wlActive = false; }
        },
        async wlToggle() {
            if (!this.wlSupported) return;
            if (this.wlActive) {
                await this.wlLock?.release();
                this.wlLock = null;
                this.wlActive = false;
            } else {
                await this.wlAcquire();
            }
        },
        async closeFullscreen() {
            if (this.wlActive) { await this.wlLock?.release(); this.wlLock = null; this.wlActive = false; }
            this.fullscreen = false;
        }
    }"
    class="space-y-8"
>
    <div class="space-y-2">
        <flux:heading size="xl" class="font-black">Generate a Phase 10 game</flux:heading>
        <flux:text>
            Build a fresh set of phases to keep the game interesting. Pick how difficulty should flow,
            how many phases you want, then generate and tweak — and when you're happy, print the card
            or keep score on it right here.
        </flux:text>
        <div class="flex flex-wrap gap-2 pt-1">
            @foreach ($bandOptions as $b)
                <flux:badge size="sm" :color="$b->color()">
                    {{ $b->label() }}: {{ $this->bandCounts[$b->value] ?? 0 }}
                </flux:badge>
            @endforeach
        </div>
    </div>

    @include('phase10.partials.watch-form')

    {{-- Controls --}}
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <flux:field>
                <flux:label>Difficulty mode</flux:label>
                <flux:radio.group wire:model.live="mode" variant="cards" class="max-sm:flex-col">
                    @foreach ($modes as $m)
                        <flux:radio value="{{ $m->value }}" label="{{ $m->label() }}" description="{{ $m->description() }}" />
                    @endforeach
                </flux:radio.group>
            </flux:field>

            <flux:field>
                <flux:label>How many phases?</flux:label>
                <flux:input type="number" wire:model.live="count" min="1" max="20" />
                <flux:description>Default is 10, like the original game.</flux:description>
            </flux:field>
        </div>

        {{-- Flat band picker --}}
        @if ($mode === 'flat')
            <div class="mt-5">
                <flux:field>
                    <flux:label>Band for every phase</flux:label>
                    <flux:select wire:model.live="band" class="max-w-xs">
                        @foreach ($bandOptions as $b)
                            <flux:select.option value="{{ $b->value }}">{{ $b->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
            </div>
        @endif

        {{-- Manual per-slot bands --}}
        @if ($mode === 'manual')
            <div class="mt-5">
                <flux:label>Difficulty per phase</flux:label>
                <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    @for ($i = 0; $i < $count; $i++)
                        <flux:select wire:model="slotBands.{{ $i }}" size="sm" :label="'Phase '.($i + 1)">
                            @foreach ($bandOptions as $b)
                                <flux:select.option value="{{ $b->value }}">{{ $b->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endfor
                </div>
            </div>
        @endif

        {{-- Difficulty range --}}
        <div class="mt-5">
            <flux:field>
                <flux:label>Difficulty range</flux:label>
                <div class="mt-3 px-1">
                    <flux:slider range wire:model.live="difficultyRange" min="0" max="3" step="1" />
                    <div class="mt-1.5 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        @foreach ($bandOptions as $b)
                            <span>{{ $b->label() }}</span>
                        @endforeach
                    </div>
                </div>
                <flux:description>Constrain which difficulty bands the generator can draw from.</flux:description>
            </flux:field>
        </div>

        <flux:separator class="my-6" />

        {{-- Building the list. What you do *with* a finished list lives with
             the results below, so this row only ever holds one job. --}}
        <div class="flex flex-wrap items-center gap-3">
            <flux:button variant="primary" icon="sparkles" wire:click="generate" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="generate">Generate {{ $count }} {{ \Illuminate\Support\Str::plural('phase', $count) }}</span>
                <span wire:loading wire:target="generate">Generating…</span>
            </flux:button>

            <flux:button variant="subtle" icon="plus" wire:click="generateOne">Add one phase</flux:button>

            <flux:button variant="ghost" icon="rectangle-stack" wire:click="useClassics">
                Use the standard 10 phases
            </flux:button>

            @if ($generated && count($this->phases))
                <flux:button variant="ghost" icon="trash" wire:click="clear">Clear</flux:button>
            @endif

            @if ($seed)
                <flux:text class="ml-auto text-xs text-zinc-400">seed #{{ $seed }}</flux:text>
            @endif
        </div>
    </div>

    {{-- Results --}}
    @if (count($this->phases))
        {{-- What to do with the game you just built, in the order people
             want it: play it, print it, or blow it up on the table. --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <flux:text class="min-w-40 flex-1 text-sm">
                <span class="font-semibold text-zinc-800 dark:text-zinc-100">{{ count($this->phases) }} phases ready.</span>
                Keep score on this device and everyone else can follow along.
            </flux:text>
            <flux:button :href="$this->scoreUrl()" variant="primary" icon="trophy">
                Keep score
            </flux:button>
            <flux:button :href="$this->printUrl()" target="_blank" variant="subtle" icon="printer">
                Print card
            </flux:button>
            <flux:button @click="fullscreen = true" variant="subtle" icon="arrows-pointing-out">
                Full screen
            </flux:button>
        </div>

        @if (count($this->widenedSlots))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.text>
                    Some slots were filled from a nearby difficulty band because the requested band ran out of
                    distinct phases. Add more phases in <code>config/phases.php</code> to widen the pool.
                </flux:callout.text>
            </flux:callout>
        @endif

        <div class="grid gap-8 lg:grid-cols-2">
            {{-- Editable list --}}
            <div class="space-y-3">
                <flux:heading size="lg">Your phases</flux:heading>
                <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                    @foreach ($this->phases as $i => $phase)
                        <li class="flex items-center gap-3 bg-white px-4 py-3 dark:bg-zinc-900" wire:key="slot-{{ $i }}-{{ $phase->signature }}">
                            <span class="w-6 text-right font-bold text-zinc-400 tabular-nums">{{ $i + 1 }}</span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold text-zinc-800 dark:text-zinc-100">{{ $phase->label }}</div>
                                <div class="text-xs text-zinc-400">score {{ number_format($phase->score, 1) }}</div>
                            </div>
                            <flux:badge size="sm" :color="$phase->band->color()">{{ $phase->band->label() }}</flux:badge>
                            <flux:button size="sm" variant="subtle" icon="arrow-path" wire:click="regenerate({{ $i }})" tooltip="Regenerate" />
                            <flux:button size="sm" variant="subtle" icon="x-mark" wire:click="remove({{ $i }})" tooltip="Remove" />
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Card preview --}}
            <div class="space-y-3">
                <flux:heading size="lg">Card preview</flux:heading>
                <x-phase10.card :phases="$this->phases" :show-bands="true" subtitle="Custom game" />
            </div>
        </div>
    @else
        <div class="rounded-xl border border-dashed border-zinc-300 px-6 py-16 text-center dark:border-zinc-700">
            <flux:icon name="sparkles" class="mx-auto mb-3 size-8 text-zinc-300" />
            <flux:text>No phases yet — hit <span class="font-semibold">Generate</span> to build a game.</flux:text>
            <flux:text class="mt-4 text-sm">
                Playing the phases off the real card?
                <a href="{{ route('phase10.play') }}" class="font-semibold text-accent-content underline">Just keep score</a>.
            </flux:text>
        </div>
    @endif

    {{-- Fullscreen overlay — Alpine-controlled, no navigation, state is never lost --}}
    <div
        x-show="fullscreen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @keydown.escape.window="closeFullscreen()"
        class="fixed inset-0 z-50 overflow-y-auto bg-phase-blue-deep"
        style="display: none"
    >
        {{-- Controls bar --}}
        <div class="fixed left-0 right-0 top-0 z-10 flex items-center justify-between px-5 py-4">
            <button
                type="button"
                @click="closeFullscreen()"
                class="flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white/70 backdrop-blur-sm transition hover:bg-white/20 hover:text-white"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Close
            </button>

            <button
                type="button"
                @click="wlToggle()"
                :title="wlActive ? 'Screen is awake' : 'Keep screen awake'"
                :class="wlActive ? 'bg-white/25 text-white' : 'bg-white/10 text-white/70'"
                class="flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium backdrop-blur-sm transition hover:bg-white/20 hover:text-white"
            >
                <svg x-show="!wlActive" xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 26 26" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75 9.75 9.75 0 0 1 8.25 6a9.72 9.72 0 0 1 .752-3.752 9.75 9.75 0 0 0-10.978 10.44 9.75 9.75 0 0 0 10.296 8.802A9.754 9.754 0 0 0 21.752 15.002Z" />
                </svg>
                <svg x-show="wlActive" xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 26 26" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                <span x-text="wlActive ? 'Screen awake' : 'Keep awake'"></span>
            </button>
        </div>

        {{-- Card --}}
        <div class="flex min-h-screen items-center justify-center px-3 pb-4 pt-16 sm:px-6 sm:pb-6 sm:pt-20">
            <div class="w-full max-w-4xl">
                <x-phase10.card :phases="$this->phases" title="Phase 10" :show-bands="true" size="xl" />
            </div>
        </div>
    </div>
</div>

<?php

declare(strict_types=1);

namespace App\Services\Phase;

use App\Support\Phase\ScoreRoom;
use App\Support\Phase\ScoreRoomCode;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only thing in the app that knows where scoring games live.
 *
 * Games sit in the cache under a sliding TTL that every write refreshes, so
 * a game the table walks away from disappears by itself. Writes run inside
 * a per-game lock — the host mutates via Livewire, but a double-tap ("New
 * game" clicked twice) still races against itself without it.
 */
final class ScoreRoomStore
{
    /**
     * @param  array<string, mixed>  $config  the `scoring` block of config/phases.php
     */
    public function __construct(private readonly array $config = []) {}

    public function maxPlayers(): int
    {
        return (int) ($this->config['max_players'] ?? 8);
    }

    public function nameMax(): int
    {
        return (int) ($this->config['name_max'] ?? 20);
    }

    /**
     * How long the host's cookie should live — matches the game's own TTL,
     * since there is no point outliving the record it authorizes writes to.
     */
    public function ttlMinutes(): int
    {
        return (int) ($this->ttl() / 60);
    }

    public function find(string $code): ?ScoreRoom
    {
        $data = $this->cache()->get($this->key($code));

        return is_array($data) ? ScoreRoom::fromArray($data) : null;
    }

    /**
     * Opens a game on a code nobody is using. Codes are short on purpose,
     * so collisions are expected rather than exceptional — retry until one
     * is free instead of trusting randomness.
     *
     * @param  list<string>  $phaseSignatures
     * @param  list<string>  $playerNames
     */
    public function create(array $phaseSignatures, array $playerNames): ScoreRoom
    {
        foreach (range(1, 20) as $ignored) {
            $code = ScoreRoomCode::generate();
            $room = ScoreRoom::open($code, Str::random(40), $phaseSignatures, $playerNames);

            if ($this->cache()->add($this->key($code), $room->toArray(), $this->ttl())) {
                return $room;
            }
        }

        throw new RuntimeException('Could not allocate a free Phase 10 game code.');
    }

    /**
     * Runs $mutate against the current game and stores whatever it returns.
     *
     * @param  Closure(ScoreRoom): ScoreRoom  $mutate
     */
    public function mutate(string $code, Closure $mutate): ?ScoreRoom
    {
        $lock = $this->cache()->lock($this->key($code).':lock', 5);

        return $lock->block(3, function () use ($code, $mutate): ?ScoreRoom {
            $room = $this->find($code);

            if (! $room instanceof ScoreRoom) {
                return null;
            }

            $next = $mutate($room);
            $this->put($next);

            return $next;
        });
    }

    public function put(ScoreRoom $room): void
    {
        $this->cache()->put($this->key($room->code), $room->toArray(), $this->ttl());
    }

    public function forget(string $code): void
    {
        $this->cache()->forget($this->key($code));
    }

    private function cache(): Repository
    {
        return Cache::store($this->config['store'] ?? 'file');
    }

    private function key(string $code): string
    {
        return 'phase10:score:'.$code;
    }

    private function ttl(): int
    {
        return (int) ($this->config['ttl_hours'] ?? 48) * 3600;
    }
}

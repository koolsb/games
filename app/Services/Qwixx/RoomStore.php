<?php

declare(strict_types=1);

namespace App\Services\Qwixx;

use App\Support\Qwixx\Room;
use App\Support\Qwixx\RoomCode;
use App\Support\Qwixx\RoomPlayer;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The only thing in the app that knows where multiplayer rooms live.
 *
 * Rooms sit in the cache under a sliding TTL that every write refreshes, so
 * a game that people walk away from disappears by itself. Writes run inside
 * a per-room lock: several players sync at once, and each one rewrites the
 * whole record, so without the lock the slowest response would win.
 */
final class RoomStore
{
    /**
     * @param  array<string, mixed>  $config  the `multiplayer` block of config/qwixx.php
     */
    public function __construct(private readonly array $config = []) {}

    public function maxPlayers(): int
    {
        return (int) ($this->config['max_players'] ?? 8);
    }

    public function nameMax(): int
    {
        return (int) ($this->config['name_max'] ?? 14);
    }

    public function find(string $code): ?Room
    {
        $data = $this->cache()->get($this->key($code));

        return is_array($data) ? Room::fromArray($data) : null;
    }

    /**
     * Opens a room on a code nobody is using. Codes are short on purpose, so
     * collisions are expected rather than exceptional — retry until one is
     * free instead of trusting randomness.
     */
    public function create(string $layoutId, string $hostName): Room
    {
        foreach (range(1, 20) as $ignored) {
            $code = RoomCode::generate();
            $room = Room::open($code, $layoutId, RoomPlayer::make($hostName, isHost: true));

            if ($this->cache()->add($this->key($code), $room->toArray(), $this->ttl())) {
                return $room;
            }
        }

        throw new RuntimeException('Could not allocate a free Qwixx room code.');
    }

    /**
     * Runs $mutate against the current room and stores whatever it returns.
     * Returning null leaves the room untouched (a poll that changed nothing).
     *
     * @param  Closure(Room): ?Room  $mutate
     */
    public function mutate(string $code, Closure $mutate): ?Room
    {
        $lock = $this->cache()->lock($this->key($code).':lock', 5);

        return $lock->block(3, function () use ($code, $mutate): ?Room {
            $room = $this->find($code);

            if (! $room instanceof Room) {
                return null;
            }

            $next = $mutate($room);

            if (! $next instanceof Room) {
                // No change, but someone is clearly still playing: keep the
                // room alive by refreshing its TTL.
                $this->put($room);

                return $room;
            }

            $this->put($next);

            return $next;
        });
    }

    public function put(Room $room): void
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
        return 'qwixx:room:'.$code;
    }

    private function ttl(): int
    {
        return (int) ($this->config['ttl_hours'] ?? 24) * 3600;
    }
}

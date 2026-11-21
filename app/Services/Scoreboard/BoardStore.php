<?php

declare(strict_types=1);

namespace App\Services\Scoreboard;

use App\Support\Phase\ScoreRoomCode;
use App\Support\Scoreboard\Board;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The only thing in the app that knows where scoreboards live — the same
 * shape as the Phase 10 ScoreRoomStore, under its own key prefix and
 * config. Codes share the Phase 10 alphabet (no I, O, 0 or 1) so every
 * code in the app reads out the same way.
 */
final class BoardStore
{
    /**
     * @param  array<string, mixed>  $config  config/scoreboard.php
     */
    public function __construct(private readonly array $config = []) {}

    public function limit(string $key, int $default): int
    {
        return (int) ($this->config[$key] ?? $default);
    }

    public function find(string $code): ?Board
    {
        $data = $this->cache()->get($this->key($code));

        return is_array($data) ? Board::fromArray($data) : null;
    }

    /**
     * Opens a board on a code nobody is using. Codes are short, so a
     * collision is expected rather than exceptional — retry until one is free.
     *
     * A $preferredCode (with the token that went with it) is tried first, so
     * a scorekeeper can reclaim the code of a game the server has forgotten.
     *
     * @param  array<string, mixed>  $state
     */
    public function create(array $state, ?string $preferredCode = null, ?string $token = null): Board
    {
        $token ??= Str::random(40);

        if ($preferredCode !== null) {
            $board = Board::open($preferredCode, $token, $state);

            if ($this->cache()->add($this->key($board->code), $board->toArray(), $this->ttl())) {
                return $board;
            }
        }

        foreach (range(1, 20) as $ignored) {
            $board = Board::open(ScoreRoomCode::generate(), $token, $state);

            if ($this->cache()->add($this->key($board->code), $board->toArray(), $this->ttl())) {
                return $board;
            }
        }

        throw new RuntimeException('Could not allocate a free scoreboard code.');
    }

    /**
     * Runs $mutate against the current board under a lock and stores the
     * result. Returning null from $mutate leaves the board untouched.
     *
     * @param  Closure(Board): ?Board  $mutate
     */
    public function mutate(string $code, Closure $mutate): ?Board
    {
        $lock = $this->cache()->lock($this->key($code).':lock', 5);

        return $lock->block(3, function () use ($code, $mutate): ?Board {
            $board = $this->find($code);

            if (! $board instanceof Board) {
                return null;
            }

            $next = $mutate($board);

            if (! $next instanceof Board) {
                return $board;
            }

            $this->put($next);

            return $next;
        });
    }

    public function put(Board $board): void
    {
        $this->cache()->put($this->key($board->code), $board->toArray(), $this->ttl());
    }

    private function cache(): Repository
    {
        return Cache::store($this->config['store'] ?? 'file');
    }

    private function key(string $code): string
    {
        return 'scoreboard:'.$code;
    }

    private function ttl(): int
    {
        return $this->limit('ttl_hours', 72) * 3600;
    }
}

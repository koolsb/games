<?php

declare(strict_types=1);

namespace App\Support\Scoreboard;

/**
 * A scoreboard as the server keeps it: the code people follow it by, the
 * scorekeeper's token, and the game itself.
 *
 * The game (`state`) is owned by the scorekeeper's browser — players,
 * entries, the round — and the server never interprets it beyond checking
 * its shape. Totals, standings and the round history are all derived from
 * the entry log in the browser, so there is nothing here to keep in step.
 *
 * `rev` is the browser's own revision counter. Pushes can arrive late or
 * twice on a flaky connection; only ever accepting a higher revision means
 * a retry or an out-of-order response can never roll the game back.
 */
final readonly class Board
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public string $code,
        public string $hostToken,
        public array $state,
        public int $rev = 0,
        public ?int $createdAt = null,
        public ?int $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function open(string $code, string $hostToken, array $state): self
    {
        return new self($code, $hostToken, $state, (int) ($state['rev'] ?? 0), time(), time());
    }

    public function isHost(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->hostToken, $token);
    }

    /**
     * Is this push newer than what we hold? Equal counts as not newer: it
     * is the same write arriving again.
     */
    public function accepts(int $rev): bool
    {
        return $rev > $this->rev;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function withState(array $state): self
    {
        return new self($this->code, $this->hostToken, $state, (int) ($state['rev'] ?? 0), $this->createdAt, time());
    }

    /**
     * What any browser — scorekeeper or follower — gets back. Never the token.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'code' => $this->code,
            'rev' => $this->rev,
            'updatedAt' => $this->updatedAt,
            'state' => $this->state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'host_token' => $this->hostToken,
            'state' => $this->state,
            'rev' => $this->rev,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            hostToken: (string) $data['host_token'],
            state: (array) ($data['state'] ?? []),
            rev: (int) ($data['rev'] ?? 0),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}

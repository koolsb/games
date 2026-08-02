<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

use Illuminate\Support\Str;

/**
 * One seat in a multiplayer room.
 *
 * `state` is the engine's per-player slice ({ rows, penalties }) exactly as
 * the browser produced it. The server never reads into it — the rules run
 * client side — so it travels as an opaque array.
 */
final readonly class RoomPlayer
{
    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $token,
        public bool $isHost,
        public array $state,
        public int $lastSeenAt,
    ) {}

    public static function make(string $name, bool $isHost): self
    {
        return new self(
            id: (string) Str::uuid(),
            name: $name,
            token: Str::random(40),
            isHost: $isHost,
            state: [],
            lastSeenAt: time(),
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function withState(array $state): self
    {
        return new self($this->id, $this->name, $this->token, $this->isHost, $state, time());
    }

    public function withName(string $name): self
    {
        return new self($this->id, $name, $this->token, $this->isHost, $this->state, $this->lastSeenAt);
    }

    public function seen(): self
    {
        return new self($this->id, $this->name, $this->token, $this->isHost, $this->state, time());
    }

    /**
     * Wipes the sheet but keeps the seat — the host starting another game.
     */
    public function withoutState(): self
    {
        return new self($this->id, $this->name, $this->token, $this->isHost, [], $this->lastSeenAt);
    }

    public function owns(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->token, $token);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'token' => $this->token,
            'is_host' => $this->isHost,
            'state' => $this->state,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            token: (string) $data['token'],
            isHost: (bool) $data['is_host'],
            state: (array) ($data['state'] ?? []),
            lastSeenAt: (int) $data['last_seen_at'],
        );
    }

    /**
     * The public shape: everything but the token, which is the one thing
     * that must never reach another player's browser.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'isHost' => $this->isHost,
            'state' => $this->state ?: null,
            'lastSeenAt' => $this->lastSeenAt,
        ];
    }
}

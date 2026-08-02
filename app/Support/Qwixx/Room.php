<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

/**
 * A multiplayer game: a code, the layout everyone is playing, and one seat
 * per player. The server keeps no rules — it stores each player's sheet and
 * hands the whole roster back so every browser can run the same engine over
 * the same data.
 *
 * `version` bumps on every write so a client can tell a stale poll from a
 * fresh one; `round` bumps when the host starts another game, which is the
 * signal for every device to clear its sheet.
 */
final readonly class Room
{
    public const LOBBY = 'lobby';

    public const PLAYING = 'playing';

    public const ENDED = 'ended';

    /**
     * @param  list<RoomPlayer>  $players
     */
    public function __construct(
        public string $code,
        public string $layoutId,
        public string $status,
        public array $players,
        public int $version = 1,
        public int $round = 1,
        public ?int $createdAt = null,
        public ?int $updatedAt = null,
        public ?int $endedAt = null,
    ) {}

    public static function open(string $code, string $layoutId, RoomPlayer $host): self
    {
        return new self(
            code: $code,
            layoutId: $layoutId,
            status: self::LOBBY,
            players: [$host],
            createdAt: time(),
            updatedAt: time(),
        );
    }

    /**
     * @param  list<RoomPlayer>  $players
     */
    public function with(?string $status = null, ?array $players = null, ?int $endedAt = null): self
    {
        return new self(
            code: $this->code,
            layoutId: $this->layoutId,
            status: $status ?? $this->status,
            players: $players ?? $this->players,
            version: $this->version + 1,
            round: $this->round,
            createdAt: $this->createdAt,
            updatedAt: time(),
            endedAt: $endedAt ?? $this->endedAt,
        );
    }

    public function join(RoomPlayer $player): self
    {
        return $this->with(players: [...$this->players, $player]);
    }

    /**
     * Another game with the same people: every sheet is wiped and `round`
     * bumps, which is how each browser knows to clear its own copy.
     */
    public function restarted(): self
    {
        return new self(
            code: $this->code,
            layoutId: $this->layoutId,
            status: self::PLAYING,
            players: array_map(fn (RoomPlayer $player): RoomPlayer => $player->withoutState(), $this->players),
            version: $this->version + 1,
            round: $this->round + 1,
            createdAt: $this->createdAt,
            updatedAt: time(),
            endedAt: null,
        );
    }

    public function player(string $id): ?RoomPlayer
    {
        foreach ($this->players as $player) {
            if ($player->id === $id) {
                return $player;
            }
        }

        return null;
    }

    /**
     * The seat a request's token belongs to, if any.
     */
    public function playerFor(?string $token): ?RoomPlayer
    {
        foreach ($this->players as $player) {
            if ($player->owns($token)) {
                return $player;
            }
        }

        return null;
    }

    public function replace(RoomPlayer $player): self
    {
        return $this->with(players: array_map(
            fn (RoomPlayer $existing): RoomPlayer => $existing->id === $player->id ? $player : $existing,
            $this->players,
        ));
    }

    public function isFull(int $max): bool
    {
        return count($this->players) >= $max;
    }

    public function hasStarted(): bool
    {
        return $this->status !== self::LOBBY;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'layout' => $this->layoutId,
            'status' => $this->status,
            'players' => array_map(fn (RoomPlayer $player): array => $player->toArray(), $this->players),
            'version' => $this->version,
            'round' => $this->round,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'ended_at' => $this->endedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: (string) $data['code'],
            layoutId: (string) $data['layout'],
            status: (string) $data['status'],
            players: array_values(array_map(
                fn (array $player): RoomPlayer => RoomPlayer::fromArray($player),
                $data['players'] ?? [],
            )),
            version: (int) ($data['version'] ?? 1),
            round: (int) ($data['round'] ?? 1),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            endedAt: $data['ended_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'code' => $this->code,
            'layout' => $this->layoutId,
            'status' => $this->status,
            'players' => array_map(fn (RoomPlayer $player): array => $player->toClientArray(), $this->players),
            'version' => $this->version,
            'round' => $this->round,
            'endedAt' => $this->endedAt,
        ];
    }
}

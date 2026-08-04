<?php

declare(strict_types=1);

namespace App\Support\Phase;

use InvalidArgumentException;
use RuntimeException;

/**
 * A scoring game: a code, the phase list everyone is playing, and one seat
 * per player. Only the host (the browser holding `hostToken`) ever writes;
 * everyone else just reads.
 *
 * Rounds are entered in lockstep for every player at once, because that's
 * how the table actually plays — a hand ends, everyone tallies, then the
 * next hand starts. `settled()` recomputes status/winner from the full
 * round log after every mutation, rather than tracking a delta, so undoing
 * a round is just popping the log and recomputing — never bespoke inverse
 * logic that can drift from the forward logic.
 */
final readonly class ScoreRoom
{
    public const ACTIVE = 'active';

    public const TIEBREAK = 'tiebreak';

    public const ENDED = 'ended';

    /**
     * @param  list<string>  $phases  phase signatures, snapshotted at creation
     * @param  list<ScorePlayer>  $players
     * @param  list<string>  $tiedPlayerIds
     * @param  list<string>  $winnerIds
     */
    public function __construct(
        public string $code,
        public string $hostToken,
        public array $phases,
        public array $players,
        public string $status = self::ACTIVE,
        public array $tiedPlayerIds = [],
        public array $winnerIds = [],
        public ?int $createdAt = null,
        public ?int $updatedAt = null,
    ) {}

    /**
     * @param  list<string>  $phaseSignatures
     * @param  list<string>  $playerNames
     */
    public static function open(string $code, string $hostToken, array $phaseSignatures, array $playerNames): self
    {
        return new self(
            code: $code,
            hostToken: $hostToken,
            phases: $phaseSignatures,
            players: array_values(array_map(static fn (string $name): ScorePlayer => ScorePlayer::make($name), $playerNames)),
            createdAt: time(),
            updatedAt: time(),
        );
    }

    public function phaseCount(): int
    {
        return count($this->phases);
    }

    public function player(string $id): ?ScorePlayer
    {
        foreach ($this->players as $player) {
            if ($player->id === $id) {
                return $player;
            }
        }

        return null;
    }

    /**
     * Applies one hand to every player at once.
     *
     * @param  array<string, array{score: int, completed: bool}>  $entries  keyed by player id
     */
    public function addRound(array $entries): self
    {
        if ($this->status !== self::ACTIVE) {
            throw new RuntimeException('This game is not active.');
        }

        $ids = array_map(static fn (ScorePlayer $p): string => $p->id, $this->players);

        if (array_diff($ids, array_keys($entries)) !== [] || array_diff(array_keys($entries), $ids) !== []) {
            throw new InvalidArgumentException('A round must include exactly one entry per player.');
        }

        $players = array_map(
            fn (ScorePlayer $p): ScorePlayer => $p->withRound((int) $entries[$p->id]['score'], (bool) $entries[$p->id]['completed']),
            $this->players,
        );

        return $this->withPlayers($players)->settled();
    }

    /**
     * The last hand turned out to be a misentry. This can walk a finished
     * or tied-out game back to active — deliberate, mirroring the Qwixx
     * precedent of letting an accidental last mark be taken back after
     * game over.
     */
    public function undoLastRound(): self
    {
        $players = array_map(static fn (ScorePlayer $p): ScorePlayer => $p->withoutLastRound(), $this->players);

        return $this->withPlayers($players)->settled();
    }

    /**
     * Records the outcome of the physical Phase 10 replay two or more
     * exactly-tied players had to play out at the table.
     */
    public function resolveTiebreak(string $winnerId): self
    {
        if ($this->status !== self::TIEBREAK) {
            throw new RuntimeException('This game is not in a tiebreak.');
        }

        if (! in_array($winnerId, $this->tiedPlayerIds, true)) {
            throw new InvalidArgumentException('That player is not part of the tiebreak.');
        }

        return $this->withStatus(self::ENDED, [], [$winnerId]);
    }

    /**
     * Recomputes status/tiebreak/winner from the full round log. Finds the
     * first round (chronologically) at which any player's completed-phase
     * count reaches the phase count — that is when the game actually ends,
     * regardless of how many rounds have since been undone or replayed.
     */
    private function settled(): self
    {
        $phaseCount = $this->phaseCount();

        if ($phaseCount === 0 || $this->players === []) {
            return $this->withStatus(self::ACTIVE, [], []);
        }

        $roundsPlayed = count($this->players[0]->log);

        for ($round = 0; $round < $roundsPlayed; $round++) {
            $finishedIds = [];

            foreach ($this->players as $player) {
                $completedThroughRound = count(array_filter(
                    array_slice($player->log, 0, $round + 1),
                    static fn (array $entry): bool => $entry['completed'],
                ));

                if ($completedThroughRound >= $phaseCount) {
                    $finishedIds[] = $player->id;
                }
            }

            if ($finishedIds !== []) {
                return $this->settleAmong($finishedIds);
            }
        }

        return $this->withStatus(self::ACTIVE, [], []);
    }

    /**
     * @param  list<string>  $finishedIds  players who reached the final phase in the same round
     */
    private function settleAmong(array $finishedIds): self
    {
        if (count($finishedIds) === 1) {
            return $this->withStatus(self::ENDED, [], $finishedIds);
        }

        $finishers = array_values(array_filter(
            $this->players,
            static fn (ScorePlayer $p): bool => in_array($p->id, $finishedIds, true),
        ));

        $lowestScore = min(array_map(static fn (ScorePlayer $p): int => $p->totalScore(), $finishers));

        $lowestIds = array_values(array_map(
            static fn (ScorePlayer $p): string => $p->id,
            array_filter($finishers, static fn (ScorePlayer $p): bool => $p->totalScore() === $lowestScore),
        ));

        return count($lowestIds) === 1
            ? $this->withStatus(self::ENDED, [], $lowestIds)
            : $this->withStatus(self::TIEBREAK, $lowestIds, []);
    }

    /**
     * @param  list<ScorePlayer>  $players
     */
    private function withPlayers(array $players): self
    {
        return new self($this->code, $this->hostToken, $this->phases, $players, $this->status, $this->tiedPlayerIds, $this->winnerIds, $this->createdAt, time());
    }

    /**
     * @param  list<string>  $tiedPlayerIds
     * @param  list<string>  $winnerIds
     */
    private function withStatus(string $status, array $tiedPlayerIds, array $winnerIds): self
    {
        return new self($this->code, $this->hostToken, $this->phases, $this->players, $status, $tiedPlayerIds, $winnerIds, $this->createdAt, time());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'host_token' => $this->hostToken,
            'phases' => $this->phases,
            'players' => array_map(static fn (ScorePlayer $p): array => $p->toArray(), $this->players),
            'status' => $this->status,
            'tied_player_ids' => $this->tiedPlayerIds,
            'winner_ids' => $this->winnerIds,
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
            phases: array_values((array) ($data['phases'] ?? [])),
            players: array_values(array_map(
                static fn (array $player): ScorePlayer => ScorePlayer::fromArray($player),
                (array) ($data['players'] ?? []),
            )),
            status: (string) ($data['status'] ?? self::ACTIVE),
            tiedPlayerIds: array_values((array) ($data['tied_player_ids'] ?? [])),
            winnerIds: array_values((array) ($data['winner_ids'] ?? [])),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}

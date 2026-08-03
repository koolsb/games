<?php

declare(strict_types=1);

namespace App\Http\Controllers\Qwixx;

use App\Http\Controllers\Controller;
use App\Services\Qwixx\LayoutLibrary;
use App\Services\Qwixx\RoomStore;
use App\Support\Qwixx\Room;
use App\Support\Qwixx\RoomCode;
use App\Support\Qwixx\RoomPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The multiplayer room API. Deliberately dumb: it stores each player's sheet
 * and hands the roster back. Every rule — what may be crossed, when a row is
 * locked, when the game is over, what everyone scored — runs in the browser
 * against the same engine solo and 2-player games use.
 *
 * A player's only credential is the opaque token they got when they created
 * or joined the room, sent as `X-Qwixx-Token`. It authorises exactly one
 * thing: writing that player's own slice.
 */
final class RoomController extends Controller
{
    public function __construct(
        private readonly RoomStore $rooms,
        private readonly LayoutLibrary $layouts,
    ) {}

    /** Host a game. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'layout' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:'.$this->rooms->nameMax()],
        ]);

        if (! $this->layouts->find($data['layout'])) {
            return $this->error('That scoresheet does not exist.', 'unknown_layout', 422);
        }

        $room = $this->rooms->create($data['layout'], $this->cleanName($data['name'] ?? null, 1));

        return $this->seated($room, $room->players[0], 201);
    }

    /** Poll a room without a seat — the join screen and the shared link. */
    public function show(string $code): JsonResponse
    {
        $room = $this->lookup($code);

        return $room instanceof Room
            ? response()->json(['room' => $room->toClientArray()])
            : $this->missing();
    }

    /** Take a seat. */
    public function join(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:'.$this->rooms->nameMax()],
        ]);

        $room = $this->lookup($code);

        if (! $room instanceof Room) {
            return $this->missing();
        }

        if ($room->hasStarted()) {
            return $this->error('That game has already started.', 'already_started', 409);
        }

        if ($room->isFull($this->rooms->maxPlayers())) {
            return $this->error('That game is full.', 'full', 409);
        }

        $player = RoomPlayer::make(
            $this->cleanName($data['name'] ?? null, count($room->players) + 1),
            isHost: false,
        );

        // Re-check inside the lock: two people can tap Join on the last seat
        // at the same moment, and the roster read above may already be stale.
        $joined = $this->rooms->mutate($room->code, function (Room $current) use ($player): ?Room {
            if ($current->hasStarted() || $current->isFull($this->rooms->maxPlayers())) {
                return null;
            }

            return $current->join($player);
        });

        if (! $joined instanceof Room || ! $joined->player($player->id)) {
            return $this->error('That game just filled up or started.', 'full', 409);
        }

        return $this->seated($joined, $player);
    }

    /**
     * Push this player's sheet and read everyone else's. This is also the
     * poll — clients call it on a timer whether or not they have changes.
     */
    public function sync(Request $request, string $code): JsonResponse
    {
        /*
         * `present`, not `required`, on the per-row fields: every sheet
         * starts with no crosses at all, and `required` rejects an empty
         * array. Requiring them there rejected the exact payload a new game
         * sends on its very first sync, which silently broke every room.
         */
        $data = $request->validate([
            'state' => ['nullable', 'array'],
            'state.penalties' => ['required_with:state', 'integer', 'between:0,4'],
            'state.rows' => ['required_with:state', 'array', 'size:4'],
            'state.rows.*.crosses' => ['present', 'array', 'max:11'],
            'state.rows.*.crosses.*' => ['integer', 'between:0,10'],
            'state.rows.*.locked' => ['present', 'boolean'],
            'state.rows.*.closed' => ['present', 'boolean'],
            'ended' => ['nullable', 'boolean'],
        ]);

        return $this->asPlayer($request, $code, function (Room $room, RoomPlayer $player) use ($data): Room {
            $next = array_key_exists('state', $data) && is_array($data['state'])
                ? $room->replace($player->withState($this->sheet($data['state'])))
                : $room->replace($player->seen());

            // The first browser to notice the game is over says so, and the
            // room stays ended for everyone — including anyone who reloads.
            if (($data['ended'] ?? false) && $next->status !== Room::ENDED) {
                $next = $next->with(status: Room::ENDED, endedAt: time());
            }

            return $next;
        });
    }

    /** Host only: close the lobby and deal everyone in. */
    public function start(Request $request, string $code): JsonResponse
    {
        return $this->asHost($request, $code, fn (Room $room): Room => $room->with(status: Room::PLAYING));
    }

    /** Host only: another game with the same people. */
    public function restart(Request $request, string $code): JsonResponse
    {
        return $this->asHost($request, $code, fn (Room $room): Room => $room->restarted());
    }

    /**
     * Runs $mutate as the token's own player.
     *
     * @param  callable(Room, RoomPlayer): Room  $mutate
     */
    private function asPlayer(Request $request, string $code, callable $mutate, bool $hostOnly = false): JsonResponse
    {
        $token = $request->header('X-Qwixx-Token');
        $room = $this->lookup($code);

        if (! $room instanceof Room) {
            return $this->missing();
        }

        $player = $room->playerFor(is_string($token) ? $token : null);

        if (! $player instanceof RoomPlayer) {
            return $this->error('You are not in that game.', 'not_seated', 403);
        }

        if ($hostOnly && ! $player->isHost) {
            return $this->error('Only the host can do that.', 'not_host', 403);
        }

        $updated = $this->rooms->mutate($room->code, function (Room $current) use ($player, $mutate): ?Room {
            // Re-resolve against the record we hold the lock on: another
            // player's write may have replaced the roster since the read.
            $seat = $current->player($player->id);

            return $seat instanceof RoomPlayer ? $mutate($current, $seat) : null;
        });

        return $updated instanceof Room
            ? response()->json(['room' => $updated->toClientArray(), 'playerId' => $player->id])
            : $this->missing();
    }

    /**
     * @param  callable(Room): Room  $mutate
     */
    private function asHost(Request $request, string $code, callable $mutate): JsonResponse
    {
        return $this->asPlayer($request, $code, fn (Room $room): Room => $mutate($room), hostOnly: true);
    }

    private function lookup(string $code): ?Room
    {
        $code = RoomCode::normalize($code);

        return RoomCode::isValid($code) ? $this->rooms->find($code) : null;
    }

    private function seated(Room $room, RoomPlayer $player, int $status = 200): JsonResponse
    {
        return response()->json([
            'room' => $room->toClientArray(),
            'token' => $player->token,
            'playerId' => $player->id,
        ], $status);
    }

    private function missing(): JsonResponse
    {
        return $this->error('That game code is not active — check it and try again.', 'not_found', 404);
    }

    private function error(string $message, string $reason, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'reason' => $reason], $status);
    }

    /**
     * Rebuild the slice in exactly the shape the browser engine expects,
     * from the validated input. Every other device reads this back and
     * feeds it straight to the rules engine, so a row that arrived without
     * its `crosses` key would break their sheet, not ours. Rebuilding also
     * keeps anything we did not ask for out of the cache.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function sheet(array $state): array
    {
        return [
            'penalties' => (int) ($state['penalties'] ?? 0),
            'rows' => array_values(array_map(fn (array $row): array => [
                'crosses' => array_values(array_map(intval(...), $row['crosses'] ?? [])),
                'locked' => (bool) ($row['locked'] ?? false),
                'closed' => (bool) ($row['closed'] ?? false),
            ], $state['rows'] ?? [])),
        ];
    }

    /**
     * Names are shouted across a table and printed on a results screen —
     * trim them, drop anything invisible, and fall back to the seat number.
     */
    private function cleanName(?string $name, int $seat): string
    {
        $name = trim(preg_replace('/\p{C}+/u', '', $name ?? '') ?? '');

        return $name === '' ? 'Player '.$seat : mb_substr($name, 0, $this->rooms->nameMax());
    }
}

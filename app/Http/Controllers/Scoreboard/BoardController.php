<?php

declare(strict_types=1);

namespace App\Http\Controllers\Scoreboard;

use App\Http\Controllers\Controller;
use App\Services\Scoreboard\BoardStore;
use App\Support\Phase\ScoreRoomCode;
use App\Support\Scoreboard\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The scoreboard API. Deliberately dumb, like the Qwixx room API: the
 * scorekeeper's browser owns the game and pushes the whole thing; the
 * server checks its shape, keeps the newest revision, and hands it to
 * anyone following along.
 *
 * The scorekeeper's only credential is the token returned on creation,
 * sent as `X-Scoreboard-Token`. Followers need nothing — a shared link is
 * read-only by construction.
 */
final class BoardController extends Controller
{
    public function __construct(private readonly BoardStore $boards) {}

    /**
     * Register a game the scorekeeper has started (possibly offline).
     *
     * A scorekeeper whose game the server has lost — the TTL ran out, or a
     * redeploy cleared the cache — sends its old code and token back, and
     * gets the same code again if it is still free. That keeps the link
     * everyone at the table already has working.
     */
    public function store(Request $request): JsonResponse
    {
        $claim = $request->validate([
            'code' => ['nullable', 'string', 'max:8'],
            'token' => ['nullable', 'string', 'size:40', 'alpha_num'],
        ]);

        $code = isset($claim['code']) ? ScoreRoomCode::normalize($claim['code']) : null;

        $board = $this->boards->create(
            $this->validState($request),
            $code !== null && ScoreRoomCode::isValid($code) ? $code : null,
            $claim['token'] ?? null,
        );

        return response()->json([
            'board' => $board->toClientArray(),
            'token' => $board->hostToken,
        ], 201);
    }

    /** Follow a game. */
    public function show(string $code): JsonResponse
    {
        $board = $this->lookup($code);

        return $board instanceof Board
            ? response()->json(['board' => $board->toClientArray()])
            : $this->missing();
    }

    /**
     * Push the scorekeeper's game. A revision no newer than the stored one
     * is a retry or a late arrival: it is ignored, and the stored board is
     * returned so the browser can see it was already applied.
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $board = $this->lookup($code);

        if (! $board instanceof Board) {
            return $this->missing();
        }

        if (! $board->isHost($request->header('X-Scoreboard-Token'))) {
            return $this->error('Only the scorekeeper can change this game.', 'not_host', 403);
        }

        $state = $this->validState($request);
        $accepted = false;

        $updated = $this->boards->mutate($board->code, function (Board $current) use ($state, &$accepted): ?Board {
            if (! $current->accepts((int) $state['rev'])) {
                return null;
            }

            $accepted = true;

            return $current->withState($state);
        });

        return $updated instanceof Board
            ? response()->json(['board' => $updated->toClientArray(), 'accepted' => $accepted])
            : $this->missing();
    }

    /**
     * Validates the pushed game and rebuilds it in exactly the shape every
     * follower's browser expects, so nothing we did not ask for reaches the
     * cache and a malformed entry can't break someone else's screen.
     *
     * @return array<string, mixed>
     */
    private function validState(Request $request): array
    {
        $points = $this->boards->limit('max_points', 1000000);

        $data = $request->validate([
            'state' => ['required', 'array'],
            'state.title' => ['nullable', 'string', 'max:'.$this->boards->limit('title_max', 40)],
            'state.lowestWins' => ['nullable', 'boolean'],
            'state.round' => ['required', 'integer', 'between:1,100000'],
            'state.rev' => ['required', 'integer', 'min:0'],
            'state.players' => ['required', 'array', 'min:1', 'max:'.$this->boards->limit('max_players', 12)],
            'state.players.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'state.players.*.name' => ['nullable', 'string', 'max:'.$this->boards->limit('name_max', 20)],
            // `present`, not `required`: a brand-new game has no entries, and
            // `required` rejects an empty array (the bug Qwixx's sync hit).
            'state.entries' => ['present', 'array', 'max:'.$this->boards->limit('max_entries', 5000)],
            'state.entries.*.id' => ['required', 'string', 'max:64'],
            'state.entries.*.batch' => ['required', 'string', 'max:64'],
            'state.entries.*.kind' => ['nullable', 'string', 'in:add,round'],
            'state.entries.*.playerId' => ['required', 'string', 'max:64'],
            'state.entries.*.points' => ['required', 'integer', "between:-$points,$points"],
            'state.entries.*.round' => ['required', 'integer', 'between:1,100000'],
            'state.entries.*.at' => ['nullable', 'integer', 'min:0'],
        ])['state'];

        $players = array_values(array_map(fn (array $player, int $i): array => [
            'id' => (string) $player['id'],
            'name' => $this->cleanName($player['name'] ?? null, $i + 1),
        ], $data['players'], array_keys(array_values($data['players']))));

        $ids = array_column($players, 'id');

        $entries = array_values(array_map(static fn (array $entry): array => [
            'id' => (string) $entry['id'],
            'batch' => (string) $entry['batch'],
            'kind' => ($entry['kind'] ?? 'add') === 'round' ? 'round' : 'add',
            'playerId' => (string) $entry['playerId'],
            'points' => (int) $entry['points'],
            'round' => (int) $entry['round'],
            'at' => isset($entry['at']) ? (int) $entry['at'] : null,
        ], array_filter(
            $data['entries'],
            // An entry for a player who isn't on the board would show up in
            // nobody's total — drop it rather than store a ghost.
            static fn (array $entry): bool => in_array((string) $entry['playerId'], $ids, true),
        )));

        return [
            'title' => trim(preg_replace('/\p{C}+/u', '', (string) ($data['title'] ?? '')) ?? ''),
            'lowestWins' => (bool) ($data['lowestWins'] ?? false),
            'round' => (int) $data['round'],
            'rev' => (int) $data['rev'],
            'players' => $players,
            'entries' => $entries,
        ];
    }

    private function lookup(string $code): ?Board
    {
        $code = ScoreRoomCode::normalize($code);

        return ScoreRoomCode::isValid($code) ? $this->boards->find($code) : null;
    }

    private function missing(): JsonResponse
    {
        return $this->error('There is no scoreboard with that code — check it and try again.', 'not_found', 404);
    }

    private function error(string $message, string $reason, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'reason' => $reason], $status);
    }

    /**
     * Names are read across a table — trim them, drop anything invisible,
     * and fall back to the seat number.
     */
    private function cleanName(?string $name, int $seat): string
    {
        $name = trim(preg_replace('/\p{C}+/u', '', $name ?? '') ?? '');

        return $name === '' ? 'Player '.$seat : mb_substr($name, 0, $this->boards->limit('name_max', 20));
    }
}

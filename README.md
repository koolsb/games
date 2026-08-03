# Games

Browser-based helpers for the games on the table, served as one app at
**games.kools.us**. The root is a menu; each game lives under its own path.

| Path | Game |
| --- | --- |
| `/` | Game menu |
| `/qwixx` | [Qwixx](https://gamewright.com/product/Qwixx) scoresheets |
| `/qwixx/room/{code}` | A Qwixx game several people play on their own devices |
| `/phase10` | [Phase 10](https://en.wikipedia.org/wiki/Phase_10) game generator |

Built with **Laravel 13 · Livewire 4 · Flux UI Pro · Tailwind 4 · Alpine.js**. No
database, no login — each game's data lives in a config file, and Qwixx's game
state lives in the browser's `localStorage`. Multiplayer Qwixx games are the one
piece of server-side state, and they live in the cache under a TTL.

This repo replaces the separate `koolsb/qwixx` and `koolsb/phase10` repos.

## The games

### Qwixx — `/qwixx`

A digital scoresheet that replaces the paper pad: tap numbers to cross them out,
the left-to-right rule is enforced automatically, scores tally live, and the game
announces when it's over. A hard refresh (or a crashed tab) restores the sheet
exactly as it was.

- **Three sheet layouts** (Classic, Mixed Numbers, Mixed Colors), selectable from
  the picker at `/qwixx`. Layouts are pure config — see below to add more.
- **Rules enforced**: crosses go left to right; skipped cells are struck out; the
  final cell needs 5 crosses and earns the lock + bonus mark; tapping your most
  recent cross undoes it (mistake correction).
- **Three modes**:
  - **Solo** — one sheet, sized for iPad/phone landscape, for playing along with
    a physical game.
  - **2 players** — both sheets on one iPad lying flat between the players, the
    top sheet rotated 180°. Locking a row locks it for the other player
    automatically. Both players can name themselves by tapping the name on
    their sheet.
  - **Multiplayer** — one player hosts from a layout card and reads out the
    four-character code; everyone else joins from **Join a game** (or the
    copied link) and plays on their own device. See below.
- **Rows locked elsewhere**: the small circled button at a row's end marks a row
  locked by a player on paper. Per the rules' simultaneous-lock clause, a player
  with 5+ crosses can still take the final cell of a freshly locked row.
- **Game over** banner when two rows are locked or four penalties are taken —
  with the final standings and the winner in 2-player and multiplayer games.
  It carries an **Undo last mark** button, because a game can end on a mistap
  and the banner covers the cells you would otherwise tap to take it back. In a
  room each device undoes its own sheet; the moment that sync lands, the table
  is playing again — the verdict is derived from the sheets, not latched.
- **Screen wake lock** on by default (toggleable) so the iPad doesn't sleep
  mid-game, plus a full-screen toggle where the browser supports it.
- **Reset** with a confirmation dialog.

#### Multiplayer rooms

A host picks a sheet, gets a four-character code (base32 minus the characters
people misread — no `I`, `O`, `0` or `1`) and a lobby; everyone joins with the
code, and the host taps **Start game**. Joining after the start is refused. The
lobby, the sheet and the final scores are all states of one URL,
`/qwixx/room/{code}`, which doubles as a share link.

**The rules stay in the browser.** Each device runs the same
[`engine.js`](resources/js/qwixx/engine.js) a solo game does, over a state whose
extra player slices arrive from the server — so locking, the simultaneous-lock
clause, penalties, game over and scoring are one implementation for all three
modes. The server stores each player's slice and hands the roster back; it never
inspects a sheet. A device pushes its own slice and polls the others every two
seconds (`POST /qwixx/rooms/{code}/sync`, which is both push and poll), and the
only thing a device is authoritative about is its own sheet — so a slow response
can never undo a tap, and play continues offline and flushes on reconnect.

Rooms live in the cache (`config('qwixx.multiplayer')`), keyed by code, under a
sliding 24-hour TTL that every write refreshes:

| Setting | Default | |
| --- | --- | --- |
| `QWIXX_ROOM_CACHE` | `file` | cache store holding rooms |
| `ttl_hours` | 24 | refreshed on every write |
| `max_players` | 8 | seats per room |

Two consequences worth knowing: **a redeploy clears every in-progress game**, and
the `file` store is per-container, so the deployment assumes **one replica**. For
more than one, point `QWIXX_ROOM_CACHE` at a shared store (`redis`) — the code
needs no change. There is still no database and no PVC.

### Phase 10 — `/phase10`

Generate fresh Phase 10 games to keep the card game interesting. The app holds a
large, difficulty-weighted library of phases (the 10 classics plus hundreds of
auto-generated variants) and builds a new 10-phase game on demand — which you can
tweak and print as a Phase-10-style card.

- **Difficulty** is computed from each phase's components, accounting for
  set/run/color/parity hardness, deck scarcity, and multi-component compounding.
  Scores map to bands: Easy / Medium / Hard / Brutal.
- **Generation** supports three modes: **classic ramp** (difficulty climbs
  easy→brutal), **flat band**, and **manual** (pick the band per slot). Plus a
  fixed seed for reproducible games, per-slot regenerate, and no duplicates.
- **Printing** is browser-native: `/phase10/print` renders the card and you
  Print → Save as PDF. No headless browser, no server-side PDF engine.

## How it fits together

Every game is mounted under a path prefix *and* a matching route-name prefix in
[`routes/web.php`](routes/web.php) (`qwixx.*`, `phase10.*`), and its landing page
is the prefix root. Code is namespaced the same way:

```
app/{Enums,Support,Services}/Qwixx/…      config/qwixx.php     resources/views/qwixx/
app/{Enums,Support,Services}/Phase/…      config/phases.php    resources/views/phase10/
```

[`config/games.php`](config/games.php) is the registry the shared UI reads: the
root menu renders one card per entry, and the shared header
([`resources/views/components/site/header.blade.php`](resources/views/components/site/header.blade.php))
uses the current game's entry for its wordmark, colors and Rules link — and
always shows an **All games** control back to the menu, so no game is a dead end.
The Qwixx play screen is chromeless (it owns the whole viewport on a tablet) and
links back to `/qwixx`, which carries the shared header.

Per-game brand colors live side by side in
[`resources/css/app.css`](resources/css/app.css); the accent Flux paints buttons
with is switched by a `theme-*` class the layout puts on `<html>`.

### Adding a game

1. Add an entry to `config/games.php`.
2. Add a route group in `routes/web.php` with a matching path + name prefix.
3. Add a wordmark case in `resources/views/components/site/wordmark.blade.php`
   and a card on `resources/views/home.blade.php`.
4. Namespace its code under `App\…\<Game>` and its views under
   `resources/views/<game>/`.

### Adding a Qwixx layout

Add an entry to [`config/qwixx.php`](config/qwixx.php) and redeploy. Rows come in
two shapes:

```php
['color' => 'red', 'numbers' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]]   // solid row
['lock' => 'red', 'cells' => [[2, 'yellow'], [3, 'blue'], /* ... */]]   // per-cell colors
```

`LayoutFactory` validates every layout at load (4 rows, permutations of 2–12,
each color owning exactly 11 cells and one lock) and fails loudly on boot if a
layout is malformed. The game page embeds the layout as JSON for the client; the
rules engine is pure JavaScript in
[`resources/js/qwixx/engine.js`](resources/js/qwixx/engine.js) and the Alpine glue
that persists to `localStorage` (`qwixx.game.v1`) lives in
[`resources/js/qwixx/game.js`](resources/js/qwixx/game.js). Multiplayer adds
[`resources/js/qwixx/room.js`](resources/js/qwixx/room.js) (transport and merging,
no rules) on the client and, on the server,
[`RoomStore`](app/Services/Qwixx/RoomStore.php) — the only thing that knows where
rooms are kept — behind [`RoomController`](app/Http/Controllers/Qwixx/RoomController.php).

### Adding or removing phases

Edit [`config/phases.php`](config/phases.php) and redeploy. A phase is a list of
`[type, count]` components, e.g.:

```php
[['set', 3], ['run', 4]]            // "1 set of 3 + 1 run of 4"
[['color_run', 5]]                  // "1 run of 5 of one color"
['components' => [['run', 8]], 'notes' => 'Crowd favorite'],
```

Component types: `set`, `run`, `color`, `color_run`, `evens`, `odds`,
`color_evens`, `color_odds`. To add a brand-new requirement type, add a case to
`App\Enums\Phase\ComponentType`, a strategy class under `app/Support/Phase/Types/`,
and wire it in `ComponentType::strategy()`.

## Local development

Flux Pro is a licensed package — configure the credentials once:

```bash
composer config http-basic.composer.fluxui.dev "<flux-username>" "<flux-license-key>"
```

Then:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
composer run dev          # serve + vite
```

Open http://localhost:8000.

### Tests & formatting

```bash
php artisan test          # Pest (both game domains + pages)
npm run test              # Vitest (Qwixx rules engine + room sync)
vendor/bin/pint           # format
```

## Deployment

Push to `main` →

1. **CI** (`.github/workflows/ci.yml`) runs Vitest + Pest + Pint.
2. **Build** (`.github/workflows/docker-publish.yml`) builds a multi-arch
   FrankenPHP image and pushes `ghcr.io/koolsb/games:main`.
3. In the **kools-k3s** GitOps repo, `argocd-image-updater` bumps the digest and
   ArgoCD deploys via the shared `charts/laravel` Helm chart.

### Required GitHub repo secrets

| Secret | Used by |
| --- | --- |
| `FLUX_USERNAME` | CI + image build (Flux Pro) |
| `FLUX_LICENSE_KEY` | CI + image build (Flux Pro) |

`GITHUB_TOKEN` (automatic) is used to push to GHCR.

### Cluster (kools-k3s repo)

Mirror the existing per-game setup: manifests at `apps/games/` (values) and
`apps/templates/games.yaml` (the ArgoCD Application). Set the hostname to
`games.kools.us`, seal `APP_KEY` and a GHCR pull secret, then flip
`games.enabled: true`. Retire the old `qwixx` and `phase10` Applications once
this one is serving.

The container serves on **:8080** (non-root), exposes `/health.php` for probes,
and needs **no PVC and no database** — Qwixx sheets live in each device's browser
and the phase library is rebuilt in memory per request. Multiplayer rooms sit in
the container's file cache, so keep the deployment at **one replica** (or set
`QWIXX_ROOM_CACHE` to a shared store); a rollout ends any game in progress, which
is why rooms are cheap to recreate — a new code takes one tap.

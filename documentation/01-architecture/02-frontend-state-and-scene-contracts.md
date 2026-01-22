# Frontend State & Scene Contracts — MVP (Authoritative)

This document defines the authoritative runtime state contracts between Phaser scenes and the authoritative MVP scene flow.

## 1. Core principles

1) **BootScene is the authentication gate.** Only BootScene calls `GET /api/v1/session`.
2) **Scene inputs are the contract.** Each scene declares required `init(data)` inputs. If required inputs are missing, the scene must route to a safe scene (BootScene or HomeScene) and/or present an error.
3) **Persistent vs run-scoped state is explicit.** Identity and owned inventory are persistent; map, nodes, encounters, and combat runtime are run-scoped.
4) **Single-writer per state slice.** The currently active scene is the single writer for its scene-local state. Backend is the single writer for server-owned state (e.g., node resolution).

## 2. MVP scenes

1) BootScene
2) LandingScene
3) HomeScene
4) RegionSelectScene
5) MapExplorationScene
6) CombatScene
7) LootScene
8) RestScene
9) BossScene
10) WarbandScene
11) DiceInventoryScene
12) DiceDetailsScene
13) UnitDetailsScene

## 3. Shared state shapes

### 3.1 SessionState

```ts
type SessionState = {
  isAuthenticated: boolean;
  user?: { id: string; displayName: string; avatarUrl?: string };
};
```

Source of truth: BootScene.

### 3.2 RunState

```ts
type RunState = {
  runId: string;
  regionId: string;
  seed: number;
  map: {
    nodes: Array<{ nodeId: string; kind: "combat" | "rest" | "loot" | "boss"; isRevealed: boolean; isCleared: boolean }>;
    paths: Array<{ from: string; to: string; isLocked: boolean }>;
    currentNodeId: string;
  };
  resources: { energySpent: number };
};
```

Source of truth: backend for node/path flags; client holds current working copy.

### 3.3 EncounterState

```ts
type EncounterState = {
  nodeId: string;
  encounterId: string;
  type: "combat" | "loot" | "rest" | "boss";
  teamRequirements: { minTeams: 0 | 1; maxTeams: 1 | 2 | 3 | 4 };
  selectedTeams: Array<{ teamId: string; unitIds: number[] }>;
};
```

### 3.4 CombatState

```ts
type CombatState = {
  combatId: string;
  timeline: { round: number; tick: number };
  units: Array<{
    unitId: number;
    side: "player" | "enemy" | "neutral";
    hp: number;
    dicePool: Array<{ dieId: string; size: 4 | 6 | 8 | 10; bonuses: string[] }>;
    position: { r: 0 | 1 | 2; c: 0 | 1 | 2 };
  }>;
  rng: { seed: number; rollIndex: number };
};
```

### 3.5 CombatLog (persisted)

A CombatLog is an append-only event list sufficient to replay the battle without re-simulating.

```ts
type CombatLog = {
  combatId: string;
  meta: { ticksPerRound: 20; rng: { seed: number }; createdAtIso: string; version: 1 };
  events: Array<any>; // MVP: event union defined in backend contracts; keep frontend flexible
};
```

---

## 4. Scene contracts (inputs, outputs, allowed side-effects)

### 4.1 BootScene
Allowed side-effects: `GET /api/v1/session`

Input: none

Output:
- To LandingScene: `{ session: SessionState }` with `isAuthenticated=false`
- To HomeScene: `{ session: SessionState }` with `isAuthenticated=true`

### 4.2 LandingScene
Allowed side-effects: full-page redirect to Discord OAuth start endpoint.

Required input: `{ session: SessionState }` where `isAuthenticated=false`.

### 4.3 HomeScene
Allowed side-effects: none in MVP.

Required input: `{ session: SessionState }`.

Navigation outputs (examples):
- To RegionSelectScene: `{ session }`
- To WarbandScene: `{ session }`
- To DiceInventoryScene: `{ session }`

### 4.4 RegionSelectScene
Allowed side-effects: `POST start run` (exact endpoint per API contracts).

Required input: `{ session: SessionState }`.

Output to MapExplorationScene: `{ session: SessionState; run: RunState }`.

### 4.5 MapExplorationScene
Allowed side-effects:
- `GET /api/v1/runs/{run_id}/map`
- `POST /api/v1/runs/{run_id}/nodes/{node_id}/resolve`

Required input: `{ session: SessionState; run: RunState }`.

Output to an encounter scene: `{ session; run; encounter: EncounterState }`.

### 4.6 CombatScene
Allowed side-effects (typical): `POST resolve/obtain CombatLog` and `POST claim rewards`.

Required input: `{ session; run; encounter }` where `encounter.type="combat"`.

Output to MapExplorationScene:
```ts
{
  session: SessionState;
  run: RunState;
  resolution: { outcome: "victory" | "defeat"; loot?: any[]; xp_award?: number };
}
```

### 4.7 BossScene
Same contract as CombatScene, except `encounter.type="boss"` and run-ending behavior is controlled by Run Resolution.

### 4.8 LootScene
Allowed side-effects: none in MVP (presentation only once node is resolved).

Required input: `{ session; run; encounter }` where `encounter.type="loot"`.

Output to MapExplorationScene: `{ session; run; resolution: { outcome: "success" | "partial" | "fail"; loot?: any[] } }`.

### 4.9 RestScene
Allowed side-effects: none in MVP (presentation only once node is resolved).

Required input: `{ session; run; encounter }` where `encounter.type="rest"`.

Output to MapExplorationScene: `{ session; run; resolution: { outcome: "success" | "partial" | "fail"; loot?: any[] } }`.

### 4.10 WarbandScene / UnitDetailsScene / DiceInventoryScene / DiceDetailsScene
Allowed side-effects: none in MVP.

Required inputs are minimal identifiers (e.g., `{ session; unitId }`, `{ session; diceId }`).

---

## 5. MVP transition matrix

BootScene → LandingScene (unauth)
BootScene → HomeScene (auth)
LandingScene → (OAuth redirect) → BootScene
HomeScene → RegionSelectScene
HomeScene → WarbandScene
HomeScene → DiceInventoryScene
WarbandScene → UnitDetailsScene
UnitDetailsScene → WarbandScene
DiceInventoryScene → DiceDetailsScene
DiceDetailsScene → DiceInventoryScene
RegionSelectScene → MapExplorationScene
MapExplorationScene → CombatScene | LootScene | RestScene | BossScene
CombatScene → MapExplorationScene
LootScene → MapExplorationScene
RestScene → MapExplorationScene
BossScene → MapExplorationScene (then run resolution to Home as applicable)

---

## 6. Open questions (track explicitly)

- Minimum ProfileState required for MVP hydration (warband units, equipped dice, inventory counts)
- Whether combat resolution is purely server-side with log playback, or mixed simulation

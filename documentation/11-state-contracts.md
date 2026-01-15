# State Contracts (MVP)

This document defines the *runtime state contracts* between Phaser scenes.

Goals:
- Make scene boundaries explicit (inputs, outputs, side-effects).
- Ensure state ownership is unambiguous (what is authoritative vs derived).
- Prevent “hidden dependencies” (a scene must not fetch or assume data it was not passed).

Non-goals:
- This is not a full rules spec (see design docs).
- This is not a database schema.

---

## Core Principles

### P1. BootScene is the auth gate
Only BootScene may query `/api/v1/session`. All other scenes must trust the `SessionState`
passed from BootScene.  
Rationale: centralizes auth logic and eliminates redundant calls. :contentReference[oaicite:2]{index=2}

### P2. Scene inputs are the contract
A scene must declare the data it expects in `init(data)` and treat missing required fields as a fatal error
(redirect to a safe scene or show an error).

### P3. Run-scoped vs Persistent state must be explicit
- **Persistent:** account identity, long-term unlocks, owned units/dice, etc.
- **Run-scoped:** current run map, node progress, encounter resolution, combat state.

### P4. Single writer principle per state slice
Each state slice has an owning scene/system that is the “single writer”.
Other scenes may *read* and may *request changes* via explicit transitions, but should not mutate directly.

---

## Shared State Shapes

### SessionState (persistent-ish)
Represents authenticated user identity and session-bound metadata.

```ts
type SessionState = {
  isAuthenticated: boolean;
  user?: {
    id: string;
    displayName: string;
    avatarUrl?: string;
  };
};
````

Source of truth:

* BootScene fetches from backend once, then passes forward.

### ProfileState (persistent)

Represents player-owned progression objects needed for Home/Run start.

```ts
type ProfileState = {
  warband: {
    id: number;
    units: Array<{ unitId: number; /* minimal summary */ }>;
  };
  inventorySummary: {
    diceCount: number;
    currency: number;
  };
};
```

Source of truth:

* Backend (eventually). In MVP may be stubbed/hardcoded.

### RunState (run-scoped)

Represents the current run and its exploration progress.

```ts
type RunState = {
  runId: string;          // server-generated UUID OK for MVP
  regionId: string;
  seed: number;
  map: {
    nodes: Array<{ nodeId: string; kind: "combat" | "rest" | "loot" | "boss"; isRevealed: boolean; isCleared: boolean; }>;
    paths: Array<{ from: string; to: string; isLocked: boolean; }>;
    currentNodeId: string;
  };
  resources: {
    energySpent: number;
    // run-limited currencies, consumables, etc.
  };
};
```

Source of truth:

* Backend (single writer) for node/path flags.
* Seed/region is set at Run start (Region Select / Run setup).

### EncounterState (ephemeral)

Created when entering an encounter node. Destroyed after resolution.

```ts
type EncounterState = {
  nodeId: string;
  encounterId: string; // deterministic from seed + nodeId, or backend-provided
  type: "combat" | "loot" | "rest" | "boss";
  teamRequirements: { minTeams: 0 | 1; maxTeams: 1 | 2 | 3 | 4 };
  selectedTeams: Array<{ teamId: string; unitIds: number[] }>;
};
```

Source of truth:

* Encounter scene (Combat, Loot, Rest, Boss) is the single writer while active.

### CombatState 

Represents automated combat runtime, including dice pools, speed/tick timelines, etc.

```ts
type CombatState = {
  combatId: string;
  timeline: { round: number; tick: number; };
  units: Array<{
    unitId: number;
    side: "player" | "enemy" | "neutral";
    hp: number;
    dicePool: Array<{ dieId: string; size: 4|6|8|10; bonuses: string[] }>;
    position: { r: 0|1|2; c: 0|1|2 };
  }>;
  rng: { seed: number; rollIndex: number };
};
```

Source of truth:

* Combat scene is the single writer.
* Deterministic aside from dice rolls; dice rolls should be reproducible given seed + rollIndex.  

## CombatLog (persisted)

Append-only log of a single resolved combat. Used to replay combat deterministically in the UI without re-simulating.

```ts
type CombatLog = {
  combatId: string;

  // Useful for replay + validation; round is 20 ticks (1..20).
  meta: {
    ticksPerRound: 20;
    rng: { seed: number };
    createdAtIso: string;
    version: 1;
  };

  // Events are stored in the exact execution order they occurred.
  // The replay client should render in-order; it should not “infer” missing events.
  events: Array<
    | {
        type: "phase_start";
        round: number;
        tick: number; // 1..20
        phase: "player_status" | "enemy_status" | "neutral_status" | "player_action" | "enemy_action" | "neutral_action";
      }
    | {
        type: "status_trigger";
        round: number;
        tick: number;
        phase: "player_status" | "enemy_status" | "neutral_status";
        targetUnitId: number;
        statusId: string; // e.g. "poison" | "bolstered" | "sleep"
        stacks?: number;
        durationRoundsRemaining?: number;
        // Optional numbers to support replay UI without recalculation.
        deltaHp?: number; // negative for damage, positive for healing
        notes?: string;
      }
    | {
        type: "action";
        round: number;
        tick: number;
        phase: "player_action" | "enemy_action" | "neutral_action";
        actorUnitId: number;
        abilityId: string; // includes basic attack as an active ability
        targets: number[]; // unitIds
        diceCost: number; // may be 0 or more
        diceConsumed?: Array<{ dieId: string; size: 4|6|8|10 }>;
        rolls?: Array<{
          rollIndex: number;      // monotonically increasing within combat
          dieSize: 4|6|8|10;
          result: number;         // final rolled face value
          modifierTotal?: number; // if you want to record modifiers explicitly
        }>;
        // Optional: compact “result summary” for UI
        results?: Array<
          | { kind: "damage"; targetUnitId: number; amount: number }
          | { kind: "heal"; targetUnitId: number; amount: number }
          | { kind: "status_apply"; targetUnitId: number; statusId: string; durationRounds: number; stacks?: number }
          | { kind: "status_remove"; targetUnitId: number; statusId: string }
        >;
      }
    | {
        type: "unit_death";
        round: number;
        tick: number;
        unitId: number;
        // Clarifies the locked rule: death cancels remaining actions (including same tick).
        cancelsFutureActions: true;
      }
    | {
        type: "combat_end";
        round: number;
        tick: number;
        outcome: "victory" | "defeat";
      }
  >;
};
```

---

## Scene Contracts

This section is the authoritative list of scenes and their required inputs/outputs.

### BootScene

**Responsibilities**

* Fetch `/api/v1/session`.
* Route to Landing (unauth) or Home (auth). 

**Allowed side-effects**

* Network call: `GET /api/v1/session` (only here). 

**Input**

* None.

**Output**

* To LandingScene:

  * `{ session: SessionState }` where `isAuthenticated=false`
* To HomeScene:

  * `{ session: SessionState }` where `isAuthenticated=true`

**Failure modes**

* Network failure: show retry UI or fallback “offline” message; do not proceed to Home.

---

### LandingScene (unauth only)

**Responsibilities**

* Show title/login button.
* Redirect to Discord OAuth start endpoint.

**Allowed side-effects**

* Full-page redirect to backend `/auth/discord/start` (or open new window).

**Required input**

```ts
{
  session: SessionState; // isAuthenticated must be false
}
```

**Output**

* None (OAuth flow returns to BootScene).

**Guards**

* If `session.isAuthenticated === true`, immediately route to HomeScene.

---

### HomeScene

**Responsibilities**

* Display user identity.
* Provide navigation to Region Select (MVP).

**Allowed side-effects**

* None (no auth calls, no profile calls in MVP unless explicitly added later).

**Required input**

```ts
{
  session: SessionState; // isAuthenticated true
  // profile?: ProfileState (optional in MVP; add once backend supports it)
}
```

**Output**

```ts
{
  session: SessionState;
  // profile?: ProfileState;
}
```

---

### RegionSelectScene

**Responsibilities**

* Allow selecting a Region to start a Run.
* Apply “energy cost”. 

**Allowed side-effects**

* POST to “start run” endpoint, decrement energy, receive run seed.

**Required input**

```ts
{
  session: SessionState;
  // profile?: ProfileState;
}
```

**Output**

* To MapExplorationScene:

```ts
{
  session: SessionState;
  run: RunState; // returned from backend
}
```

**Notes**

* This scene is the single writer for initial `run.regionId` and `run.seed`.

---

### MapExplorationScene

**Responsibilities**

* Render the run map (nodes/paths).
* Interface to request movement between nodes.
* Interface to mark nodes as revealed/cleared.
* Choose when to enter an encounter.

**Allowed side-effects**

* GET /api/v1/runs/{run_id}/map
* POST /api/v1/runs/{run_id}/nodes/{node_id}/resolve

**Required input**

```ts
{
  session: SessionState;
  run: RunState;
}
```

**Output (to CombatScene / LootScene / RestScene / BossScene)**

```ts
{
  session: SessionState;
  run: RunState;               // updated with currentNodeId, reveal/clear flags
  encounter: EncounterState;   // created based on the node
}
```

**Output (to HomeScene, run end)**

```ts
{
  session: SessionState;
  // rewards?: RewardSummary;
}
```

**Guards**

* If `run.map.currentNodeId` is invalid, reset to start node or abort run.

---

### CombatScene

**Responsibilities**

* Create CombatState from EncounterState + RunState (and selected teams).
* Show outcome of server-generated combat
* Allow player to replay combat via combat log
* Allow player to claim rewards
* Return result to MapExploration.

**Allowed side-effects**

* POST to "start combat" backend endpoint, generate or retrieve combat log on backend, return combat log
* Later: telemetry/events.

**Required input**

```ts
{
  session: SessionState;
  run: RunState;
  encounter: EncounterState; // type must be "combat"
}
```

**Output (back to MapExplorationScene)**

```ts
{
  session: SessionState;
  run: RunState; // mark node cleared on victory; apply resource changes
  resolution: {
    outcome: "victory" | "defeat";
    loot?: Array<{ /* minimal loot */ }>;
    xp?: number;
  };
}
```

**Failure modes**

* If `encounter.type !== "combat"`, abort to MapExplorationScene with error.


### BossScene

**Responsibilities**

* Extended version of CombatScene
* Fixed encounter that is the same each run, including special Boss monster
* Return result to MapExploration.

**Allowed side-effects**

* Same as CombatScene

**Required input**

* Same as CombatScene


**Output (back to MapExplorationScene)**

* Same as CombatScene

**Failure modes**

* If `encounter.type !== "boss"`, abort to MapExplorationScene with error.

---

### LootScene

**Responsibilities**

* Present short message and reward loot.
* Apply run-scoped rewards/costs.
* Return to MapExploration.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
  run: RunState;
  encounter: EncounterState; // type must be "loot"
}
```

**Output**

```ts
{
  session: SessionState;
  run: RunState;
  resolution: {
    outcome: "success" | "partial" | "fail";
    loot?: Array<{ /* minimal loot */ }>;
    xp?: number;
  };
}
```


### RestScene

**Responsibilities**

* Present short message and heal units.
* Apply run-scoped rewards/costs.
* Return to MapExploration.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
  run: RunState;
  encounter: EncounterState; // type must be "rest"
}
```

**Output**

```ts
{
  session: SessionState;
  run: RunState;
  resolution: {
    outcome: "success" | "partial" | "fail";
    loot?: Array<{ /* minimal loot */ }>;
    xp?: number;
  };
}
```

---

### WarbandScene

**Responsibilities**

* View units in your warband
* Act as a gateway to Dice Inventory
* Return to Home.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
}
```

**Output**

```ts
{
  session: SessionState;
}
```

---

### UnitDetailsScene

**Responsibilities**

* View individual unit
* Handle unit promotion
* Handle dice equip
* Return to WarbandScene.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
  unitId: number
}
```

**Output**

```ts
{
  session: SessionState;
}
```

---

### DiceInventoryScene

**Responsibilities**

* Display dice that are available to your warband
* Return to WarbandScene.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
}
```

**Output**

```ts
{
  session: SessionState;
}
```

---

### DiceDetailsScene

**Responsibilities**

* View individual die
* Return to DiceInventoryScene.

**Allowed side-effects**

* None for MVP.

**Required input**

```ts
{
  session: SessionState;
  diceId: number
}
```

**Output**

```ts
{
  session: SessionState;
}
```

---

## Transition Matrix (MVP)

BootScene → LandingScene (unauth)
BootScene → HomeScene (auth)
LandingScene → (OAuth redirect) → BootScene
HomeScene → RegionSelectScene
HomeScene → WarbandScene
HomeScene → DiceInventoryScene
WarbandScene → UnitDetailsScene
UnitDetailsScene → WarbandScene
DiceInventoryScene → DiceDetailsScene
DiceInventoryScene → HomeScene
DiceDetailsScene → DiceInventoryScene
RegionSelectScene → MapExplorationScene
MapExplorationScene → CombatScene
MapExplorationScene → LootScene
MapExplorationScene → RestScene
MapExplorationScene → BossScene
MapExplorationScene → HomeScene
CombatScene → MapExplorationScene
LootScene → MapExplorationScene
RestScene → MapExplorationScene
BossScene → MapExplorationScene

Scene list is aligned to the current MVP flow. 

---

## Open Questions (track explicitly)

* What is the minimal ProfileState needed for MVP (warband selection, dice equip, etc.)?

Keep this section up-to-date to prevent silent scope creep.

# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 2 - Region-neutral Boss reward + terminal Exit resolution contracts

**Status:** In Progress
**Priority:** High

#### Problem

Package 1 established canonical Mountains/kobold combat and is approved at `4adff479b4c10af40057f1f93088c0930e5894d8`.

The reusable node-resolution path still contains Farm-only assumptions that prevent a second region from using the same Boss/Exit lifecycle:
- `BossNodeResolutionHandler` requires `event.farm_boss_completed`;
- Boss reward projection requires exactly 16 XP plus the Mountains unlock;
- the strict frontend Boss contract and BattleScene summary are Mountains-specific;
- `ExitNodeResolutionHandler` requires `region.the_farm`, node index `4`, and `isTerminalFarmExit()`;
- RunScene's Exit progress text names the Farm.

Loot and Rest are already sufficiently region-neutral for the current second-region requirement. Do not refactor them merely for symmetry.

#### Goal

Remove only the Farm-specific assumptions required for a second authored region to reuse the accepted Boss reward and successful terminal Exit pipeline.

Do **not** author the Mountains run graph/events/rewards yet. That is Package 3.

#### Backend - Boss

Keep the existing `run_node_type.boss` path and the existing Combat -> reward transaction ownership.

After a victorious Boss:
- require a valid persisted stable `event.*` identity, but do not require the Farm event ID;
- finalize/apply that authored event through the existing `RewardApplicationService`;
- preserve participating-unit ownership/context validation;
- project only player-safe finalized facts needed by the client;
- support the reward types required by Farm and Mountains in this milestone: `unit_xp` and `unlock`;
- do not hardcode XP amount, reward key names, Mountains, or a required unlock;
- require `unit_xp` grants to target participating units and report the actual authored amount/transitions;
- project unlock grants generically by stable `unlock_id` plus `granted | already_owned`;
- return deterministic ordering for unit XP transitions and unlock entries;
- successful Boss rewards may contain XP with zero unlock entries;
- reject unsupported Boss reward types rather than silently dropping them;
- a failed Boss still returns `rewards: null` and applies no reward event.

Use a generic player-safe response shape:

`rewards: { unit_xp: [...], unlocks: [...] }`

Each XP transition remains:
`{ unit_id, amount, level_before, xp_before, level_after, xp_after }`

Each unlock remains:
`{ unlock_id, outcome }`

Do not expose event IDs, reward definition IDs, probability/roll facts, handler config, or other authored-private data.

#### Backend - Exit

Generalize Exit from Farm identity to structural terminal identity:
- node type must be `run_node_type.exit`;
- Exit must carry no encounter or event;
- do not require a specific region ID;
- do not require a specific node index;
- preserve the accepted topology invariant needed by the current run model: zero outgoing edges, exactly one incoming edge, and that parent is a completed Boss node;
- rename/refactor the repository predicate so it is not Farm-named;
- completion still owns the same active run, sets `status = completed` + `ended_at`, unlocks no child nodes, increments revision once, and retains exact idempotent replay.

A structurally incoherent Exit must still fail atomically.

#### Frontend contract/presentation

Generalize the strict Boss parser/type to the backend shape:
- `rewards.unit_xp` is a deterministic list of positive authored XP transitions; do not require amount `16`;
- `rewards.unlocks` is a deterministic list of `{ unlock_id, outcome }`; it may be empty;
- reject duplicate/unsorted identities, invalid outcomes, invalid XP transitions, private extra fields, or rewards on a failed Boss;
- retain strict battle/run/node/revision semantics.

Make BattleScene Boss reward presentation generic:
- show actual XP amounts/level changes;
- summarize generic unlock grants without depending on a Mountains-specific property;
- do not derive progression state locally.

Make RunScene's Exit progress copy region-neutral. The terminal bootstrap reconciliation, interaction lock, GET-only retry after committed Exit, and Camp transition must remain unchanged.

#### Required tests

Backend:
- current Farm Boss still grants exactly its authored 16 XP and `unlock.region.mountains`, now through the generic projection;
- a valid Boss reward result with XP and no unlock is accepted by the generic projection path;
- invalid/missing event identity fails atomically;
- unsupported Boss reward type fails atomically rather than being omitted;
- failed Boss applies no rewards;
- structurally valid non-Farm Exit can complete a run without region/index checks;
- Exit with outgoing children, invalid incoming topology, incomplete/non-Boss parent, event, or encounter fails atomically;
- same-key Exit replay and different-key conflict remain unchanged;
- Farm Boss/Exit regression remains green.

Frontend:
- generic Boss parser accepts Farm's 16-XP + Mountains unlock result;
- parser also accepts a different positive XP amount with `unlocks: []`;
- rejects hardcoded/private/duplicate/incoherent reward facts;
- BattleScene generic summary handles XP-only and XP+unlock Boss results;
- Exit terminal reconciliation/retry tests remain green;
- RunScene contains no Farm-specific Exit message.

#### Verification

Run `npm run verify:package` plus focused Boss/Exit backend/frontend tests. If DB-backed integration coverage is touched, run the applicable Docker backend gate and report skipped counts.

#### Out of scope

- Mountains run-generation definition;
- Mountains events/reward definitions;
- Mountains Loot/Rest/Boss tuning;
- Camp region selector or run-start authorization;
- Swamps unlock;
- Lizard Kin;
- unrelated reward-system expansion;
- broad visual/UI work.

#### Completion

Implement only Package 2. Leave it **In Progress** for architectural review. Do not promote Package 3 or make Mountains startable.

# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future work remains in `agent/MILESTONES.md` and the roadmap until promoted.

## Milestone 3 - Enter Farm

### Address UAT interaction affordances and active-run lock presentation

**Status:** In Progress
**Priority:** High

#### Problem
Milestone 3 passed technical closure, but manual UAT identified two concrete usability defects:

1. Clickable Phaser controls do not consistently change the desktop/fine-pointer cursor, so actionable UI does not reliably advertise clickability.
2. During an active run, the backend correctly rejects participating squad/unit combat-configuration changes, but the Warband UI still lets the player enter or attempt known-invalid configuration and only surfaces a generic command error after submission.

This corrective package fixes those affordances without changing server authority or expanding Milestone 3 gameplay scope.

#### Required Context
Read:
- `AGENTS.md`
- `agent/MILESTONES.md`
- Package 4 active-run lock implementation/contracts
- Package 6/7 RunScene and closure verification
- `frontend/src/app/game/screens/warband-screen.ts`
- `frontend/src/app/game/screens/squad-editor-screen.ts`
- `frontend/src/app/game/screens/unit-configuration-screen.ts`
- `frontend/src/app/game/screens/camp-screen.ts`
- `frontend/src/app/game/scenes/runtime-scenes.ts`
- other current Phaser-owned screens/helpers containing `setInteractive`
- current `GameStore` bootstrap/run state

Do not change backend lock semantics unless a concrete defect is discovered. Client lock state is a usability hint and proactive affordance; backend Package 4 validation remains authoritative for stale-tab/race cases.

#### UAT Finding 1: interactive cursor convention
Establish a consistent current-vNext Phaser interaction convention:
- every genuinely actionable button/control/row/node/pager/confirmation action should use a pointer/hand cursor on fine-pointer desktop input;
- purely informational graphics/text must not advertise clickability;
- controls that are intentionally disabled because of active-run lock or other blocked state must not advertise themselves as actionable;
- using a clear disabled/not-allowed cursor for visibly disabled controls is acceptable if consistent;
- touch behavior must not depend on hover/cursor and must remain unchanged.

Audit current live vNext Phaser surfaces rather than fixing one screen only. At minimum inspect:
- Camp;
- Warband tabs/rows/actions/pagers;
- squad editor formation/roster/actions/confirmations;
- unit configuration tabs/loadout/dice/actions/confirmations;
- RunScene nodes/Return/Abandon/confirmation/retry;
- other live GameScene controls reachable in Milestones 1-3.

Prefer a small shared helper/convention where it removes repeated cursor wiring without introducing a UI framework rewrite.

Do not make non-actionable decorative graphics interactive merely to gain a cursor.

#### UAT Finding 2: derive active-run presentation lock
Use already-authoritative client state to derive a narrow presentation lock hint.

Bootstrap provides:
- `active_run` including participating `squad_id`;
- `active_squad` including formation/unit summaries.

For normal coherent state, this is sufficient to identify:
- the participating squad;
- participating unit IDs.

Do not add a new API request merely to determine known lock state.

A small pure helper or narrow GameStore-derived view is appropriate so Warband, squad editor, and unit configuration do not each invent different lock rules.

The client-derived lock is not security/authority. If local state is stale or another tab changes lifecycle state, backend `active_run_configuration_locked` remains the final authority.

If bootstrap active-run/squad state is internally contradictory, do not guess. Preserve existing integrity/recovery behavior.

#### Warband overview presentation
When an active run exists:
- clearly identify the participating squad as currently in the run / configuration locked;
- participating units should be visibly identifiable as in the active run;
- do not represent participating squad/unit combat configuration as freely editable.

Keep useful read/detail access where allowed behavior exists.

Recommended behavior:
- participating unit row remains openable because unit detail and rename are still useful, but its action label should communicate `VIEW / RENAME`, `IN RUN`, or equivalent rather than implying unrestricted configuration;
- participating squad Edit may remain available because name-only editing is legal, but the UI should make the formation lock clear before entry;
- activating a different squad while a run is active should be visibly unavailable before a request is sent;
- deleting the participating squad should be visibly unavailable before a request is sent;
- creating a new squad remains allowed;
- non-participating squad/unit editing remains available under existing rules.

Do not disable all Warband functionality simply because a run exists.

#### Participating squad editor
When editing the active run's participating squad:
- show a prominent player-facing explanation such as: `This squad is in an active Farm run. Its formation is locked until the run ends. You can still rename the squad.`;
- formation cells must not change placement/clearing;
- roster selection used to change formation must be disabled/non-actionable;
- name input remains editable;
- Save remains available for a genuine name-only change with unchanged formation;
- deleting the participating squad is disabled/prevented before confirmation/API call;
- activating it is already the active-squad no-op and should not imply a meaningful mutation;
- dirty/discard behavior must still work for allowed name changes;
- responsive/orientation behavior must preserve the same lock state.

When editing a non-participating saved squad during an active run:
- formation editing remains legal;
- delete remains subject to existing normal rules;
- activation to replace the participating active squad is proactively disabled because Package 4 will reject it.

Do not alter the draft's committed formation behind the scenes to enforce the lock. Prevent local formation mutation when known locked and let the server remain the final validation layer.

#### Participating unit configuration
When viewing a unit that participates in the active run:
- show a prominent player-facing explanation such as: `This goblin is in an active Farm run. Loadout and dice are locked until the run ends. You can still rename the goblin.`;
- unit detail remains readable;
- name input and rename save remain usable;
- loadout mutation controls are disabled/non-actionable, including add/remove, move order, die-slot selection/assignment where those actions imply configuration changes, and Save Loadout;
- passive/ability/dice information may remain browsable as read-only presentation;
- do not destroy or falsify committed loadout state;
- do not POST a loadout mutation when the known client lock is active.

Non-participating units remain fully configurable.

#### Backend lock error fallback
Improve player-facing handling of backend:

`active_run_configuration_locked`

This error can still legitimately occur because:
- another tab started a run after this screen loaded;
- local bootstrap state is stale;
- a race resolves on the server first.

Map it to understandable text, for example:
- squad: `This squad's formation is locked while it is being used in an active run.`
- unit: `This goblin's loadout and dice are locked while it is participating in an active run.`

Preserve local drafts where appropriate.

Do not expose the raw error code or generic `command failed` text as the primary message.

Do not weaken/remove the backend 409.

#### Allowed naming behavior
Preserve the accepted server contract:
- participating squad **name-only** changes are legal when formation is unchanged;
- participating unit rename is legal.

The proactive client lock must therefore be granular. A blanket disabled editor is incorrect.

#### Start/abandon lifecycle
Lock presentation must respond to authoritative lifecycle state:
- after successful Start, participating squad/unit presentation becomes locked;
- Return/Resume keeps it locked while the run remains active;
- after successful Abandon reconciliation, the client clears active-run state and the same squad/unit configuration becomes editable again without requiring a global profile/bootstrap refresh;
- browser reload while active derives the lock from bootstrap immediately.

Do not add polling.

#### Tests
Add focused coverage proving at minimum:
- actionable live Phaser controls use pointer/hand cursor convention;
- informational/disabled controls do not falsely advertise clickability;
- participating squad is identified from bootstrap active-run/active-squad state;
- participating unit IDs are derived from the participating active formation/state;
- no additional API request is required to derive the lock;
- Warband proactively distinguishes participating locked squad/unit presentation;
- different-squad Activate sends no request when active-run lock is already known;
- participating-squad Delete sends no request when lock is known;
- participating squad editor prevents formation mutation but permits name-only change/save;
- participating squad editor preserves discard/dirty behavior for name edits;
- non-participating squad formation remains editable;
- participating unit configuration prevents loadout/dice draft mutation/submission but permits rename;
- non-participating unit remains configurable;
- `active_run_configuration_locked` fallback produces player-facing lock text and preserves appropriate draft state;
- successful abandon reconciliation removes the proactive lock using existing reconciled state;
- Return/Resume does not remove the lock;
- Compact/Standard/Wide and portrait-gate behavior do not re-enable locked controls accidentally.

Retain all Milestone 2/3 backend lock and lifecycle regressions.

#### Captures / UAT evidence
Generate deterministic captures where useful for:
- Warband during an active run;
- participating squad editor locked formation state;
- participating unit configuration locked loadout state.

Inspect that the player can understand **why** controls are locked and **what remains editable** without first causing a server error.

This is functional UX correction, not the deferred visual overhaul.

#### Scope guard
Do not implement:
- node resolution;
- combat/BattleScene entry;
- rewards;
- Rest/Loot/Boss/Exit mechanics;
- new backend lifecycle rules;
- new API endpoints solely for lock presentation;
- polling/live synchronization;
- final visual overhaul;
- Milestone 4.

Do not broadly redesign Warband.

#### Verification
Run applicable frontend gates, including:
- focused Warband/squad-editor/unit-configuration/RunScene interaction tests;
- existing active-run lock backend regression if backend files are touched;
- full frontend suite;
- production frontend build;
- bundle check;
- deterministic affected-screen captures;
- `npm run llm:check` / docs checks if planning/docs change;
- `git diff --check`.

Real-stack verification should at least prove that proactive UI prevention and the existing backend lock agree for an active run, and that abandon re-enables the controls.

Do not claim an unexecuted gate passed.

#### Review State
When complete:
- leave this corrective package **In Progress**;
- do not mark Milestone 3 UAT passed;
- do not promote Milestone 4.

Architectural review will determine whether the correction is ready for the user's focused UAT recheck.

#### Final Report
Report:
1. commit SHA;
2. cursor convention/helper and audited live surfaces;
3. client lock derivation;
4. Warband overview lock presentation;
5. participating squad editor behavior;
6. participating unit configuration behavior;
7. backend lock-error fallback messaging;
8. post-abandon unlock behavior;
9. tests/gates run;
10. capture paths/visual findings;
11. unresolved concern, if any.

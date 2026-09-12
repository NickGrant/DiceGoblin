# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish squad commands and active-squad bootstrap integration

**Status:** In Progress
**Priority:** High

#### Problem
Warband persistence, canonical authored content, and authoritative read APIs are now approved. Players still cannot create, edit, activate, or delete saved squads through the vNext command boundary, and bootstrap still reports `active_squad: null` even when authoritative state contains an active squad. Implement complete authoritative squad mutations plus bootstrap hydration without starting Phaser squad UI or unit/loadout mutation.

#### Required Context
- `documentation/07-development-path/vnext-api-contract-model.md` — command/query authority, mutation responses, `player_revision`, CSRF/idempotency rules
- `documentation/07-development-path/vnext-endpoint-inventory.md` — accepted squad endpoints and whole-aggregate replacement
- `documentation/07-development-path/vnext-backend-internal-architecture.md` — controller/application/repository boundaries and transaction ownership
- `documentation/07-development-path/vnext-storage-model.md` — squads, `squad_units`, nullable `user_state.active_squad_id`
- `documentation/02-systems/warband-and-formation.md` — nine positions, multiple saved squads, active-squad semantics, future run lock
- Current Package 3 Warband read repositories/queries/controllers and controlled fixture
- Current `GameBootstrapQuery`, runtime bootstrap parser/GameStore tests, and Milestone 1 startup contract

Inspect prototype Team behavior only for useful edge cases. New code uses `squad` terminology and vNext contracts exclusively.

#### Accepted Command Surface
- `POST /api/v1/squads` creates one saved squad from a complete submitted configuration.
- `PUT /api/v1/squads/:squadId` atomically replaces that owned squad's complete editable configuration.
- `POST /api/v1/squads/:squadId/activate` sets an owned saved squad active.
- `DELETE /api/v1/squads/:squadId` deletes an owned saved squad subject to active-squad validation.
- All mutations require authenticated session + normal CSRF protection.
- Commands return the authoritative affected squad/active-squad state and current `player_revision`; no command performs a global profile refresh.

#### Squad Configuration Contract
- Use one explicit complete request shape for create/update. Prefer the already-established nine-position read shape: `name` plus `formation` containing exactly positions `0` through `8`, each `null` or one unit instance ID.
- Validate request shape strictly. Reject missing/extra positions, positions outside `0-8`, malformed/non-positive unit IDs, duplicate unit IDs within one squad, empty/blank names, and names exceeding the accepted persistence length.
- Empty positions and a completely empty squad are valid unless an already-accepted product rule says otherwise.
- Every occupied unit must be an active unit owned by the authenticated player. Non-owned, missing, or terminal units are invalid and must not leak foreign state.
- A unit may appear in multiple different saved squads.
- Replace the whole formation transactionally rather than exposing per-position patch endpoints.

#### Active Squad Semantics
- `user_state.active_squad_id` is nullable only while the player has no saved squad.
- Creating the player's first squad must make it active in the same transaction. Creating additional squads must not silently change the existing active squad.
- Activation is explicit and idempotent: activating the already-active squad returns authoritative current state without creating another logical change/revision bump.
- Do not permit deleting the active squad while another saved squad remains; require the player to activate a replacement first. Deleting the only remaining active squad is allowed and leaves `active_squad_id = null` because no squad remains.
- Editing an active squad keeps it active.
- Deleting a non-active squad does not change the active squad.
- Cross-owner/corrupt active-squad state is an integrity failure, not a state the command silently repairs.

#### Transaction and Revision Rules
- Each command owns exactly one application-level transaction covering validation that must be protected from races, durable mutation, active-squad changes where applicable, and `player_revision` increment.
- Lock the relevant player state/squad rows as needed so complete replacement/activation/deletion cannot interleave into invalid state.
- Increment `player_revision` exactly once for each successful command that changes durable player state.
- Do not increment on rejected commands, rolled-back commands, GETs, or genuine no-op activation.
- Return the resulting authoritative state from the command; do not require a follow-up profile/bootstrap refresh.

#### Creation Idempotency
`POST /api/v1/squads` creates a durable asset and therefore requires the conventional `Idempotency-Key` request header under the accepted API rules. Validate the supplied key using a small bounded opaque-string contract and include `Idempotency-Key` in dev CORS allowed headers where cross-origin local development requires it. Implement the smallest vNext idempotency persistence/mechanism appropriate to this concrete command now; do not build a generic event-sourcing framework. Repeating the same accepted create request under the same authenticated user + idempotency key must return the previously created authoritative result without creating duplicate squads or incrementing `player_revision` twice. Reusing the same key for a different create payload must fail safely. Idempotency ownership is per authenticated user; one user's key must not collide with another user's key. Add only the persistence needed for this concrete command and document/test its retention semantics at the level necessary for correctness.

#### Future Run Lock Boundary
- Do not fabricate active-run persistence or locking before Milestone 3 creates runs.
- Structure the squad command/application boundary so a future run-lock policy can be inserted before mutation without redesigning endpoint contracts or putting rules in controllers/repositories.
- Do not add placeholder run tables or fake `hasActiveRun=false` services merely to claim support.

#### Bootstrap Integration
- `GET /api/v1/game/bootstrap` must hydrate `active_squad` from authoritative persisted state when `user_state.active_squad_id` is non-null.
- The active squad payload should contain squad ID/name, normalized nine-position formation, and compact active unit summaries sufficient for initial Camp/game state. Reuse the same summary semantics as the approved unit/squad reads rather than creating a second inconsistent model.
- `active_squad` remains `null` for a fresh account with no squads.
- A missing, non-owned, terminal-unit, malformed-position, or otherwise corrupt active squad is a bootstrap integrity failure. Bootstrap must not repair it on GET.
- Bootstrap remains read-only and must not increment `player_revision`.
- Update the framework-neutral frontend bootstrap contract/parser/GameStore startup tests so a valid non-null active squad is accepted and retained. Do not build or visually render the squad in Phaser yet; Package 6 owns Warband UI/read integration.

#### Error and Ownership Behavior
- Non-owned/missing squad IDs must use a non-disclosing not-found response.
- Invalid configuration uses a narrow validation/domain error; persisted corruption uses the existing narrow Warband integrity behavior or an equally consistent bootstrap-integrity mapping.
- Do not return another player's unit/squad identity in error payloads.
- Do not expose raw SQL/exception text.

#### Tests
Add focused real-MySQL integration coverage proving at minimum:
- auth and CSRF rejection for every mutation;
- create with exact nine-position normalization and owned active units;
- first created squad becomes active; second create preserves current active squad;
- create idempotency prevents duplicate assets/revision increments, rejects conflicting key reuse, and scopes identical keys independently by authenticated user;
- missing/malformed/oversized idempotency keys are rejected before mutation;
- invalid/malformed formation/name is rejected atomically;
- non-owned/terminal/missing units cannot be inserted and do not leak state;
- PUT atomically replaces name + complete formation and rolls back on failure;
- activation changes active squad and increments revision once; repeated activation is a no-op for revision;
- non-owned squad mutation/activation/deletion is non-disclosing;
- deleting a non-active squad preserves active squad;
- deleting the only active squad leaves null;
- deleting an active squad while another squad remains is rejected until another is activated;
- player revision changes exactly once per real mutation and not on failure/no-op;
- cross-owner/corrupt persisted active state fails safely;
- bootstrap returns null for fresh accounts and the normalized authoritative active squad for populated state;
- bootstrap active squad includes only owned active units and fails safely on corrupt state;
- frontend bootstrap parsing accepts valid non-null active squad and rejects malformed shapes without changing startup revision/content compatibility behavior;
- existing Package 3 reads and fixture behavior remain valid.

Use the repository-supported real MySQL/Docker path; do not satisfy command behavior exclusively with mocked repositories.

#### Explicitly Out of Scope
- Unit rename or loadout/dice-binding mutation — Package 5.
- Phaser Warband navigation/read UI — Package 6.
- Phaser squad editor — Package 7.
- Promotion/progression, production starter onboarding, Shop, Academy, Wrong Machine, run persistence/locks, combat, rewards, or Milestone 3.
- Final visual/UI overhaul.

#### Completion
Run applicable context/docs checks, focused backend unit/integration tests, Docker/MySQL backend verification, frontend bootstrap/runtime tests, content validation where required, and production frontend build if the bootstrap contract changes compiled client code. Exercise the real HTTP command/bootstrap path where practical. Report exact request/response shapes, idempotency semantics, revision behavior, bootstrap shape, verification results, and any unresolved concern.

Leave this package **In Progress** for architectural review. Do not promote or begin Package 5 in the same coding-agent change.

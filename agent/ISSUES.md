# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 2 - Warband

### Establish authoritative Warband reads and controlled fixtures

**Status:** In Progress
**Priority:** High

#### Problem
Warband persistence and canonical authored definitions are now approved, but there is no live vNext domain boundary that reads a player's owned units, dice, or saved squads. Establish the authoritative lazy read APIs that Phaser will consume later and a controlled development/UAT fixture path that creates representative real MySQL Warband state using canonical authored IDs. Keep production registration empty; this package is not onboarding, squad mutation, or Phaser UI.

#### Required Context
- `documentation/07-development-path/vnext-api-contract-model.md` — query/command boundary, lazy domains, authoritative state
- `documentation/07-development-path/vnext-endpoint-inventory.md` — accepted unit/dice/squad query contracts
- `documentation/07-development-path/vnext-backend-internal-architecture.md` — controller/application/repository responsibilities
- `documentation/07-development-path/vnext-storage-model.md` — approved Warband persistence
- `documentation/07-development-path/vnext-authored-content-model.md` — ContentRegistry/stable-ID authority
- `documentation/02-systems/warband-and-formation.md`
- `documentation/02-systems/ability-loadouts-and-dice-binding.md`
- `documentation/02-systems/unit-stat-advancement.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- Current `GameBootstrapController`/query/service-factory patterns, router, auth/session/CSRF infrastructure, Warband baseline tables, canonical Warband content, and relevant tests

Inspect prototype Unit/Dice/Team repositories and old API behavior only for useful query/ownership edge cases. Do not adapt their catch-all/profile/team contracts into vNext.

#### Acceptance Criteria
- Add authenticated, read-only vNext query endpoints for `GET /api/v1/units`, `GET /api/v1/units/:unitId`, `GET /api/v1/dice`, and `GET /api/v1/squads` using the accepted vNext `squad` terminology.
- Keep controllers thin. Queries/application services assemble authoritative responses; repositories perform persistence only. Do not rebuild a catch-all gameplay service or profile payload.
- Query endpoints are read-only: no provisioning, repair, starter grants, profile synchronization, or mutation on GET.
- All collection/detail reads are scoped to the authenticated `users.id`. A player cannot discover another player's unit detail by guessing an ID.
- `GET /api/v1/units` returns compact owned-unit summaries appropriate for a roster/selector, referencing canonical authored IDs rather than duplicating authored catalogs. Include instance identity/name/current unit type/kin/level/XP or other small mutable fields actually needed by the Warband roster; lifecycle policy should prevent terminal/non-owned instances from masquerading as usable units.
- `GET /api/v1/units/:unitId` returns the full current mutable detail needed by the eventual unit-configuration screen: instance identity/current type/kin/name/level/XP/lifecycle, promotion history, permanently owned abilities, committed ordered ability loadout, and exact ability-slot -> die-instance bindings. Do not expose server-only authored handler/effect configuration in this response; static authored presentation remains resolved through the client projection.
- Do not invent promotion eligibility/options in this package. `GET /units/:unitId/promotion-options` remains deferred with Milestone 8 unless a minimal explicit empty contract is already required by accepted current consumers; do not add it merely for symmetry.
- `GET /api/v1/dice` returns compact active owned die instances including instance ID, size, profile ID, lifecycle state, and only the small mutable/equipment summary that is genuinely useful for configuration. Material/rarity/aspects come from the authored profile projection rather than being duplicated from MySQL/API.
- If dice read responses report equipment usage, derive it authoritatively from current bindings and keep the shape useful for later loadout validation; do not invent a separate mutable equipped flag.
- `GET /api/v1/squads` returns all saved squads owned by the player with squad identity/name and normalized positions `0-8` in a stable response shape. Empty positions should be represented deliberately and consistently. The response may indicate which squad ID matches `user_state.active_squad_id`, but this package does not implement activation/mutation or bootstrap active-squad hydration.
- Queries that encounter persisted authored IDs missing from the current ContentRegistry should fail as an integrity problem rather than silently fabricate definitions. Choose a narrow consistent error contract and test it; do not repair rows on read.
- Keep authored static definitions out of API payloads where the browser-safe projection already owns them. API state should reference stable IDs plus mutable player-instance state.
- Do not increment `player_revision` for reads.
- Preserve current bootstrap behavior for this package. Package 4 owns active-squad bootstrap integration.

#### Controlled development/UAT fixture
- Add one controlled development/test-only path capable of creating representative real Warband state for the authenticated test/UAT user using the canonical IDs established in Package 2.
- Prefer a narrow authenticated debug/fixture operation or equally practical repository-supported development command that can be exercised by real-stack automated verification and manual UAT without editing production account-creation behavior.
- If implemented as an HTTP mutation, it must be environment-gated so it is unavailable in production, require the normal authenticated session, and use normal mutation/CSRF protections. It is an operational fixture surface, not a public gameplay endpoint.
- The fixture creation must be one explicit transaction and deterministic/repeat-safe. Re-running it must not accumulate arbitrary duplicate units/dice/squads. A deliberate replace/reset-to-known-fixture behavior is acceptable when clearly restricted to dev/test.
- Fixture state should be representative enough to exercise all Package 3 reads and later Milestone 2 configuration UI: multiple units using real canonical unit/kin/ability IDs, multiple dice using real canonical profiles/sizes, permanent ability ownership, representative ordered loadouts/dice bindings, and at least one saved squad with several occupied formation positions.
- Use the five established tier-one Warband families where useful for breadth rather than fabricating new authored types specifically for fixtures.
- Do not make fixture creation the production starter-pack implementation. Normal registration must still create no units, dice, or squads. Production onboarding/provisioning remains Milestone 12.
- Fixture tooling must not become available merely because `APP_ENV` is mis-capitalized or omitted; use an explicit safe environment policy and cover production rejection.

#### Ownership and integrity
- Repositories/query code must enforce ownership by user scope, not trust client-supplied IDs.
- Preserve the Package 1 choice that MySQL stores authored IDs without SQL catalogs. Validate fixture-created authored IDs through ContentRegistry/application logic rather than adding SQL catalog foreign keys.
- Do not add cross-user relationship constraints by denormalizing ownership into every relation merely for these reads; command-layer ownership validation remains Packages 4/5.
- Treat dangling/cross-inconsistent persisted data encountered by a query as an integrity failure where it would otherwise leak or misrepresent another user's state.
- Do not expose another user's die identity through a malformed cross-owner binding. Query assembly must remain scoped and integrity-safe even if bad data exists.

#### Tests
- Add focused repository/query/controller/integration coverage for successful empty and populated collection reads; full unit detail; stable squad position ordering/empty-position representation; dice binding/equipment summary where present; auth rejection; guessed cross-user unit IDs; terminal lifecycle filtering/representation; dangling authored-ID integrity failures; and no read-side writes or `player_revision` changes.
- Add fixture tests proving environment gating, authentication/CSRF if HTTP-based, deterministic/repeat-safe behavior, canonical-ID usage, transaction rollback on failure, and that ordinary registration remains empty.
- Add a real MySQL/Docker integration path for representative persisted Warband data. Do not satisfy this package exclusively with mocked repositories.
- Preserve Milestone 1 bootstrap/auth/content tests.

#### Explicitly Out of Scope
- Squad create/update/activate/delete commands or bootstrap active-squad hydration — Package 4.
- Unit rename or loadout mutation — Package 5.
- Phaser/API-client/GameStore Warband UI/read integration — Package 6.
- Promotion options/transactions, Academy, XP tuning, starter onboarding, Shop, sale/salvage, Wrong Machine, run locks, combat, rewards, or Milestone 3.
- Production registration grants.

#### Completion
Run applicable context/docs/content checks plus focused backend unit/integration tests and the repository-supported Docker/MySQL path. Exercise the live HTTP reads against fixture-populated real state where practical. Report the exact endpoint shapes, fixture invocation/gating semantics, canonical fixture IDs used, verification commands/results, and any integrity behavior chosen. Leave this package **In Progress** for architectural review. Do not promote or begin Package 4 in the same coding-agent change.

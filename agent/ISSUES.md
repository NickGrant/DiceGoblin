# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Implement vNext game bootstrap query

**Status:** Open
**Priority:** High

#### Problem
Implement the authenticated, read-only bootstrap query that gives the future Phaser runtime the authoritative Milestone 1 state needed to enter Camp, without recreating the prototype catch-all profile contract or prematurely building later gameplay domains.

#### Required Context
- `documentation/07-development-path/vnext-api-contract-model.md` — Game Bootstrap, Query/Command Boundary, Player Revision
- `documentation/07-development-path/vnext-endpoint-inventory.md` — `GET /api/v1/game/bootstrap`
- `documentation/07-development-path/vnext-backend-internal-architecture.md` — Controllers, Queries/Read Models, ContentRegistry
- `documentation/07-development-path/vnext-storage-model.md` — account/auth and `user_state`
- `documentation/07-development-path/vnext-energy-model.md` — regeneration/overcap semantics
- `documentation/07-development-path/vnext-authored-content-model.md` — authored balance/config authority
- Current vNext auth/session, `user_state`, ContentRegistry, composition, and tests touched by the implementation

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- Add authenticated `GET /api/v1/game/bootstrap` under the existing cookie/session model. Unauthenticated requests return the established unauthorized API response; do not add another authentication mechanism.
- Keep the controller thin. Implement a purpose-built application query/read model that composes account identity, mutable `user_state`, authored configuration, content revision, and session/CSRF metadata. Do not resurrect `/profile` or put bootstrap assembly into a catch-all gameplay service.
- For Milestone 1, return the implemented subset only: account ID/display name/role; Teeth; Raw Chaos; effective current Energy; calculated normal Energy maximum; Energy regeneration timing needed for presentation; `player_revision`; session/CSRF metadata as required by the future Phaser API client; and the server canonical content revision.
- Represent accepted-but-not-yet-implemented bootstrap domains explicitly as empty/null rather than creating their persistence early: unlock/progression summary empty; active squad null; active run null. Do not add unit, dice, squad, run, unlock, Codex, objective, inventory, Shop, Academy, Wrong Machine, reward, or battle storage for this package.
- Bootstrap is a query. It must not provision missing `user_state`, advance persisted Energy timestamps/current values, grant anything, increment `player_revision`, or otherwise mutate durable player state. An authenticated user missing required `user_state` is an integrity/provisioning error, not a reason for GET-side repair.
- Move the remaining baseline Energy tuning required by the query into canonical authored configuration rather than SQL or environment variables. Preserve existing behavior unless intentionally changed later: normal/base maximum 50 and regeneration rate 12 Energy/hour. Starting Energy remains 50. Do not persist `energy_max` or regeneration rate in `user_state`.
- Calculate the bootstrap Energy view from persisted `energy_current` + `energy_last_regen_at` and authored Energy rules without writing during the GET. Natural regeneration caps at normal max; an already-overcapped current value is preserved and does not regenerate further. Keep this calculation reusable/deterministic and outside controllers/repositories.
- Do not introduce Energy-cap upgrade persistence or prototype feature-unlock tables merely to calculate max in Milestone 1. The current normal max is the authored base max; later permanent progression may modify the calculation when its owning package exists.
- Return the same canonical content revision represented by the generated client projection. Do not implement client-side revision comparison/mismatch blocking yet; that belongs to Phaser startup.
- Preserve registered auth/session/health behavior and the approved fresh database/content architecture. Do not re-register prototype gameplay routes.
- Avoid repeatedly parsing the authored catalog within one request and avoid making content-independent `/session` depend on loading ContentRegistry. Establish a small composition boundary that can share/inject the validated registry into content-dependent operations without introducing a dependency-injection framework or broad container rewrite.
- Add focused automated coverage for unauthorized bootstrap; fresh authenticated account bootstrap; account/user-state mapping; authored content revision; read-only behavior; missing-user-state integrity behavior; Energy regeneration calculation including cap and overcap cases; and explicit empty/null later-domain representations.
- Do not implement Phaser runtime/client state, Angular `/game` host changes, Camp rendering, client content compatibility enforcement, later collection queries, or gameplay mutations in this package.

#### Completion
Run applicable backend/content/context gates from `agent/QUALITY_GATES.md`. Leave this package active for architectural review; do not promote or begin the persistent Phaser runtime package in the same change.
# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 1 - Walking Skeleton

### Establish the fresh vNext database baseline

**Status:** In Progress
**Priority:** High

#### Problem
Create the smallest clean MySQL/auth/player-state foundation needed for the walking skeleton without replaying prototype migrations or implementing later gameplay domains.

#### Required Context
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md` — account/auth, database/repository sections
- Current auth/session/schema source and tests being replaced

Load other decision docs only if implementation reaches their domain.

#### Acceptance Criteria
- An empty MySQL database initializes through a vNext baseline without replaying the prototype migration chain.
- No SQL-authored gameplay catalogs are recreated (regions, unit types, dice definitions/affixes, enemies, loot tables, run-pattern catalogs, etc.).
- Persist only accepted account/auth data plus minimum `user_state` for the walking skeleton.
- `user_state` includes Teeth, Raw Chaos, current Energy, Energy regeneration timing, and monotonic `player_revision`; derived Energy max is not persisted.
- New-account provisioning creates required player state through an authoritative mutation path. Read endpoints do not create missing gameplay state.
- Preserve applicable password hashing/verification, reset-token safety, Discord OAuth state protection, session-ID rotation, CSRF rotation/validation, and session-expiration behavior while conforming storage to the accepted `users` + local credentials + external identities model.
- Authentication/session behavior required to reach an authenticated `/game` remains functional against the fresh schema.
- Automated backend/database verification proves fresh initialization plus basic account/player-state persistence.
- Do not add unit, dice, squad, run, battle, reward, objective, Codex, inventory, Shop, Academy, Wrong Machine, starter-pack, or other gameplay persistence in this package.

#### Completion
Run applicable database/backend/context gates from `agent/QUALITY_GATES.md`. Leave this package active for architectural review; do not promote or begin the next package in the same change.

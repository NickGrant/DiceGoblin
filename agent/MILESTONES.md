# MILESTONES FILE
----
Active vNext milestones only. The full 0-14 roadmap is maintained in `documentation/07-development-path/vnext-game-overhaul.md`.

## Milestone 1 - Walking Skeleton

**Status:** Active

Establish the smallest real vertical path through the vNext architecture: authenticated Angular host -> persistent Phaser runtime -> client-safe authored content -> PHP bootstrap -> MySQL-backed player state -> minimal Phaser Camp.

### Exit Criteria
- Prototype source relevant to vNext has been inspected/classified so useful algorithms/infrastructure are deliberately preserved or adapted. **Complete:** see `documentation/07-development-path/vnext-prototype-code-disposition.md`.
- A fresh vNext database can support the walking skeleton without replaying the prototype migration chain.
- The server can load and validate canonical authored JSON needed by the slice.
- Phaser receives only an allowlisted client-safe content projection.
- `/game` mounts Phaser through Angular without Angular orchestrating gameplay state.
- Phaser boot/loading reaches `GameScene` and displays a minimal Camp from authoritative bootstrap state.
- The 1600x900 reference layout, landscape-only mobile orientation gate, and basic responsive behavior work.
- Client/server content-version mismatch is detected safely.
- Relevant fresh-DB, backend, frontend, content-projection, and responsive verification gates pass.

### Remaining Execution Order
Milestone 1 is decomposed into dependency-ordered packages in `agent/ISSUES.md`:

1. Establish the fresh vNext database baseline.
2. Establish the authored content registry and client projection.
3. Implement the vNext game bootstrap query.
4. Mount the persistent Phaser runtime at `/game`.
5. Implement Phaser startup state and the compatibility gate.
6. Render the minimal authoritative Camp.
7. Implement responsive landscape host behavior.
8. Verify and close the walking skeleton.

Work one package at a time. Do not begin Warband, units/dice/squads, Farm/run creation, combat, economy, progression, or onboarding work merely because later contracts mention those domains.

### Reuse Rule
Before replacing a substantive prototype subsystem in this or any later milestone, inspect its current implementation and apply **Keep**, **Adapt**, **Rebuild**, or **Retire** guidance from `vnext-prototype-code-disposition.md`. Accepted vNext architecture controls the destination and boundaries, but useful algorithms, tests, assets, infrastructure, and domain behavior should be carried forward deliberately where they still fit.

Do not create a permanent in-tree legacy-code archive. Git history is the archive. Keep still-needed prototype code in place until its replacement is proven, then remove obsolete paths as part of the milestone/package that supersedes them.

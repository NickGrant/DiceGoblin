# MILESTONES FILE
----
Active vNext milestones only. The full 0-14 roadmap is maintained in `documentation/07-development-path/vnext-game-overhaul.md`.

## Milestone 1 - Walking Skeleton

**Status:** Next

Establish the smallest real vertical path through the vNext architecture: authenticated Angular host -> persistent Phaser runtime -> client-safe authored content -> PHP bootstrap -> MySQL-backed player state -> minimal Phaser Camp.

### Exit Criteria
- A fresh vNext database can support the walking skeleton without replaying the prototype migration chain.
- The server can load and validate canonical authored JSON needed by the slice.
- Phaser receives only an allowlisted client-safe content projection.
- `/game` mounts Phaser through Angular without Angular orchestrating gameplay state.
- Phaser boot/loading reaches `GameScene` and displays a minimal Camp from authoritative bootstrap state.
- The 1600x900 reference layout, landscape-only mobile orientation gate, and basic responsive behavior work.
- Client/server content-version mismatch is detected safely.
- Relevant fresh-DB, backend, frontend, content-projection, and responsive verification gates pass.

### Planning Requirement
Before implementation begins, decompose this milestone into dependency-ordered work packages. Do not start by porting unrelated prototype gameplay systems.

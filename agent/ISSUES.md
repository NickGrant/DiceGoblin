# ISSUES FILE
----
Active vNext issues only. Prototype/demo issues were intentionally removed from this branch's active context; Git history retains them.

## Milestone 1 - Walking Skeleton

### Decompose the walking skeleton

**Milestone:** Milestone 1 - Walking Skeleton
**Status:** Open
**Priority:** High

#### Problem
Milestone 1 crosses database, authored content, PHP infrastructure/bootstrap, Angular hosting, and Phaser runtime concerns. Implementation should be divided into dependency-ordered vertical work packages before coding begins so the project does not recreate a horizontal rewrite.

#### Acceptance Criteria
- Identify the minimum clean-schema subset needed by bootstrap and Camp.
- Identify the first canonical authored JSON required by the slice and its client-safe projection.
- Define the minimum backend infrastructure and bootstrap work needed for the slice.
- Define the Angular `/game` host responsibilities without preserving Angular gameplay orchestration.
- Define the Phaser boot/runtime/GameScene work and minimal Camp presentation.
- Include content-version, fresh-database, responsive/orientation, and verification work in the appropriate packages.
- Keep later Warband, Farm, combat, economy, progression, and onboarding work out of Milestone 1 unless it is genuinely required by the skeleton.

# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Balance Simulation and Telemetry

### BST-002: Add progression-goal simulation reports

**Milestone:** Balance Simulation and Telemetry
**Status:** Open
**Priority:** Medium

#### Problem

Required unlocks such as region access, promotions, Wrong Machine recovery, and Pig Kin reconstruction need pacing checks that look beyond a single run. The team needs p50 and p90 estimates for how long common player goals take under defined assumptions.

#### Acceptance Criteria

- Add a progression simulation mode for named goals.
- Support goals for first promotion, next-region unlock, Wrong Machine unlock, and Pig Kin reconstruction.
- Report p50, p75, p90, worst-observed, and failure/shortfall reasons.
- Include material and Raw Chaos shortfall reporting for Pig Kin.
- Make assumptions explicit in the output, including strategy/profile fixture and sample count.

### BST-003: Add balance report workflow to PR validation

**Milestone:** Balance Simulation and Telemetry
**Status:** Open
**Priority:** Medium

#### Problem

Once simulations exist, balance-affecting PRs need a lightweight workflow for comparing before/after behavior without turning every change into a research project.

#### Acceptance Criteria

- Add npm scripts or documented commands for common simulation suites.
- Define a PR summary format for balance changes.
- Include before/after report examples.
- Keep reports small enough to paste into PR descriptions.
- Update testing strategy docs to reference the implemented command names.

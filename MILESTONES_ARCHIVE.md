# MILESTONES ARCHIVE
----

## Purpose
- Historical record for completed or otherwise inactive milestones moved from `MILESTONES.md`.
- Preserve milestone resolution history without bloating active execution context.

---
name: Milestone 7 - Documentation Integrity
status: complete
execution_window: open
is_current: yes
issues:
  - Align frontend scene/state contract docs with current implemented scene flow
  - Extend style-guide normalization pass to legacy overview and glossary docs
  - Add documentation QA checklist for code-contract drift checks
  - Clarify archive-loading policy for milestone history in AGENTS startup behavior
  - Reconcile combat progression wording across encounter/combat/reward docs
description: |
  Resolve active documentation drift and establish repeatable doc QA so execution docs remain reliable before resuming feature milestones.
Resolution: Completed documentation drift pass across architecture/overview/system docs, added QA checklist workflow, and aligned archive-loading policy and progression wording.

---
name: Milestone 8 - QA Test Backfill and Strategy
status: complete
execution_window: open
is_current: yes
issues:
  - Establish backend API integration test harness (PHPUnit + fixtures)
  - Establish frontend interaction test harness for Phaser scenes
  - Add API contract regression tests for session/profile/run payload invariants
  - Add negative-path integration tests for run creation and mutation CSRF enforcement
  - Add backend integration tests for team create/activate/update with CSRF and ownership rules
  - Add idempotency regression tests for run node resolve and battle claim
  - Add frontend interaction tests for warband placement and save behaviors
  - Create repository testing strategy and verification matrix document
description: |
  Stand up automated QA infrastructure and baseline regression coverage while defining an explicit testing strategy that governs verification expectations for future feature work.
Resolution: Completed QA harness setup across backend/frontend, added API contract and endpoint mutation security coverage, validated resolve/claim idempotency behavior, and documented repository-wide testing strategy.

---
name: Milestone 9 - Product and Backlog Governance
status: complete
execution_window: open
is_current: yes
issues:
  - Establish post-QA milestone execution order and current milestone selection policy
  - Define milestone entry/exit criteria template and apply to active milestones
  - Add cross-milestone dependency map for run, combat, UX, and QA streams
  - Add issue dependency metadata for blocked-by relationships
  - Normalize active issue metadata fields to template standards
  - Resolve empty milestone placeholders or repopulate with scoped issues
  - Define backlog triage cadence and status-transition policy
  - Add automated lint/check for ISSUES and MILESTONES schema consistency
  - Add dependency metadata policy for Milestones 4-6 execution sequencing
  - Resolve MVP XP-source contradiction between encounter and progression specs
  - Add explicit squads-vs-teams terminology compatibility note in architecture docs
  - Add required metadata headers to high-impact documentation files
  - Remove template-entry parsing ambiguity from active milestones file
  - Archive or remove deprecated documentation worklist artifact
description: |
  Tighten roadmap governance, milestone clarity, and backlog hygiene so future delivery lanes stay executable and auditable.
Resolution: Completed governance hardening for execution order, dependency modeling, backlog schema validation, issue metadata consistency, and documentation metadata/terminology hygiene; milestone artifacts are now structured for predictable cross-milestone execution.

---
name: Milestone 14 - Post-Governance Simplification and Readiness
status: complete
execution_window: open
is_current: yes
issues:
  - Select and open the next current milestone after Milestone 9 completion
  - Add a single-command workflow entrypoint for backlog validation
  - Add CI gate to run backlog schema validation on documentation/backlog changes
  - Trim active backlog and milestone docs to configured context guardrails
description: |
  Capture role-based simplification and readiness follow-ups discovered after completing milestone governance hardening.
Resolution: Opened Milestone 2 as the current lane, added local/CI backlog validation entrypoints, and split deferred planning inventory into dedicated backlog files to restore active-doc guardrail compliance.
---
name: Milestone 2 - Server-Side Battle Resolution
status: complete
execution_window: open
is_current: yes
issues:
  - Replace placeholder run node resolution with deterministic combat engine integration
  - Implement non-placeholder reward and XP application on battle claim
description: |
  Deliver deterministic battle resolution and real claim/reward flow with idempotency guarantees.
Resolution: Completed deterministic node resolution and non-placeholder claim reward/XP application, with integration verification via full backend test suite (`phpunit`, 13 passing tests including deterministic resolve and idempotent claim XP coverage).

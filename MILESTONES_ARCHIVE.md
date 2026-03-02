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

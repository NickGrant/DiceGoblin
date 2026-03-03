# Backlog Triage Policy
----

Status: active
Last Updated: 2026-03-02
Owner: Product + Engineering
Depends On: `ISSUES.md`, `MILESTONES.md`, `AGENTS.md`

## Purpose
- Keep issue state reliable and actionable.
- Define minimum triage cadence and transition rules.

## Cadence
- Weekly triage pass:
  - review all `in-progress`, `blocked`, and high-priority `unstarted` issues.
  - update stale metadata (`owner`, `updated`, dependency fields).
- Milestone boundary triage:
  - run at milestone open and before milestone close.
- Event-driven triage:
  - run immediately after major contract/doc updates that can invalidate issue sequencing.

## Status Transition Policy
- `unstarted` -> `in-progress`
  - when execution begins and issue is actively being implemented.
- `in-progress` -> `blocked`
  - when external dependency or unresolved prerequisite prevents progress.
- `blocked` -> `in-progress`
  - when blocker is cleared and implementation resumes.
- `in-progress` -> archived (`complete`)
  - when implementation and verification are done and resolution is recorded.
- any status -> `reopened`
  - when a previously completed issue regresses or is found incomplete.

## Metadata Policy
- `owner` should be explicit before long-running implementation.
- `updated` must reflect latest meaningful issue-state change.
- use `blocked_by` and `enables` for direct dependency edges when sequencing risk exists.

## Hygiene Rules
- Keep active file focused on actionable issues only.
- Move completed issues to `ISSUES_ARCHIVE.md` immediately after resolution logging.

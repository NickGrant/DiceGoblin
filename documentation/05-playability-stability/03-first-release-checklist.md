# First Pig Kin Demo Release Checklist
----

Status: active
Last Updated: 2026-07-30
Owner: Product + QA + Engineering
Depends On: `documentation/05-playability-stability/00-release-gate-criteria.md`, `documentation/05-playability-stability/01-critical-path-playtest-script.md`, `documentation/CHANGELOG.md`

## Purpose

Track closeout work for the formal demo release that ends at first Pig Kin creation.

## Product Gates

- [ ] Active roadmap points to the first Pig Kin demo release target.
- [ ] Active tracker contains only demo-release work.
- [ ] Post-demo biome, kin, affix, and Library expansion work is parked in backlog.
- [ ] Known accepted demo limitations are documented.

## Automated Gates

- [ ] `npm.cmd run llm:check`
- [ ] `npm.cmd run backlog:validate`
- [ ] `npm.cmd run docs:lint`
- [ ] Relevant Docker backend tests
- [ ] Relevant frontend tests
- [ ] `npm.cmd run build:frontend`
- [ ] `git diff --check`

## Manual Gates

- [ ] Fresh-account first Pig Kin demo script completed.
- [ ] Required dialogue loads through first Pig Kin creation.
- [ ] Objectives guide the player to the next demo action.
- [ ] Chaos, hazards, shrines, consumables, and rewards are readable enough for demo.
- [ ] Wrong Machine first-reconstruction flow is understandable.
- [ ] Pig Kin ownership and granted unit persist after refresh.
- [ ] Desktop and mobile layouts are checked on required demo surfaces.
- [ ] Evidence is written using the critical-path playtest template.

## Release Packaging Checks

- [ ] Production-required migrations are listed and applied.
- [ ] `ENABLE_DEBUG_ENDPOINTS=0` in release backend environment.
- [ ] `VITE_ENABLE_DEV_PANEL` unset or explicitly false in release frontend environment.
- [ ] Release build does not surface the Dev Panel from Home or the command strip.
- [ ] Generated frontend artifacts are intentionally included or omitted according to release policy.
- [ ] Final smoke check passes against the release-configured build.

## Notes and Release Comms

- [ ] Add first Pig Kin demo closeout summary to `documentation/CHANGELOG.md`.
- [ ] Draft outward-facing demo notes.
- [ ] Capture final release commit/tag reference when publishing.
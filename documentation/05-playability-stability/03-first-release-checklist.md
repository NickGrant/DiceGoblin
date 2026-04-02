# First Release Checklist
----

Status: active  
Last Updated: 2026-04-01  
Owner: Product + QA + Engineering  
Depends On: `documentation/05-playability-stability/02-first-release-manual-gate-evidence.md`, `documentation/CHANGELOG.md`

## Purpose
- Turn the Milestone 31 release-prep work into an explicit closeout checklist.
- Separate already-validated checks from final packaging/release toggles.

## Automated Gates
- [x] `npm.cmd run llm:check`
- [x] `composer --working-dir=backend test`
- [x] `npm.cmd --prefix frontend run test`
- [x] `npm.cmd --prefix frontend run build`

## Manual Gates
- [x] Fresh-account bootstrap validated
- [x] Successful Farm run validated
- [x] Failed Mountains run validated
- [x] Resume continuity validated on active run
- [x] Reset-account validation completed after live fix
- [x] Evidence written to [`02-first-release-manual-gate-evidence.md`](c:/xampp/htdocs/dice-goblin/documentation/05-playability-stability/02-first-release-manual-gate-evidence.md)

## Release Packaging Checks
- [ ] Set `ENABLE_DEBUG_ENDPOINTS=0` in the release backend environment.
- [ ] Leave `VITE_ENABLE_DEV_PANEL` unset or explicitly false in the release frontend environment.
- [ ] Verify release build does not surface the Dev Panel from Home or the bottom command strip.
- [ ] Run one final smoke check against the release-configured build after the debug toggles are disabled.

## Notes and Release Comms
- [x] Add Milestone 31 closeout summary to [`documentation/CHANGELOG.md`](c:/xampp/htdocs/dice-goblin/documentation/CHANGELOG.md)
- [ ] Draft outward-facing release notes once the actual release build is cut
- [ ] Capture final release commit/tag reference when publishing

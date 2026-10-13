# Prototype Test Evidence Outside the Canonical Suite

These pre-vNext integration tests depend on the retired SQL catalog, profile, claim, or encounter schema. They are intentionally outside `backend/tests/` and do not run in `npm run verify:package`. Use them only as migration evidence, then port useful current behavior and delete the old file when its owning milestone ships.

| Owning milestone | Files | Evidence to review |
| --- | --- | --- |
| 11, Codex/Objectives | `BountyBoardControllerIntegrationTest.php`, `BountyBoardSchemaIntegrationTest.php`, `BattleClaimProgressionIntegrationTest.php`, `RunLifecycleServiceIntegrationTest.php` | Objective acceptance, completion, durable facts, and Codex grant cases; discard claim/profile orchestration. |
| 12, onboarding/Mystic Cave | `GrantServiceStarterPackInvariantsTest.php`, `RunGraphGeneratorIntegrationTest.php` | Fresh-player grants, Mystic Cave dialogue/entry, and user-specific introduction cases; use current run-generation tests for Farm/Mountains. |
| 13, Swamp/Chaos | `ChaosEncounterControllerIntegrationTest.php`, `RunGraphGeneratorIntegrationTest.php` | Older reel and Swamp generation ideas for content review; current Milestone 10 Chaos/Hazard tests own the accepted runtime contract. |

The shared support code under `backend/tests/Support/` remains available to inspect these cases. These files are not expected to pass against the current vNext baseline. Do not move them back into canonical discovery without adapting them to current contracts.

# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 6 - Mystic Cave/onboarding integrated verification and technical closure

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-5 now implement the complete fresh-player Mystic Cave onboarding slice: canonical authored onboarding state, ordered Whim dialogue/Codex progression, atomic Basic Goblin starting-Warband provisioning, knowledge-only Wrong Machine introduction, transition into normal Camp/Farm authority, and Phaser presentation/routing driven by server state. Before manual UAT, Package 6 must verify the slice as one integrated lifecycle and close any concrete technical defects without expanding the architecture or pulling Milestone 13 work forward.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
- Package 2 is approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02` (implementation `7661b012aeb5eb58a99c751d0d1eaf6157e89fae`).
- Package 3 is approved through evidence `2ef9aeca75280b7279c5f4bea4ae6260bcd7932a` (implementation `978db58e030b00f339574778ad8a7ce6072a31a4`).
- Package 4 is approved through evidence `045c5446b7bb6c016c530461e040ab38177706f0` (implementation `1f1be8cffa6492960befb9c680294e464175da49`).
- Package 5 is approved through evidence `fc1c5cff8888a0e5a8b3b717077958dec061f2d0` (implementation `11b27b5cd646733b498b8f9f6f84e9fbe052ef5d`).
- Backend/MySQL remains authoritative for onboarding state, dialogue eligibility, Codex ownership, provisioning, unit/squad state, revision, idempotency, and normal run eligibility.
- Phaser presentation is transient orchestration over those authorities; no client tutorial index, local-storage completion flag, or generic history/progression state exists.
- Wrong Machine onboarding is knowledge-only. Access/reconstruction, physical possession/provenance, Swamp/Frog Kin, and Milestone 13 expansion remain deferred.
- Package 7 is the explicit manual UAT gate; Package 6 must not mark Milestone 12 complete or activate Milestone 13.

#### Purpose
Perform integrated technical closure of the Mystic Cave onboarding slice before manual UAT. Exercise the real lifecycle and its recovery boundaries across backend, API/runtime, Phaser routing, responsive presentation, and transition into the existing core loop. Fix only defects demonstrated by this verification. Prefer strengthened integration/regression proof over new abstractions or state.

#### Canonical Package 6 semantics
1. **End-to-end lifecycle.** Verify a fresh player can progress `required` -> `in_progress` -> `ready_to_provision` -> `complete` through the accepted dialogue and provisioning mutations, then enter normal Camp/Farm play.
2. **Authority remains singular.** No test or correction may introduce a second onboarding-completion marker, tutorial history, client-authored progression, or duplicate dialogue/provisioning authority.
3. **Reconnect/reload.** Verify durable recovery at each onboarding state and after completion. Re-entry must derive the same route from authoritative reads without transient session/scene history.
4. **Idempotency and ambiguous retries.** Verify dialogue and provisioning exact-key replay/ambiguous retry behavior across the integrated client/server path, including no duplicate Codex, units, dice, squads, rewards, or revision effects.
5. **Stale-response safety.** Verify older reads/mutations cannot overwrite or route against newer authoritative revision/state.
6. **Cross-player isolation.** One player's knowledge, provisioning, or completion must not alter another player's onboarding state or eligibility.
7. **Secrecy/safe projection.** Browser-visible onboarding/dialogue/bootstrap contracts must reveal only current player-safe state. Future authored steps, reward/event internals, hidden prerequisites, and deferred Wrong Machine/Swamp mechanics remain private.
8. **Wrong Machine boundary.** Owning Wrong Machine Codex knowledge or completing onboarding must not grant `capability.wrong_machine_access`, reconstruction authority, physical machine state, or Swamp/Frog Kin progression.
9. **Returning-player compatibility.** Existing unit owners remain `complete` and enter normal gameplay without onboarding knowledge backfill or mutation.
10. **Core-loop regression.** The provisioned starting Warband must continue to satisfy existing Farm run-start rules, and existing Camp/Codex/Objectives/Warband/run/battle routing must remain intact.
11. **Responsive closure.** Reconfirm Compact 844x390, Standard 1600x900, Wide 2560x1080, and portrait 390x844 behavior, including state-preserving rotate-device blocking.
12. **Scope discipline.** Correct demonstrated integration defects only. Do not broaden visual polish, add speculative systems, or implement Milestone 13 content.

#### Required verification inventory
Review the accepted package implementations as one path: registration/bootstrap, onboarding read, dialogue read/completion, Codex ownership, provisioning, revision/idempotency receipts, active squad/bootstrap reconciliation, BootScene route selection, `OnboardingFlow`, Mystic Cave scene presentation, viewport/portrait gate, Camp transition, and Farm run start. Inspect browser contracts for accidental private-data leakage and verify no duplicate durable state was introduced across Packages 1-5.

#### Minimum proof
1. Brand-new player completes the full onboarding path and reaches normal Camp/Farm state.
2. Reload/resume works independently at `required`, `in_progress`, `ready_to_provision`, and `complete`.
3. Exact-key dialogue replay cannot duplicate Codex/revision effects; ambiguous retry retains the original key.
4. Exact-key provisioning replay cannot duplicate unit/die/squad/revision effects; ambiguous retry retains the original key.
5. Distinct-key attempts after accepted progression cannot replay past onboarding work or duplicate provisioning.
6. Stale/out-of-order reads and revision regressions fail closed without corrupting the client route.
7. Cross-player isolation holds for onboarding knowledge, current step, and provisioning.
8. Returning players with units remain complete without Codex backfill.
9. Wrong Machine knowledge exists independently from access/reconstruction; no deferred Swamp/Frog Kin state is exposed or granted.
10. Browser-safe contracts expose only current interaction/state and no private authored sequence/reward internals.
11. Completed onboarding bootstrap contains the valid active starting squad and Farm remains startable through normal rules.
12. Existing Camp, Codex, Objectives, Warband, Run, and Battle behavior remains regression-safe.
13. Required responsive captures remain legible and deterministic, including portrait blocking.
14. Full package quality gates pass from the final Package 6 head.

#### Verification
Add or consolidate focused integration tests only where Packages 1-5 leave a meaningful lifecycle/recovery boundary unproven. Avoid redundant assertions that merely restate lower-level unit tests. Run all applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`. Re-run deterministic onboarding captures at 844x390, 1600x900, 2560x1080, and 390x844 plus a post-onboarding Camp route capture. If a concrete defect is found, fix it narrowly and include regression proof.

#### Scope exclusions
- manual UAT itself;
- Milestone 12 completion or Milestone 13 activation;
- Swamp/Frog Kin gameplay;
- Wrong Machine physical access/reconstruction/theft/recovery;
- new tutorial/history/story/completion flags;
- generic progression state;
- broad visual redesign/polish;
- speculative refactors not required by demonstrated integration defects.

#### Completion evidence required
Record final implementation/correction SHA(s); integrated lifecycle inventory; any defects found and narrow fixes; fresh-player end-to-end evidence; reload/reconnect/idempotency/stale-response/cross-player evidence; safe-projection/secrecy proof; returning-player and normal-core-loop regression evidence; explicit Wrong Machine knowledge/access separation; responsive capture results; focused test counts; and complete package-gate results.

Leave Package 6 **In Progress** for architectural review. Do not activate Package 7 automatically from the coding agent.

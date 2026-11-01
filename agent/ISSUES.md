# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 2 - Farm access and Tooth Collector rescue correction

**Status:** In Progress
**Priority:** High

#### Problem
Package 1 audited the real opening implementation and confirmed two Farm-level discrepancies that should be corrected before the post-Farm Whim/Mountains work begins:

1. Farm is currently a global `starting_region_id` exception. It is listed in fresh bootstrap before the Whim onboarding has established the first Warband, and `StartRunCommand` bypasses normal region-unlock authority for it. Canonically, The Whim establishes the goblins and grants Farm access.
2. First Mudking victory already grants Shop access exactly once, but the captive is only represented as an unnamed merchant and the first-victory follow-up is tied to transient boss-resolution presentation. Canonically, the player rescues the Tooth Collector from the Mudking; that rescue must remain understandable/recoverable across reload rather than depending on client-local transient state.

Package 1 evidence is recorded in `documentation/07-development-path/m12-5-opening-progression-audit.md`. Package 2 corrects only this Farm/onboarding boundary. Package 3 owns the later Whim return and Mountains unlock.

#### Canonical target
- Fresh player: The Whim is the only meaningful gameplay interaction during onboarding.
- The Whim turns the player from Chaos into goblin form, establishes the four-unit Basic Goblin Warband, sends the player to rescue the Tooth Collector from the Mudking, and grants Farm access.
- First Mudking victory rescues the Tooth Collector and grants Shop access.
- The rescue/follow-up remains recoverable after reload/reconnect until the required player-facing beat is completed.
- Repeat Mudking victories do not duplicate Shop access or replay a one-time rescue as though it happened again.
- Mountains remain locked throughout Package 2. Package 3 will move that unlock to the post-Farm Whim beat.

#### Accepted baseline
- M12 onboarding remains server-authoritative and accepted. Do not replace its ordered Codex/onboarding state model or unit-ownership completion marker.
- Starting Warband remains four Basic Goblins: Bruisers in positions 2 and 5, Marksmen in positions 0 and 3, with shared generated personal names.
- Shop access is already enforced authoritatively by `unlock.capability.shop_access`; preserve that authority and the locked Shop read/purchase behavior.
- Permanent unlock/reward application is unique/idempotent. Repeat reward application must remain safe.
- Farm currently grants `unlock.capability.shop_access` and `unlock.region.mountains` together on first boss resolution. Package 2 must preserve Shop but remove the premature Mountains grant so Mountains remains locked for Package 3.
- Current Farm first-victory follow-up is not reload durable because Phaser relies on the in-memory boss resolution. Correct this through existing durable server-owned concepts; do not persist client presentation state.
- `documentation/01-lore/01-story-and-biome-progression.md` owns the narrative order.

#### Required implementation
1. **Durable Farm access from onboarding.** Replace the global starting-region exception with normal durable Farm region-unlock authority granted as part of the successful onboarding/provisioning transition. The grant must be atomic/idempotent with the accepted onboarding intent and must not create a second bootstrap/tutorial path.
2. **Fresh bootstrap semantics.** Before onboarding completion, Farm must not be exposed as an available/unlocked region merely because it is configured as the starting region. It is acceptable for a fresh player's region list to be empty while onboarding owns routing. Update the strict client contract only as narrowly as needed to permit that legitimate pre-onboarding state.
3. **Run-entry authority.** Farm run entry after onboarding must use the same durable region-access authority as ordinary regions rather than a hard-coded starting-region bypass. Returning players who already have the accepted completed onboarding state must not be stranded; handle compatibility through the smallest explicit migration/reconciliation mechanism consistent with current storage/progression architecture, not a permanent Farm special case.
4. **Whim rescue mission copy/content.** Update the onboarding Whim presentation so the opening assignment is to visit the Mudking and rescue The Whim's friend, the Tooth Collector. Do not make the opening onboarding grant Wrong Machine access or turn the Wrong Machine search into the current mission; that search belongs after Farm in Package 3.
5. **Tooth Collector identity.** Replace the generic/unnamed captive merchant presentation with the Tooth Collector in canonical Farm dialogue/encounter/Codex content where appropriate. Keep teeth as ordinary monster currency and the Shop as the resulting persistent economy surface.
6. **First Mudking victory.** Preserve the existing unique Shop capability reward, but remove `unlock.region.mountains` from the Farm boss reward. First victory must represent/rescue the Tooth Collector; repeat victories must not re-grant Shop or restage the rescue as new.
7. **Reload-safe rescue follow-up.** Make any required post-victory Tooth Collector conversation recoverable from authoritative durable state after reload/reconnect. Prefer meaningful Codex knowledge and existing dialogue/reward eligibility concepts where they fit. Do not add generic `has_seen_dialogue`, story flags, chapter state, or client-local durable state.
8. **Package boundary.** Do not implement the post-Farm Whim Wrong Machine search or Mountains unlock in this package. The end state is intentionally: Farm completed/rescuable, Tooth Collector rescued, Shop available, Mountains still locked.
9. **Revision/idempotency.** New/changed mutations must retain current player-revision and exact-key replay semantics. Retry/reconnect must not duplicate unlocks, Codex ownership, rewards, units, or conversations.
10. **Safe projection.** Browser-facing state may expose the player's resulting Farm/Shop/knowledge state but must not leak future Mountains/Swamp rewards or private prerequisites.

#### Architectural constraints
- Use current reward/unlock, dialogue/Codex, onboarding, region availability, API/storage, and Phaser runtime boundaries.
- Backend/MySQL remains authoritative. Phaser renders/routs from authoritative state; Angular remains the host.
- Authored content belongs in canonical JSON.
- Do not add a generic story/progression history store.
- Do not reintroduce prototype `seen` dialogue state.
- Do not weaken Shop authorization or region-start authorization to make presentation easier.
- Avoid content-literal test sprawl. Test semantic transitions and authority; let structural content validation own catalog integrity.

#### Minimum proof
1. Fresh registration/onboarding does not expose Farm as unlocked before the Whim/provisioning transition.
2. Successful onboarding/provisioning atomically leaves the player with the accepted four-unit Warband, active squad, and durable Farm region access.
3. Farm start succeeds because of that durable access, not a starting-region exception.
4. Returning/previously accepted players are not stranded by the Farm-access correction.
5. Before first Mudking victory, Shop remains locked and Tooth Collector is not represented as already rescued.
6. First Mudking victory grants Shop access exactly once, represents the Tooth Collector rescue, and does **not** grant Mountains.
7. Reload/reconnect after the victory can recover any required one-time Tooth Collector follow-up from authoritative state; it does not depend on the original in-memory battle resolution.
8. Repeat Mudking victory/replay does not duplicate Shop access, rescue knowledge, or one-time rescue presentation.
9. Mountains remain locked and unavailable at Package 2 completion.
10. Wrong Machine access remains absent; no Swamp/Frog Kin/Archivist-troop behavior is introduced.
11. Existing onboarding, Farm run, Shop, reward/idempotency, dialogue/Codex, and client routing regressions pass.

#### Verification
Run focused tests covering onboarding bootstrap/provisioning, region availability/start authorization, Farm boss first/repeat resolution, Shop locked/unlocked behavior, dialogue/Codex reload continuity, exact-key replay, player revision, and Phaser onboarding/Camp/run presentation affected by the contract change. Run applicable `agent/QUALITY_GATES.md` checks and `npm run verify:package -- --continue-on-failure` because this package changes production gameplay behavior/content.

#### Scope exclusions
- post-Farm Whim interaction that starts the Wrong Machine search;
- Mountains unlock/grant (other than removing the premature Farm-boss grant);
- Mountains narrative/dialogue changes;
- Archivist introduction;
- Swamp identity/unlock/presentation;
- Swamp/Frogmen/Archivist troop content;
- Wrong Machine access/reconstruction;
- Frog Kin;
- broader-map opening;
- broad visual redesign;
- M13 parity work or M14 hardening.

#### Completion evidence required
Record implementation SHA(s); exact onboarding/Farm/reward/dialogue/content paths changed; how durable Farm access is granted and how existing players remain valid; proof the starting-region bypass is removed or no longer authoritative; Tooth Collector authored identity/rescue path; proof Shop is first-victory gated and Mountains is no longer granted by Farm; reload/reconnect proof for the rescue follow-up; first/repeat/idempotency/revision results; focused backend/frontend test counts; content revision if changed; and full package-gate results.

Leave Package 2 **In Progress** for architectural review. Do not activate Package 3 automatically from the coding agent.

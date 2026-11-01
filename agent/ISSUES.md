# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 7 - Focused manual UAT before Milestone 13

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-6 have implemented and technically closed the Milestone 12 Mystic Cave onboarding slice. Automated verification now covers real local registration, authoritative onboarding progression, dialogue/Codex grants, explicit starting-Warband provisioning, reconnect/idempotency/stale-response/cross-player boundaries, responsive Phaser presentation, Camp transition, and normal Farm run eligibility. The remaining milestone gate is focused manual UAT of the actual player-facing fresh-player experience.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a`.
- Package 2 is approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02`.
- Package 3 is approved through evidence `2ef9aeca75280b7279c5f4bea4ae6260bcd7932a`.
- Package 4 is approved through evidence `045c5446b7bb6c016c530461e040ab38177706f0`.
- Package 5 is approved through evidence `fc1c5cff8888a0e5a8b3b717077958dec061f2d0`.
- Package 6 is approved through evidence `7c8f2e66df222d1035bba0ae33eb5cbb44220b94` (technical-closure implementation `3f148166a247df80e8839ac48d4b71488f6610de`).
- Package 6 found no production architecture defect; its only implementation change strengthened the integrated lifecycle test to begin from real local registration/session/CSRF state.
- Backend authority, Phaser routing, Wrong Machine knowledge/access separation, and normal Farm eligibility are technically closed and must not be redesigned during UAT absent a demonstrated defect.
- Milestone 13 must not begin until the user explicitly accepts this UAT gate.

#### Purpose
Manually exercise the fresh-player onboarding experience as a player would encounter it and confirm that the accepted technical behavior is understandable, usable, correctly sequenced, and transitions cleanly into the existing Camp/Farm loop. Record concrete defects or acceptance; do not broaden UAT into deferred visual polish or Milestone 13 content.

#### Manual UAT path
1. Start from a genuinely fresh local test account/player with no owned units or onboarding Codex knowledge.
2. Enter the game and confirm the player is taken to the Mystic Cave onboarding rather than Camp.
3. Confirm the first Whim interaction is readable and clearly presents the initial onboarding context.
4. Complete the first interaction and confirm the second Whim/Wrong Machine interaction appears through normal progression without an unexpected route or duplicate action.
5. Confirm the Wrong Machine is introduced as knowledge/future relevance only; the presentation must not imply that the player owns it, can operate it, or knows a physical Mystic Cave location for it.
6. Complete the final knowledge interaction and confirm the game presents a distinct explicit Basic Goblin establishment/provisioning action rather than silently provisioning during dialogue.
7. Trigger provisioning and confirm the player transitions into the normal Camp experience with the starting Basic Goblin Warband available.
8. Confirm the new player can enter/start the Farm through the normal existing flow without an onboarding-specific bypass or obvious broken state.
9. Reload/re-enter at least once during onboarding (preferably after the first dialogue) and confirm the correct current interaction resumes.
10. If practical, reload at the ready-to-provision step and confirm provisioning remains pending and explicit.
11. Reload after completion and confirm the player enters normal gameplay rather than seeing Mystic Cave again.
12. Confirm a pre-existing/returning player with units continues directly to normal gameplay and is not forced through onboarding/backfill.
13. Check the experience at a normal desktop viewport and one compact/mobile-landscape viewport for legibility and usable controls. Confirm portrait touch orientation shows the rotate-device gate without losing the onboarding state after rotation/reload.
14. During the path, note any confusing copy, missing feedback, unusable control, broken route, duplicate grant/action, stale screen, or visible deferred Swamp/Wrong Machine functionality.

#### Acceptance criteria
UAT passes when the user confirms the complete fresh-player path is understandable and functional from Mystic Cave through Camp/Farm, interrupted/reloaded onboarding resumes correctly, provisioning is explicit, returning players bypass onboarding, Wrong Machine presentation remains knowledge-only, and no blocking usability/routing/state defects are observed.

Minor visual polish that is clearly part of the deferred broad visual overhaul does not block acceptance unless it makes the onboarding unreadable or unusable. Any functional, authority, sequencing, retry/reload, or misleading Wrong Machine defect blocks acceptance and should be corrected before Milestone 12 closes.

#### On acceptance
Record the user's explicit UAT acceptance and date in `agent/MILESTONES.md`, mark Milestone 12 complete, and activate Milestone 13 according to the canonical vNext development path. Do not close the milestone or begin Milestone 13 before explicit user acceptance.

#### On failure
Record the observed defect(s), keep Package 7/Milestone 12 active, and create the narrow correction package/work needed to address those defects. Re-run applicable automated verification and the affected UAT steps before requesting acceptance again.

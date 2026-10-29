# Active Execution Issue

## Milestone 12 - Mystic Cave and Onboarding

### Milestone 12 Package 3 - Mystic Cave interaction/dialogue sequence

**Status:** In Progress
**Priority:** High

#### Problem
Packages 1-2 established the authoritative onboarding state model, authored Mystic Cave/Whim knowledge sequence, and atomic Basic Goblin starting-Warband provisioning transaction. The authored onboarding steps are not yet a complete executable interaction sequence. Package 3 must connect the Whim-led Mystic Cave conversations to the accepted dialogue/Codex authority so a fresh player can advance from `required` through `in_progress` to `ready_to_provision` using normal authoritative interactions, without tutorial-specific history or client-authored progression.

#### Accepted baseline
- Package 1 is approved through correction evidence `3f7ab2ac9450faa4087108111844d67f2cc4123a` (implementation `4d2e32c68fa3accba7c4f4d93467015806bc17c2`, correction `fb6414d12848ae37b3150a8cf9a35dbabffcec24`).
- Package 2 is approved through evidence `ba0eecb88cc1ac1a0925c8dc49ab0ca836914a02` (implementation `7661b012aeb5eb58a99c751d0d1eaf6157e89fae`).
- `GET /api/v1/onboarding` is authoritative for `required`, `in_progress`, `ready_to_provision`, and `complete`, with the current dialogue identity exposed only when a conversation remains.
- `onboarding.mystic_cave` owns the ordered authored steps; each step references a dialogue encounter and meaningful Codex knowledge.
- Dialogue completion already provides authenticated, CSRF-protected, idempotent event/reward application and Codex ownership through the accepted Milestone 11 pipeline.
- Out-of-order onboarding knowledge is an integrity failure, not an alternate route.
- Wrong Machine onboarding is knowledge-only and location-neutral; access/recovery and Swamp/Frog Kin remain later progression.
- Package 2 provisioning is only legal at `ready_to_provision`; Package 3 must not bypass or duplicate it.
- Phaser onboarding presentation/routing remains Package 5.

#### Purpose
Make the authored Mystic Cave Whim conversation sequence executable through existing authoritative dialogue/Codex mechanics. A fresh player should be able to complete the currently required conversation, receive its meaningful knowledge once, re-read authoritative onboarding state, and advance to the next authored conversation or `ready_to_provision`. This package owns interaction/API semantics and browser-safe contracts only, not the final Phaser presentation.

#### Canonical Package 3 semantics
1. **Onboarding chooses the conversation.** The authoritative onboarding read determines which authored dialogue is currently eligible. Do not let the client choose an arbitrary onboarding step or infer sequence progression locally.
2. **Reuse dialogue authority.** Execute onboarding conversations through the accepted dialogue completion/event/reward/Codex pipeline wherever semantically valid. Do not create an onboarding-specific knowledge-grant path.
3. **Ordered progression.** Completing the current step grants its authored meaningful Codex entry. The next onboarding read must advance to the next missing ordered step. Completing the final knowledge step must yield `ready_to_provision` while no unit exists.
4. **Replay and idempotency.** Exact-key replay must return the accepted stored result without duplicate Codex grants or revision changes. Ordinary dialogue replay behavior may remain available where canonical dialogue semantics allow it, but it must not skip onboarding order or fabricate progress.
5. **Eligibility is server-side.** Attempts to complete a future/out-of-order onboarding dialogue must fail closed even if a caller knows its stable ID. Do not rely solely on hidden client presentation to enforce order.
6. **Revision/reconciliation.** Newly granted knowledge advances player revision according to the accepted dialogue mutation semantics. Responses/read state must give later clients enough authoritative information to refresh onboarding/Codex state without optimistic knowledge inference.
7. **Safe projection.** Do not expose hidden prerequisite IDs, reward definitions, event routing, future onboarding steps, or private authored sequence data through browser contracts. The browser may know only the current player-safe interaction/presentation required for the active step.
8. **Provisioning remains separate.** Package 3 stops at `ready_to_provision`. Do not automatically create the Warband from dialogue completion or merge the Package 2 mutation into the dialogue pipeline.
9. **Wrong Machine boundary.** The Wrong Machine conversation may teach its name/purpose and grant its Codex knowledge, but cannot grant physical access/capability or move Swamp mechanics forward.
10. **No tutorial history.** Do not add `has_seen_dialogue`, tutorial flags, generic story/history events, or another progression store.

#### Implementation guidance
Prefer a narrow onboarding interaction endpoint or server-side eligibility guard that composes `OnboardingQuery` with the existing dialogue query/completion command, rather than duplicating dialogue execution. If existing dialogue endpoints can safely enforce onboarding order with a small shared eligibility extension, that is also acceptable. Keep transaction ownership and idempotency consistent with existing dialogue semantics.

The client contract should be strict and player-safe. Package 5 will own Phaser scene/UI routing; Package 3 should only add the API/runtime primitives needed to execute the authoritative interaction sequence.

#### Minimum proof
1. A brand-new player at `required` can retrieve/execute only the first authored Whim conversation.
2. Successful first completion grants the expected Codex knowledge once and changes onboarding to `in_progress` with the second conversation current.
3. The second conversation cannot be completed before the first knowledge exists.
4. Successful final knowledge completion changes onboarding to `ready_to_provision`, not `complete`.
5. Exact-key replay cannot duplicate knowledge or revision effects.
6. Invalid/future dialogue IDs and malformed/private data fail closed.
7. Cross-player knowledge cannot affect another player's onboarding eligibility.
8. Reconnect/re-read derives the same current step solely from durable Codex ownership.
9. Wrong Machine interaction grants knowledge only and no access capability.
10. Existing non-onboarding dialogue behavior remains unchanged.
11. Package 2 provisioning remains separately gated at `ready_to_provision`.
12. Browser-safe contracts reveal no future step sequence, hidden prerequisite IDs, event/reward internals, or generic history state.

#### Verification
Add focused backend integration tests for first-step execution, ordered advancement, future-step rejection, final transition to `ready_to_provision`, exact-key replay, revision behavior, cross-player isolation, malformed/corrupt state, and regression of ordinary dialogue. Add strict frontend contract/API tests for any new interaction surface. Run all applicable `agent/QUALITY_GATES.md` checks and full `npm run verify:package -- --continue-on-failure`.

#### Scope exclusions
- automatic or dialogue-coupled starting-Warband provisioning;
- Phaser Mystic Cave/onboarding UI or routing;
- Wrong Machine physical access/reconstruction/theft/recovery;
- Swamp/Frog Kin implementation;
- tutorial/history/story flags or generic progression;
- broad visual redesign;
- Milestone 13+ work.

#### Completion evidence required
Record implementation SHA; exact reuse/composition of dialogue/Codex authority; interaction endpoint/contract and server-side order enforcement; first-to-final step lifecycle; Codex/revision/idempotency behavior; reconnect and cross-player evidence; safe-projection/secrecy proof; ordinary-dialogue regression evidence; confirmation Package 2 provisioning remains separate and Wrong Machine remains knowledge-only; focused tests; and full package-gate results.

Leave Package 3 **In Progress** for architectural review. Do not promote Package 4 automatically from the coding agent.

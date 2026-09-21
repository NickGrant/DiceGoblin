# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 4 - Unlock-aware multi-region run start + Camp region selection/resume

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Package 1 Mountains combat foundation is approved at `4adff479b4c10af40057f1f93088c0930e5894d8`.

Package 2 region-neutral Boss/Exit contracts are approved at `a667ee703da5f868fd65218199ad271553110bc6`, with focused and full MySQL/Docker verification completed without defects.

Package 3 Mountains authored run graph/events/rewards + terminal lifecycle is approved at `f06e150e68ef39c7eeab1299a61d00a6dd2f9bea`. Its final state proves:
- canonical seven-node Mountains fixed graph;
- 8-Teeth Loot;
- 16-XP-per-participant Boss with `unlocks: []`;
- shared Combat/Loot/Rest/Boss/Exit lifecycle;
- MySQL Mountains lifecycle 3 tests / 54 assertions / 0 skipped;
- run-start boundary 26 / 229 / 0 skipped;
- full Docker backend 596 / 2,497 / 150 skipped;
- region-neutral RunScene loading/Exit copy;
- GitHub Full Verification at the final correction: backend 789 / 1,622, frontend 466, all standard gates PASS.

#### Problem

Mountains is now a complete authored/persistable run, but the production start boundary and Camp still assume Farm:
- `StartRunCommand::validateRegion()` accepts only `startingRegionId()`;
- bootstrap exposes owned `unlock_ids` but no authoritative derived region-availability set;
- Camp has one hardcoded Farm start/resume action and hardcoded Farm-specific start/error copy;
- ambiguous run-start retries retain only an idempotency key because the request region has never been selectable.

#### Goal

Make region availability authoritative and unlock-aware on the server, allow an unlocked player to start Mountains through the existing `POST /api/v1/runs` path, and make Camp offer a content-driven choice among the server-authorized regions.

Do not add a second region-start endpoint or a client-owned authorization rule.

#### One authoritative region-availability rule

Establish one shared backend policy used by both bootstrap presentation state and `StartRunCommand`.

For a user:
1. The authored `config.gameplay.starting_region_id` is always available, provided it is a valid playable region with run generation.
2. Any other playable authored region is available only when the user owns an authored `unlock` definition whose:
   - `target_type` is `region`;
   - `target_id` is that region.
3. Do **not** infer an unlock identity from a region identity or hardcode `unlock.region.mountains` in the availability policy.
4. Persisted unknown/stale/unrelated unlock IDs must not grant region access.
5. Regions without valid authored run generation are not startable.
6. Return/order availability deterministically with the starting region first and remaining available region IDs sorted by stable ID.

The policy may be a dedicated service/value object or another single shared boundary, but bootstrap and run start must not independently reimplement the rule.

#### Bootstrap contract

Keep `progression.unlock_ids` unchanged as the authoritative owned-unlock list.

Add:
`progression.available_region_ids: string[]`

Requirements:
- derived server-side from the shared availability policy;
- non-empty for a valid player because the starting region is available;
- deterministic order: starting region first, then other available region IDs sorted;
- contains only authored playable regions;
- no private unlock target/configuration or run-generation data is exposed.

Update the strict frontend bootstrap contract:
- require `available_region_ids`;
- require canonical `region.*` identities, uniqueness, and a non-empty list;
- Camp must resolve every available ID against the projected region catalog and treat disagreement as an integrity error;
- do not derive authorization from `unlock_ids` in TypeScript.

#### StartRun authorization

Replace the current starting-region-only check with the shared authoritative availability policy.

Inside the existing start-run transaction:
- retain exact request shape, CSRF, idempotency hash/receipt behavior, active-run checks, participation validation, Energy spend, generation, persistence, and response shape;
- read authoritative user unlock ownership before authorizing a non-starting region; use the repository's locking form where appropriate for the mutation transaction;
- starting Farm requires no unlock;
- unlocked Mountains uses its existing authored `run_generation.mountains`;
- a valid playable region that is not available to the user must fail with controlled error `run_region_locked` and HTTP 403;
- an unknown/non-playable authored identity remains `run_region_unsupported` / 422;
- locked/unsupported rejection must spend no Energy, create no run/nodes/participants, increment no revision, and finalize no idempotency receipt;
- existing same-key replay semantics remain exact;
- same key with a different region payload remains an idempotency conflict.

Do not special-case Farm/Mountains in the command beyond the authored starting-region rule.

#### Camp region selection

Replace the Farm-only start action with a minimal content-driven region selection using:
- `bootstrap.progression.available_region_ids` for authoritative availability/order;
- projected `regions` content for display name/art identity only.

When there is no active run:
- show/select only server-authorized available regions; locked projected regions are not startable;
- default selection to the first server-provided available region (the starting region);
- with only Farm available, retain a simple one-region experience;
- after `unlock.region.mountains` is present and bootstrap reports Mountains available, allow selection between The Farm and Mountains;
- the action label/messages use the selected authored display name rather than hardcoded Farm text;
- starting Mountains calls the existing `RuntimeApiClient.startRun('region.mountains', ...)`;
- preserve the existing Energy-cost presentation and active-squad requirement;
- keep the layout usable inside Compact, Standard, and Wide safe bounds. A simple selector/list is sufficient; broad Camp visual redesign is out of scope.

When an active run exists:
- do not offer a new region start;
- resume the existing run without POSTing;
- resolve the active run's projected region display name for generic `RESUME <REGION>` presentation;
- active-run resume remains region-agnostic.

#### Run-start retry identity

Region selection adds request identity to the existing idempotent retry state.

For every start attempt retain both:
- the generated idempotency key;
- the exact attempted `region_id`.

After network failure, malformed success, HTTP 5xx, or any other ambiguous result:
- retry only the same key **and same region**;
- do not permit switching region while that ambiguous attempt is outstanding;
- do not generate a second key until a definitive 4xx rejection clears the prior attempt or the user reloads;
- a valid success whose returned `run.region_id` disagrees with the attempted region is recovery-required, not a reason to POST again.

Definitive 4xx rejection clears the attempt identity and allows a later selection/new attempt.

#### Required backend tests

Availability/bootstrap:
- fresh account reports `available_region_ids = ['region.the_farm']`;
- owning `unlock.region.mountains` reports Farm first, Mountains second;
- an unrelated/unknown persisted unlock remains in `unlock_ids` if that is existing behavior but does not grant another region;
- availability is derived from authored unlock target relationships, not unlock-ID naming convention;
- no cross-user unlock leakage or writes.

Run start:
- Farm start remains valid without any region unlock;
- locked Mountains returns `run_region_locked` / 403 atomically;
- unlocked Mountains starts successfully through the public endpoint and persists the exact seven-node authored Mountains graph/participants;
- Mountains spends the same authored Energy cost and increments revision once;
- same-key Mountains replay returns the original result without second spend/run;
- same key with Farm vs Mountains payload conflicts;
- unknown/non-playable region remains unsupported;
- active-run and participation protections remain unchanged;
- Farm regressions remain green.

#### Required frontend tests

Bootstrap/content:
- strict bootstrap parser accepts/retains authoritative `available_region_ids`;
- malformed, duplicate, empty, or invalid region identities are rejected;
- Camp rejects availability IDs absent from projected content;
- client does not infer availability from `unlock_ids`.

Camp:
- fresh player sees only The Farm as a start choice;
- Mountains-unlocked bootstrap exposes both authored choices and can select Mountains;
- selecting Mountains sends exactly `region.mountains`;
- dynamic labels/messages contain the selected display name and no Farm hardcode for Mountains;
- ambiguous retries retain one key + one region and prevent region switching;
- definitive 4xx clears the attempt so a new region/key may be selected;
- mismatched successful region response enters recovery-required and does not POST again;
- active Mountains run resumes without start POST and presents Mountains;
- Compact/Standard/Wide region-choice layout remains inside safe bounds.

Existing RunScene/BattleScene behavior is unchanged except where test fixtures need the new bootstrap field.

#### Verification

Run:
- `npm run verify:package`;
- focused bootstrap/availability/start-run backend tests;
- focused Camp/runtime contract tests;
- `npm run test:db:provision:docker`;
- `npm run test:db:reset:docker`;
- applicable MySQL-backed bootstrap/start-run integration tests;
- `npm run test:backend:docker`.

Report test/assertion/skipped counts where available.

#### Out of scope

- changing the Mountains graph/rewards;
- unlocking Swamps;
- Lizard Kin;
- Wrong Machine recovery;
- new run endpoints;
- client-side authorization derived from unlock conventions;
- prototype region page revival;
- Angular gameplay region selection;
- broad Camp/UI visual overhaul.

#### Completion

Implement only Package 4. Leave it **In Progress** for architectural review. Do not promote Package 5.

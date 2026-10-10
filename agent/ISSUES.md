# Active Execution Issue

## Milestone 9 - Kin and Wrong Machine

### Milestone 9 Package 3 - Phaser Wrong Machine surface + Camp/runtime integration

**Status:** In Progress
**Priority:** High

#### Accepted baseline

Milestone 9 Package 1 is approved through implementation `2126bb76f65b753bef227f68c048de1738808bf7` plus focused production-drop correction `79e38a5a41d25fd36a97ea630ee2379d0e2f6049`.

Milestone 9 Package 2 is approved at `722aacbb6cab4f9907cfeeacf86326ceda024eeb`.

Packages 1-2 established:
- canonical Pig and Lizard Kin definitions and reconstruction recipes;
- permanent Kin ownership through the existing unlock model;
- permanent Wrong Machine access as an authored prerequisite;
- authoritative `GET /api/v1/wrong-machine` read semantics;
- generic authored Mountains/Farm reconstruction-material victory drops;
- one generic authenticated `POST /api/v1/wrong-machine/reconstruct` mutation for Pig and Lizard;
- atomic Raw Chaos + ingredient consumption, Kin restoration, normal unit creation, and `player_revision` advancement;
- first-restoration `random_unlocked` semantics frozen by the finalized idempotency receipt;
- repeat reconstruction with explicit `chosen_unlocked` unit type and no gameplay randomness;
- rollback, cross-player isolation, stale-intent rejection, and active-run-safe unit ownership behavior.

Preserve all accepted Milestone 7/8 economy, inventory, permanent progression, unit ownership, cache, navigation, responsive, idempotency, and reconciliation behavior.

#### Purpose

Make the accepted Wrong Machine backend capability playable through Phaser as a `GameScene` screen and integrate it into Camp/runtime navigation and authoritative client state.

This package is the presentation/integration slice for the Package 1 read contract and Package 2 reconstruction mutation. Do not redesign or duplicate their domain rules in the client.

Do not begin Milestone 9 integrated closure/manual UAT work beyond the verification needed for this package, and do not begin Milestone 10 encounter-depth work.

#### Architectural boundary

Wrong Machine is a screen/view inside the existing persistent `GameScene`, not a new Phaser Scene and not a new Angular gameplay route/page.

Angular remains only the `/game` host. Phaser owns Wrong Machine navigation, API interaction, presentation, mutation lifecycle, and cache reconciliation.

Use the existing GameRuntime/API/state/navigation patterns already established by Warband, Shop, Inventory, and Academy. Do not introduce a parallel state store, direct `fetch` path, screen-specific HTTP stack, or separate gameplay runtime.

The backend remains authoritative. Client state is a cache; the client must not locally invent reconstruction availability, Kin restoration, costs, ingredient balances, eligible unit types, or created-unit state.

#### Runtime/API integration

Add typed client contracts and runtime/API support for:
- `GET /api/v1/wrong-machine`;
- `POST /api/v1/wrong-machine/reconstruct`;
- the Package 2 authoritative reconstruction receipt and business-error envelope.

Opening/refreshing Wrong Machine must load the authoritative read model through the shared runtime/API boundary.

Mutation requests must be constructed only from the authoritative read state currently being presented:
- recipe id;
- current expected mode;
- current authoritative Raw Chaos price;
- current authoritative ingredient requirements;
- chosen unit type only for repeat reconstruction;
- one idempotency key generated/retained according to the existing durable-mutation convention.

Do not accept or derive hidden authored rules from client projection data when the Wrong Machine read already supplies the player-authorized state.

A semantic retry of the same player intention must retain its idempotency identity. A genuinely new reconstruction intention must receive a new key.

#### Wrong Machine screen

Add a usable responsive Wrong Machine screen under `GameScene` that presents the authoritative recipes returned by the server.

At minimum, the player must be able to understand for each available recipe:
- Kin/display identity supplied by the read contract;
- whether the Kin is already restored;
- current mode: first restoration or repeat reconstruction;
- Raw Chaos cost and current balance;
- required ingredients, quantities required, and quantities owned;
- whether the current authoritative state is reconstructable;
- any server-provided availability/blocking state intended for presentation.

Do not duplicate Pig/Lizard rules in the screen. Render the returned recipe collection generically so both accepted recipes travel through the same presentation path and later Kin families do not require a second screen architecture.

For first restoration:
- explain/present that the output unit type is selected from the player's eligible unlocked types by the server;
- do not allow the client to choose or predict the random result;
- provide one clear reconstruction action when authoritative state permits it.

For repeat reconstruction:
- present the authoritative eligible unit-type choices from the read model;
- require an explicit selection before reconstruction;
- do not silently default a choice in a way that could spend resources unintentionally;
- send only the selected stable unit-type id, not client-authored stats or Kin data.

Disabled/unavailable actions must remain visibly non-actionable and must not issue mutation requests.

#### Mutation UX and authoritative reconciliation

During reconstruction:
- prevent accidental duplicate submissions while the intention is pending;
- retain the same idempotency key for semantic retry after transport/unknown-outcome failure;
- distinguish transport/retryable failure from authoritative business rejection;
- surface concise actionable failure state without locally mutating durable values.

On authoritative success, reconcile from the receipt/read contracts rather than simulating the transaction locally.

At minimum:
- advance runtime `player_revision` to the returned authoritative revision;
- reconcile Raw Chaos from the authoritative spend result;
- reconcile affected inventory quantities from authoritative `owned_after` values when that cache is loaded, otherwise mark the relevant inventory cache stale;
- reconcile permanent Kin unlock/restoration state through the accepted runtime unlock/cache mechanism;
- make the newly created unit observable to Warband without requiring a full browser reload: update the loaded Warband/unit cache when the existing cache contract safely supports it, otherwise mark the relevant lazy domain stale so the next Warband load obtains authoritative data;
- refresh/reconcile the Wrong Machine read so first restoration immediately becomes repeat mode and reconstructability reflects the post-spend resources.

Do not optimistically create a local unit, grant a Kin unlock, subtract resources, or switch modes before authoritative success.

If an authoritative conflict such as `reconstruction_changed` indicates stale intent, refresh the Wrong Machine read and require the player to confirm the newly authoritative state rather than automatically resubmitting under changed semantics.

#### Camp and navigation integration

Expose Wrong Machine through the existing Phaser Camp/GameScene navigation model.

The Camp entry must respect permanent Wrong Machine access from authoritative player state. Do not make the feature usable merely because a client screen exists.

Use the established Camp destination/navigation conventions, Back/Escape behavior, pointer affordances, screen teardown, and persistent-runtime lifecycle. Returning from Wrong Machine must not recreate the Phaser runtime or lose unrelated cached state.

Do not add an Angular Wrong Machine route or revive a superseded Angular gameplay surface.

If the Camp currently needs a temporary presentation affordance because final Camp art/structure placement is deferred, keep it narrow and consistent with the existing temporary Camp interaction language. Do not turn this package into the game-wide visual/UI overhaul.

#### Responsive and presentation requirements

Follow the accepted `1600 x 900` logical reference composition and shared responsive layout rules.

Verify the Wrong Machine and its Camp entry at:
- Compact landscape `844 x 390` with touch/mobile capabilities;
- Standard `1600 x 900`;
- Wide `2560 x 1080`;
- portrait mobile `390 x 844`, where the existing rotate-device gate must continue to obscure/block gameplay without losing state.

Critical costs, ingredient counts, unit-type selection, action controls, status/error text, and Back navigation must remain legible and reachable in all supported landscape classes.

Use existing shared UI primitives/layout helpers where they fit. Avoid package-specific viewport-coordinate hacks and avoid final visual-polish work that belongs to the later cross-cutting UI pass.

#### Cache/security/content rules

Do not ship canonical reconstruction recipes or server-only selection rules wholesale to the browser to implement this screen.

Treat server-returned Wrong Machine state as player-authorized presentation data. Preserve the allowlisted client-content boundary and do not expand the static client projection merely to mirror server-private recipe mechanics.

Do not persist client-side reconstruction authority. `reconstructable`, mode, prices, ingredients, eligible types, ownership, and resulting unit data remain server authoritative.

Do not expose another player's resources, unlocks, units, or reconstruction state through cache reuse or client requests.

#### Verification

Run focused frontend tests plus the applicable gates from `agent/QUALITY_GATES.md`.

At minimum prove:
- Wrong Machine is a `GameScene` screen, not a new Phaser Scene or Angular route;
- Camp exposes/navigates to Wrong Machine only under the accepted access semantics;
- Back/Escape returns naturally through Phaser navigation;
- authoritative read loading, loading/error/retry states, and generic Pig/Lizard rendering;
- first-restoration presentation has no client unit-type choice and does not predict the random result;
- repeat presentation requires an explicit eligible unit-type selection;
- disabled/unavailable recipes do not submit;
- one user action produces one mutation intention and duplicate input while pending does not double-submit;
- transport/unknown-outcome retry retains the original idempotency key;
- a new intention receives a new key;
- `reconstruction_changed` refreshes authoritative state instead of silently changing semantics;
- successful first restoration reconciles Raw Chaos, ingredient ownership, Kin state, revision, Wrong Machine mode, and Warband visibility without browser reload;
- successful repeat reconstruction reconciles the same affected domains without replaying first-restoration presentation;
- loaded versus unloaded inventory/Warband caches follow the accepted update-or-stale behavior;
- no optimistic durable mutation occurs before server success;
- refresh/re-entry after success remains consistent with backend authority;
- Compact, Standard, Wide, and portrait-orientation behavior remain correct;
- existing Camp, Warband, Shop, Inventory, Academy, runtime/navigation, and backend Wrong Machine tests remain green;
- full supported frontend/backend/content/docs gates pass as applicable.

#### Completion evidence

Report:
- implementation SHA;
- files/surfaces added or changed;
- Wrong Machine runtime/API/cache contracts;
- Camp/navigation integration path;
- idempotency-key lifecycle for submit/retry/new intention;
- authoritative success/error reconciliation behavior;
- how the created unit becomes visible to Warband without reload;
- focused and full verification counts;
- responsive/visual verification evidence for Compact, Standard, Wide, and portrait gate;
- confirmation Pig and Lizard use one generic Phaser screen path;
- confirmation no new Phaser Scene, Angular gameplay route, client-authored reconstruction authority, Frog Kin, or Milestone 10 work was introduced.

Leave Package 3 **In Progress** for architectural review. Do not promote the next package yourself.

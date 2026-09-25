# Active Execution Issue

## Milestone 6 - Prove Region Generalization

### Milestone 6 Package 6 - Focused manual UAT

**Status:** In Progress
**Priority:** High

#### Accepted technical baseline

Package 5 integrated Mountains verification/closure is architecturally approved at `5c8548d8b70f10d16470a564c53d13d48d10b3e2`.

Technical closure evidence:
- integrated Farm -> Mountains lifecycle: 3 tests / 84 assertions / 0 skipped;
- bootstrap: 6 / 51 / 0 skipped;
- run start: 28 / 255 / 0 skipped;
- full Docker backend: 599 / 2,527 / 150 skipped;
- focused frontend closure suites: 87 tests passed;
- supported browser/runtime lifecycle probe passed;
- GitHub Full Verification: backend 792 / 1,626 assertions, frontend 475, all standard gates PASS;
- integrated proof now uses authorized normal Mountains start through production composition;
- no Farm-specific assumption remains in the registered vNext execution surface.

The old `RegionRepository`/prototype region chain remains as migration evidence only. `backend/public/index.php` explicitly does not register those prototype gameplay controllers against the vNext schema, so it is not part of this milestone's production execution graph.

#### Purpose

Perform the final human-facing proof that Mountains behaves like a genuine second playable region rather than a Farm-specific technical adaptation.

This package is manual UAT only. Do not add implementation work unless UAT exposes a concrete defect.

#### Preconditions

Use the current `vnext-game-overhaul` build with a test player that:
- has a ready active warband/squad;
- has enough Energy to start both Farm and Mountains;
- does **not** already own the Mountains unlock at the beginning of the test.

A fresh test account plus the supported warband fixture is appropriate.

Do not manually grant the Mountains unlock or manually create a Mountains run.

#### UAT flow

##### 1. Fresh Camp / locked Mountains

From Camp before Farm completion:

- only The Farm is offered as a startable region;
- Mountains is not selectable/startable;
- starting Farm uses the normal Camp action;
- no stale/duplicate active-run state appears.

If only one region is available, the Camp should remain simple rather than presenting a meaningless locked selector.

##### 2. Complete Farm and unlock Mountains

Play the complete Farm run through:

`Combat -> Loot -> Rest -> Mudking Boss -> Exit`

Confirm during the run:
- Combat and Boss playback still behave normally;
- Loot grants the expected 8 Teeth once;
- Rest behaves normally;
- no duplicate reward occurs if a completed result is revisited/replayed.

After the Mudking Boss and Exit return to Camp:
- there is no active run;
- The Farm remains available;
- Mountains is now offered as an available region;
- the region selection/start presentation is understandable without a reload;
- Warband editing is available again after the Farm run ends.

##### 3. Start Mountains from Camp

Select Mountains and start it from the normal Camp UI.

Confirm:
- the selected region visibly reads Mountains;
- the action starts Mountains, not Farm;
- no Farm-specific run copy appears;
- the Mountains map contains exactly seven nodes in this order:

`Combat -> Loot -> Combat -> Rest -> Combat -> Boss -> Exit`

##### 4. Mountains combat/content identity

Across the Mountains run, confirm the player-facing encounter identity feels like Mountains/kobolds rather than Farm/pigs.

At minimum observe:
- kobold combatants in the three Combat nodes;
- the final Boss is the Kobold Chief Engineer;
- battle playback works through the same interaction flow as Farm;
- no Farm-specific labels or lock messages appear while the Mountains run is active.

##### 5. Mountains resume/reload behavior

Partway through Mountains, after at least one node has completed:

1. return to Camp through the normal navigation path;
2. confirm Camp offers **Resume Mountains** rather than a new start;
3. resume and confirm the same run/progress is retained;
4. hard reload the application while the Mountains run is still active;
5. confirm startup returns to/resumes the same Mountains run with the same completed/available node state.

While Mountains is active, spot-check Warband:
- participating configuration remains locked as expected;
- copy says active run rather than Farm run;
- viewing/renaming behavior remains consistent with the existing active-run lock rules.

##### 6. Complete Mountains

Finish the remaining path:

`Combat -> Loot -> Combat -> Rest -> Combat -> Kobold Chief Engineer -> Exit`

Confirm:
- Mountains Loot grants exactly 8 Teeth once;
- Rest visibly returns injured participating goblins to full HP;
- all three Combat nodes and the Boss resolve/play back normally;
- Boss completion grants XP to participating units;
- Mountains Boss does **not** announce or expose a Swamps unlock;
- Exit returns cleanly to Camp.

##### 7. Final Camp / persistence

After Mountains Exit:

- there is no active run;
- both The Farm and Mountains remain selectable;
- Swamps is not available;
- Mountains Teeth/XP effects are still present;
- Warband editing is unlocked;
- hard reload preserves the same final state;
- no Farm-specific text appears in Mountains-related Camp/run/active-lock presentation.

##### 8. Responsive spot check

At least once while both regions are available, perform a quick visual check at:
- normal desktop/reference landscape;
- a compact landscape viewport.

Confirm the region choice and start/resume action remain readable, clickable, and inside the usable game area.

#### Pass criteria

Milestone 6 UAT passes when the complete player-facing progression works without a blocking defect:

`Farm locked-state -> Farm completion -> Mountains unlock -> Mountains selection/start -> resume/reload -> complete Mountains -> Camp`

Report either:
- **UAT passed with no issues**, or
- each observed issue with the screen/state, expected behavior, and actual behavior.

If a defect is found, keep Package 6 In Progress and fix only the demonstrated issue before rechecking it.

#### Scope boundary

Do not use this UAT to add:
- Swamps;
- Lizard Kin;
- Wrong Machine;
- economy/inventory;
- Academy/progression breadth;
- new encounters/node types;
- visual redesign.

Those remain later milestones.

#### Completion

Do not promote Milestone 7 until the user reports this manual UAT passed.

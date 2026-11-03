# Active Execution Issue

## Milestone 12.5 - Opening Narrative Progression Alignment

### Milestone 12.5 Package 7 - Focused manual UAT before M13

**Status:** In Progress
**Priority:** High

#### Problem
Packages 2-5 implement the corrected opening narrative progression and Package 6 proves that the complete lifecycle composes technically from a fresh account through the visible/unlocked but non-playable Swamp handoff. Milestone 12.5 still requires explicit player-facing manual acceptance before M13 can begin.

This package is the manual UAT gate. It must validate the actual opening experience and narrative order rather than introduce new gameplay. Milestone 13 remains deferred until the user explicitly accepts this UAT.

#### Accepted technical baseline
- Package 2: Farm/onboarding and Tooth Collector correction accepted through evidence `4a173da769a7e49239168d29522f1e5c3aa5eb8c`.
- Package 3: post-Farm Whim/Mountains transition accepted through evidence `362a77d45022f72b8fc625062149f5cb3c39ade9`.
- Package 4: Mountains trail/first Archivist conversation accepted through evidence `cf9b99cba3f2aab7acb48a95fe6d6df11a228a6b`.
- Package 5: Mountains boss/Swamp handoff accepted through evidence `1f7470fd591e9ce32e08244be8aa3d8c1730bf3f`.
- Package 6: integrated opening-arc verification accepted through test implementation `f0d7a8e99a0d2192a05c4bceccfcc6ffbf1b6b5b` and evidence `77d75330c4618e909b6921cd62214fbb365cf0a6`. Full verification passed at backend 715 tests / 6,045 assertions and frontend 470 tests; no production composition defect was found.

#### UAT target
Manually experience the canonical opening in order:
1. A fresh player begins with The Whim as the only meaningful opening interaction.
2. The Whim establishes the player/Basic Goblins, provides the four-unit starting Warband, grants Farm access, and directs the player to the Mudking/Tooth Collector rescue without introducing the Wrong Machine prematurely.
3. The Farm run reaches the Mudking; first victory grants Shop access and leads to the Tooth Collector rescue/follow-up.
4. After the rescue, the Camp Whim interaction introduces the missing Wrong Machine search, points toward kobolds/Mountains, and unlocks Mountains.
5. The Mountains run advances the Wrong Machine trail and contains the player's first direct conversation with The Archivist. Mountains combat remains kobold-only.
6. First Mountains boss victory establishes that the Machine was moved to the Swamps; the post-boss handoff makes Swamps visible/unlocked.
7. Swamps appear as the next found destination but remain disabled/non-startable because M13 gameplay is not implemented.
8. The player has not recovered the Wrong Machine, fought Order/Archivist troops, reconstructed Frog Kin, or opened the broader map.

#### Manual checks
- Verify dialogue order, wording, and transitions feel coherent and do not expose later information early.
- Verify the four starting Goblins and their formation appear as accepted in M12 UAT.
- Verify Camp progression visibly changes at the expected points: Farm, Shop, Whim/Mountains, then Swamps PATH FOUND.
- Verify Tooth Collector identity/rescue is clear after Mudking.
- Verify The Archivist's Mountains appearance reads as the first direct meeting and not as a combat encounter.
- Verify the Mountains boss/chart handoff clearly communicates location knowledge rather than Wrong Machine possession.
- Verify Swamps cannot be entered yet and that this disabled state is understandable rather than appearing broken.
- Verify no obvious responsive/layout regression at representative desktop/compact sizes used during normal play.
- Reload at one or more pending narrative transitions if useful and confirm the required interaction remains available.

#### Defect handling
If UAT exposes a concrete defect, record the observed behavior and expected behavior here, make the smallest owning correction, rerun focused/full verification as appropriate, and repeat the affected UAT portion. Do not expand into M13 gameplay or unrelated polish.

#### Completion gate
Package 7 and Milestone 12.5 complete only after the user explicitly accepts the manual UAT. Do not reactivate or implement M13 before that acceptance.

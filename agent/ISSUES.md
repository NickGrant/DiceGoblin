# Active Execution Issue

## Milestone 8 - Permanent Progression

### Milestone 8 Package 7 - Focused manual UAT before Milestone 9 promotion

**Status:** In Progress
**Priority:** High

#### Problem

Milestone 8 has passed integrated technical closure, but the permanent-progression slice still needs focused player-facing manual UAT before Milestone 8 can close and Milestone 9 Wrong Machine/kin work can begin.

#### Accepted baseline

Milestone 8 Package 6 - Permanent-progression integrated verification and technical closure is approved at `6314877766187931124c4d8fa5013da02354adfc`.

Package 6 proved the production permanent-progression lifecycle across authored content, MySQL persistence, Raw Chaos, Academy upgrades, Shop consequences, Energy/die-size capabilities, promotion/history/abilities, active-run safety, GameStore reconciliation, reload persistence, and deterministic responsive captures. Its closure record is preserved in git history at `1d3a49b190fda6d29466eb89187bc33e63f42293`.

Do not add new permanent-progression mechanics during UAT. Fix only narrow defects that block or materially contradict the accepted Milestone 8 behavior.

#### Purpose

Validate the Milestone 8 slice as a player experiences it through the normal Phaser runtime, with emphasis on clarity, authoritative cross-screen state, persistence, and progression gating.

#### Focused manual UAT path

Exercise the following supported flow using normal player-facing controls and reload/re-entry where specified:

1. **Academy entry and catalog**
   - enter Academy from Camp;
   - confirm owned, available, locked/prerequisite, and unaffordable states are understandable;
   - confirm displayed Raw Chaos matches the shared player wallet;
   - buy an available upgrade and verify the mutation settles cleanly without trapping navigation.

2. **Unit-type research -> Shop acquisition**
   - verify a researched Goblin type becomes available through the normal Shop flow;
   - purchase one unit with Teeth;
   - confirm the new unit appears in Warband without a global profile refresh;
   - reload/re-enter and confirm the research ownership, wallet state, and purchased unit persist.

3. **Energy capability progression**
   - exercise the 75-cap upgrade and its prerequisite path to 100;
   - verify increasing the cap does not retroactively refill Energy;
   - verify later Camp/run Energy presentation uses the new authoritative maximum;
   - reload/re-enter and confirm the capability persists.

4. **Die-size progression**
   - verify d10/d12/d20 acquisition remains gated until the corresponding Academy capability is owned;
   - advance through the capability chain and confirm each newly eligible Cardboard die appears at the correct threshold;
   - purchase at least one newly unlocked die and verify it appears in inventory/Warband dice state;
   - reload/re-enter and confirm both capability and die ownership persist.

5. **Unit promotion**
   - open Unit Configuration -> Unit Promotion for a Goblin with promotion options;
   - verify below-level and insufficient-Raw-Chaos states are clear and non-destructive;
   - complete a legal tier-1 -> tier-2 promotion and verify the same unit identity/name/kin/loadout/dice bindings remain intact;
   - confirm the next authored branch/options update correctly and permanent abilities/history are represented consistently;
   - where practical, complete tier-2 -> tier-3 and verify terminal no-options behavior;
   - reload/re-enter Unit Detail and confirm the promoted type/history/abilities persist.

6. **Cross-surface wallet/state coherence**
   - spend Raw Chaos in Academy, then open Unit Promotion and confirm the same remaining balance is shown;
   - spend Raw Chaos on promotion, then return to Academy and confirm the same remaining balance is shown;
   - verify no screen appears to retain an independent stale wallet snapshot.

7. **Run/progression interaction**
   - with a previously viewed unit detail/promotion state, allow that unit to gain persisted XP/level through supported run/battle progression;
   - after normal run completion/exit, return to Unit Configuration/Promotion and confirm the fresh level/XP is usable without deleting local state or performing a second mutation;
   - repeat the recovery check after abandoning a run that has already persisted progression;
   - verify a participating unit remains blocked from promotion during an active run while a legal reserve unit is not falsely blocked.

8. **Navigation and presentation smoke check**
   - traverse `Camp -> Academy -> Camp -> Shop -> Warband -> Unit Configuration -> Unit Promotion -> Unit Configuration -> Warband -> Camp`;
   - confirm Back/Escape/navigation remains coherent after successful and rejected actions;
   - note any clipped/inaccessible controls or critical status/wallet text on the device/viewport used for UAT.

#### Defect policy

If UAT finds a defect:
- record the exact player path and observed/expected behavior;
- make the smallest correction that restores the accepted Milestone 8 contract;
- add focused regression coverage for the defect;
- run the applicable package gates from `agent/QUALITY_GATES.md`;
- leave Package 7 **In Progress** for recheck.

Do not broaden scope into balance redesign, new Academy upgrades, new promotion branches, Wrong Machine/kin work, or the deferred game-wide visual overhaul.

#### Completion

Package 7 completes only when the user confirms the focused manual UAT passes after any required corrections.

On confirmed pass:
- mark Milestone 8 complete in the roadmap/milestone state;
- record the UAT date and any correction SHA(s);
- then promote Milestone 9 just in time from the roadmap.

Do not begin Milestone 9 before explicit user confirmation that this UAT package passed.

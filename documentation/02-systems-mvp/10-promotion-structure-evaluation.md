# Promotion Structure Evaluation

Status: active  
Last Updated: 2026-06-06  
Owner: Systems Design + Engineering  
Depends On: `documentation/02-systems-mvp/02-units-and-progression.md`, `documentation/02-systems-mvp/09-ability-loadout-combat-rework-plan.md`

## Purpose

This document evaluates the current promotion structure and identifies why the incentive to go deeper on one unit lineage currently feels weaker than the incentive to go wider by collecting more Tier 1 unit types.

It also proposes three concrete revision plans, with a detailed breakdown of how each plan addresses the problem, what benefits it creates, and what drawbacks or risks it introduces.

## Current Weaknesses

### 1. Sideways breadth currently gives immediate flexibility with low sacrifice

The current structure allows Tier 1 sideways promotion options to expose new archetype packages quickly. That means a player can convert one line into another line and gain access to a different combat role without needing to commit to a long-term identity plan first.

This creates a strong breadth incentive because:
- each new Tier 1 base type represents a meaningful authored package
- sideways promotion from Tier 1 gives access to multiple role families
- the player can treat promotion as roster diversification instead of lineage mastery

The result is that "collect many base identities" feels safer and often more rewarding than "invest deeply in one identity."

### 2. Deeper tiers do not currently create enough exclusive payoff

The current cumulative-ability model helps preserve identity, but it does not automatically create a strong deep-investment reward. If Tier 2 and Tier 3 mostly add another small authored package on top of what the unit already has, the player may read the outcome as:
- slightly better stats
- one more authored package
- a reset back to level 1
- a promotion cost that consumes two other units

That exchange can feel thin unless the deeper tier provides a meaningfully new combat expression, not just a marginally improved one.

### 3. Promotion cost is high while the fantasy payoff is subtle

Promotion consumes three same-type, same-tier, max-level units. That is a real cost in time, combat XP, and roster slots. If the promoted unit does not visibly unlock a stronger identity spike, the system feels economically harsh.

This is especially dangerous because players compare:
- keeping three usable units or multiple base lines
- versus collapsing them into one unit that resets to level 1

If the promoted result does not feel transformational, wider roster ownership wins that comparison.

### 4. Current branch structure risks making lineage choices feel cosmetic

If many branch destinations mostly represent stat tuning and a small authored package shift, then chain promotion and sideways promotion may feel like alternate skins on the same growth pattern rather than distinct long-term strategic identities.

That weakens:
- anticipation for future tiers
- attachment to a chosen branch
- replay value from planning a different lineage

### 5. The system currently gives more clarity to acquisition than to specialization

It is easy for a player to understand why adding another Tier 1 line matters:
- new role
- new base package
- new formation option

It is less obvious why going from Tier 1 to Tier 2 or Tier 2 to Tier 3 matters unless that upgrade changes:
- how the unit solves encounters
- how the loadout budget is used
- how the player builds the rest of the squad around it

That clarity gap is a design problem, not just a tuning problem.

## Design Goal for a Fix

The target should not be to make wide play invalid. Wide play is healthy for experimentation, roster excitement, and early progression. The real goal is:

- wide play should be the onboarding and exploration incentive
- deep play should be the mastery and payoff incentive

That means the game needs to make deeper promotion feel like a strategic commitment with a noticeable return, not just a maintenance upgrade.

## Plan 1: Deep-Tier Exclusive Mechanics

### Summary

Keep the existing promotion structure, but make Tier 2 and Tier 3 destinations grant mechanics that cannot be replicated by owning more Tier 1 lines.

Examples:
- Tier 2 branches unlock signature passive rules that alter action timing, targeting, or survivability
- Tier 3 branches unlock defining capstone mechanics, such as reactive triggers, formation-wide bonuses, or ability-cost manipulation
- some of these mechanics only activate when the unit has traveled deeply enough in that line

### How this solves the problem

This plan addresses the shallow incentive problem by making deeper tiers provide exclusive gameplay verbs instead of only broader ability accumulation.

If a deep promotion grants a rule that changes how the unit functions at a systems level, then a player cannot replace that value by simply owning more Tier 1 identities. A Tier 1 roster can provide breadth. A deep lineage can provide power expression and tactical uniqueness.

That creates a healthier progression ladder:
- Tier 1 gives experimentation
- Tier 2 gives specialization
- Tier 3 gives identity-defining mastery

### Benefits

- Preserves the current structure without rewriting promotion foundations.
- Creates strong emotional payoff for promotion.
- Makes deeper tiers easy to communicate in UX and content previews.
- Supports strong class fantasy because branches can gain truly distinctive identities.
- Helps justify level reset and unit-consumption cost.

### Drawbacks

- Requires more content design work per deep-tier unit.
- Risks power creep if exclusive mechanics are too broadly useful.
- Can create balance gaps if one branch’s exclusive mechanic dominates many encounter types.
- Raises implementation complexity because deeper-tier rules may need new engine hooks.

### Best use case

This is the best plan if the team wants to keep the existing cumulative progression model and strengthen the reward for deep investment without changing the overall promotion economy.

## Plan 2: Promotion Synergy Thresholds Based on Path Commitment

### Summary

Keep wide access to branches, but gate the strongest rewards behind commitment thresholds tied to promotion path continuity.

Examples:
- a unit gains standard inherited abilities from any valid promotion
- chain promotion within the same lineage unlocks synergy bonuses at Tier 2 and Tier 3
- sideways promotions remain legal, but they sacrifice some lineage bonus progression
- synergy bonuses could modify:
  - speed budget efficiency
  - passive scaling
  - slot effectiveness
  - encounter-type utility

### How this solves the problem

This plan does not remove sideways freedom, but it changes the value equation. The player can still move wide, but deep commitment now compounds better.

That means the game says:
- sideways promotion is flexible
- chain promotion is efficient

This is a strong answer to the current problem because it lets players explore without making exploration equal in long-term payoff to specialization. It also makes path history matter in a more visible way than simple destination legality.

### Benefits

- Preserves player freedom while improving long-term strategic structure.
- Makes promotion history more meaningful.
- Supports nuanced choices instead of forcing a single correct promotion style.
- Easier to tune than fully bespoke deep-tier mechanics because bonuses can use reusable system levers.
- Helps create replayable build identities across the same roster content.

### Drawbacks

- Harder for players to understand than explicit exclusive mechanics unless UX is very clear.
- Can feel punishing if sideways promotions are technically allowed but quietly "wrong."
- Requires careful presentation to avoid hidden optimization traps.
- Risks making pathing math feel too abstract compared with direct ability rewards.

### Best use case

This is the best plan if the team wants deep investment to outperform wide experimentation over time, but still wants sideways promotion to remain a meaningful and attractive option.

## Plan 3: Split the Roster Economy into Exploration Units and Keystone Units

### Summary

Reframe unit progression so not every unit is equally worth taking deep. Some lines are intentionally designed as broad utility acquisitions, while others are designed as high-investment keystones.

In practice:
- some Tier 1 types are excellent early pickups and sideways tools
- some branches clearly advertise that their value spikes at Tier 2 or Tier 3
- deeper promotion is concentrated around units that become squad anchors, not every possible unit

This can be supported with:
- stronger Tier 2/Tier 3 stat curves for keystone branches
- stronger capstone passives for keystone branches
- support branches that scale by enabling allies rather than by becoming standalone anchors

### How this solves the problem

This plan accepts that wide progression is desirable and stops trying to make every branch compete on the same incentive axis. Instead, it formalizes different job categories inside the roster:

- exploration units provide early adaptability
- keystone units provide late-run payoff and composition anchors

That makes the system easier to read because the player no longer expects every line to be equally compelling at every stage. Some lines are there to widen options. Some are there to become the centerpiece.

### Benefits

- Gives the roster clearer roles at the macro-progression level.
- Avoids overengineering every branch into a deep-investment line.
- Makes squad-building more legible because anchor units and support units diverge intentionally.
- Lets content effort focus on a smaller set of truly premium deep-tier experiences.

### Drawbacks

- Requires very clear communication so players do not feel baited by weaker deep lines.
- Can create perceived roster inequality if some branches feel "secondary."
- Risks undermining attachment if a favorite Tier 1 line is categorized as mostly exploratory.
- Needs strong thematic framing so role differences feel intentional, not incomplete.

### Best use case

This is the best plan if the team wants a more curated macro-progression identity across the roster instead of trying to make every lineage equally deep, equally broad, and equally rewarding.

## Recommendation

The strongest near-to-mid-term direction is a hybrid of Plan 1 and Plan 2:

- use Plan 1 to create clearly visible deep-tier rewards
- use Plan 2 to make commitment efficiency better than constant sideways drifting

This hybrid works well because:
- Plan 1 solves the emotional payoff problem
- Plan 2 solves the structural incentive problem

Together they create a progression message that is easy to understand:
- explore wide early
- commit deeper once you find a line you want to build around

Plan 3 is still useful, but it should be treated as a roster-authoring philosophy layered on top of the other two plans, not the sole solution.

## Suggested Implementation Order

1. Audit each Tier 2 and Tier 3 line for "exclusive payoff density."
2. Identify which branches currently fail to grant a meaningful new verb or strategic role.
3. Add visible deep-tier identity rewards first.
4. Then add lineage-commitment bonuses if deep versus wide is still too close in value.
5. Re-evaluate whether some branches should intentionally become keystone lines versus exploratory lines.

## Role Review

### Game Designer Review

Role: `Game Designer`

Suggested revision:
- The document should more directly address the player journey by phase:
  - early game
  - first promotion
  - repeated promotions
  - endgame roster shaping

Reason:
- The current analysis is structurally sound, but it would be even stronger if it framed why wide play feels good early and why deep play needs to take over later. That timing matters because a system can be healthy even if wide is initially stronger, as long as the handoff to deep incentives is deliberate and satisfying.

Suggested addition:
- Add an explicit "progression phase intent" section that states:
  - early progression should reward collecting and trying roles
  - mid progression should reward identifying preferred lineages
  - late progression should reward commitment and mastery

Suggested revision:
- Plan 2 should include a player-facing messaging strategy, not just systems mechanics.

Reason:
- If the game introduces lineage bonuses without surfacing them clearly in promotion previews, unit details, and branch identity messaging, players may feel tricked rather than guided.

Suggested addition:
- Add UX requirements for any commitment-threshold system:
  - promotion preview must show what is gained now
  - promotion preview must show what future threshold is being progressed
  - unit details should show current lineage bonus state

Suggested revision:
- The recommendation should explicitly warn against overloading Tier 3 as the only satisfying payoff point.

Reason:
- If Tier 2 still feels weak, many players may never reach the moment where the system becomes exciting. Tier 2 needs to feel like a real payoff, not just a bridge to Tier 3.

Suggested addition:
- Require every Tier 2 branch to introduce at least one clearly felt combat identity shift, even if Tier 3 is the true capstone.

### Combat Systems Reviewer Review

Role: `Combat Systems Reviewer`

Suggested revision:
- The document should distinguish between power payoff and interaction payoff.

Reason:
- A deeper line does not need to be numerically stronger in all cases. It needs to produce better, more coherent combat outcomes in situations where specialization should matter. If this distinction is not explicit, implementation may drift toward stat inflation instead of better system interactions.

Suggested addition:
- Add a validation rule stating that deep-tier rewards should primarily improve:
  - action economy
  - targeting reliability
  - survivability pattern
  - role-specific battlefield leverage
  before simply increasing raw numbers

Suggested revision:
- Plan 1 needs a caution that exclusive mechanics must be encounter-sensitive.

Reason:
- If exclusive mechanics are generically strong across every encounter, then deep-line units become universal best picks. The better design target is "strong in a role-defining way," not "always optimal."

Suggested addition:
- Add an encounter-matrix review requirement for each proposed deep-tier mechanic:
  - how it performs in combat nodes
  - how it scales into boss encounters
  - whether it overcompresses decision-making in formation or loadout planning

Suggested revision:
- Plan 2 should evaluate whether lineage bonuses interact with the 20-point loadout budget.

Reason:
- The current combat system makes loadout budget one of the most important balancing levers. If lineage commitment bonuses affect speed, equip efficiency, duplicate ability value, or passive scaling, those interactions must be tested carefully or they will create hidden dominant loops.

Suggested addition:
- Add a balancing checkpoint for any commitment bonus that touches:
  - speed cost
  - duplicate equip value
  - dice-slot efficiency
  - passive stacking behavior

Suggested revision:
- The recommendation section should call for simulation or deterministic test scenarios before rollout.

Reason:
- Promotion structure changes will affect not only player perception but also encounter throughput, unit survival patterns, and squad-level optimization. Those outcomes should be measured, not only judged by concept.

Suggested addition:
- Require combat validation scenarios covering:
  - wide early roster versus one deep carry
  - mixed-path squad versus committed-path squad
  - Tier 2 spike performance
  - Tier 3 capstone performance

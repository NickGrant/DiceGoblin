---
Title: "vNext Energy Model"
Status: Accepted
Last Updated: 2026-09-09
Owner: Product + Engineering
Depends On:
  - documentation/07-development-path/vnext-game-overhaul.md
  - documentation/07-development-path/vnext-currency-economy-model.md
Category: 07-development-path
Tags:
  - vnext
  - energy
  - pacing
  - runs
---

# vNext Energy Model

## Decision

Energy is a pacing resource, not a currency.

Its purpose is to limit how frequently a player can begin runs without introducing tactical Energy decisions inside a run.

The core rule is:

> Energy is consumed once when a run is successfully created. After that, the active run proceeds without additional Energy costs.

## Run Cost

Energy is charged when the backend successfully creates the run.

This keeps the spend atomic with the creation of the attempt and avoids ambiguity around disconnects, abandoned runs, and failed runs.

Energy is not refunded for:

- losing a run
- abandoning a run
- receiving an unfavorable run layout or opening state

If run creation itself fails before the run exists, the Energy spend must not be committed.

All ordinary runs should initially use the same Energy cost. Different regional or difficulty-specific Energy costs are deferred unless later balance testing demonstrates a clear need.

## Regeneration

Energy regenerates automatically over real time up to the player's normal maximum Energy.

Natural regeneration must never increase Energy above the normal maximum.

Energy regeneration is time-based state and should track the information required to calculate the authoritative current value, such as:

- current Energy
- normal maximum Energy
- regeneration interval/rate
- last regeneration timestamp or equivalent authoritative timing state

The exact maximum, regeneration rate, and run cost are balance values rather than architectural rules.

## Recharge Consumables

The game should include consumable items that restore Energy.

Using an Energy recharge item applies an Energy-restoration effect rather than granting generic currency.

Recharge items may overcharge Energy above the normal maximum, but only when the player's Energy is below the normal maximum at the moment the item is used.

For example, with a normal maximum of 10 Energy:

```text
Current 8 / 10
Use +5 Energy item
Result: 13 / 10
```

The player cannot use another Energy recharge item while at 10/10 or above the normal maximum.

```text
Current 10 / 10 -> item use blocked
Current 13 / 10 -> item use blocked
Current 9 / 10  -> item use allowed
```

This allows a recharge item to overcap without allowing unrestricted stacking of Energy consumables.

Natural regeneration remains paused while Energy is at or above the normal maximum. Once Energy falls below the normal maximum, ordinary regeneration resumes according to the authoritative regeneration rules.

## Role in the Economy

Energy should remain independent from the Teeth/Raw Chaos wallet model.

Energy consumables may be acquired through normal reward and economy systems, including gameplay rewards and potentially Teeth-based purchases, but Energy itself is not bought, spent, or stored using generic currency infrastructure.

Raw Chaos should not be the normal mechanism for Energy recovery because Raw Chaos is reserved for permanent/transformative progression and targeted Wrong Machine reconstruction.

## Design Intent

Energy should pace access to runs without shaping moment-to-moment tactical choices once a run begins.

The balancing target should therefore be expressed primarily in terms such as:

- how many runs a full Energy bar supports
- how long it takes to regenerate enough Energy for another run
- how frequently recharge consumables enter the economy

The absolute numeric values of Energy are secondary to these player-experience targets.

## Consequences

This model intentionally keeps Energy simple:

- one spend per run
- no Energy cost per node, battle, or action
- no refund for failed or abandoned runs
- automatic real-time regeneration to normal maximum
- consumable recovery
- consumable overcharge allowed only when starting below normal maximum
- no unrestricted consumable stacking while already full or overcharged

This preserves Energy as a pacing system rather than turning it into another tactical resource or general-purpose currency.

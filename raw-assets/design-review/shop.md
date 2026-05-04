Screenshot: `raw-assets/design-review/screenshots/shop.png`

## Purpose of the Screen
Provide the between-run shop where the player spends teeth on baseline dice, baseline units, and the rotating daily deal.

## Needed Player Interactions
- Scan the three shop sections: basic dice, basic units, and daily deal.
- Compare item labels, prices, and roles before purchasing.
- Use `Buy`, `Hire`, or `Buy Deal` for the chosen offer.
- Return to the home screen through the dedicated back action.

## Information Need to Be Conveyed to Player
- Current soft currency amount and whether the player can afford available offers.
- The difference between permanent catalog items and the once-per-day deal.
- For the daily deal, the die rarity, size, affix, value, and purchase availability.
- That purchases immediately affect roster or inventory state after success.

## Current Visual Challenges
- The shop uses many compact rows, so price, item identity, and call-to-action buttons must stay readable at a glance.
- The daily deal panel carries the most flavor and detail, which can visually overpower the baseline catalog columns.
- Currency framing matters because the scene's usefulness depends on instantly understanding affordability.
- The scene currently relies on live backend data, so any loading or unavailable state needs to remain presentable for review.

## In-Screen Changes That Can Occur
- The screen transitions from loading into the populated catalog once shop data arrives.
- Purchase actions can refresh the catalog, change currency, and flip the daily deal into a sold state.
- Toast feedback appears for successful or failed purchases.
- The back action returns directly to `HomeScene`.

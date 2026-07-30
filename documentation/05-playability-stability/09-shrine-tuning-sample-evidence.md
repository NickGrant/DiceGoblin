# Shrine Tuning Sample Evidence
----

Status: Draft
Last Updated: 2026-07-30
Owner: Product + Design + QA
Depends On: `agent/ISSUES.md`, `documentation/09-active-system-structure/06-loot-determination.md`

## Purpose

Capture automated shrine effect distribution evidence after the generated shrine expansion, stat-favor pass, and result-clarity pass merged.

This evidence supports UAT review. It does not replace hands-on validation of whether the effects feel valuable, readable, and correctly paced during real runs.

## Command

Run from the repository root:

```bash
npm.cmd run sim:shrines:docker
```

## Sample Configuration

The 2026-07-30 sample used:

- `200` samples per quality tier.
- Regions: Farm, Mountains, Swamps.
- Qualities: poor, good, great.
- Declineable shrine offers included.
- Deterministic seed prefix: `shrine-tuning`.

## Distribution Snapshot

| Region | Quality | Avg Teeth | Declineable | Primitive Mix |
| --- | --- | ---: | ---: | --- |
| Farm | poor | `4.83` | `0` | grant teeth `80%`, heal random unit `20%` |
| Farm | good | `2.59` | `0` | grant teeth `43%`, heal random unit `22%`, run stat modifier `15.5%`, squad damage modifier `19.5%` |
| Farm | great | `1.6` | `0` | grant teeth `26%`, heal random unit `16.5%`, run stat modifier `19.5%`, squad damage modifier `26.5%`, upgrade run unit tier `11.5%` |
| Mountains | poor | `6` | `0` | grant teeth `100%` |
| Mountains | good | `2.4` | `11` | clear combat node `14.5%`, drain/heal bargain `5.5%`, grant teeth `41%`, run stat modifier `24%`, squad damage modifier `15%` |
| Mountains | great | `0.73` | `32` | clear combat node `15.5%`, drain/heal bargain `16%`, grant teeth `12%`, run stat modifier `29%`, squad damage modifier `15%`, upgrade run unit tier `12.5%` |
| Swamps | poor | `4.75` | `0` | grant teeth `77.5%`, heal random unit `22.5%` |
| Swamps | good | `1.86` | `13` | clear combat node `8%`, double run teeth `7.5%`, drain/heal bargain `6.5%`, grant teeth `32.5%`, heal random unit `18.5%`, run stat modifier `16%`, squad damage modifier `11%` |
| Swamps | great | `0.6` | `23` | clear combat node `11.5%`, double run teeth `10%`, drain/heal bargain `11.5%`, grant teeth `11%`, heal random unit `13%`, run stat modifier `19.5%`, squad damage modifier `13.5%`, upgrade run unit tier `10%` |

## Interpretation

- Poor shrines are intentionally simple and material. Farm and Swamps mix teeth with small healing; Mountains currently uses only teeth at poor quality.
- Good shrines introduce utility without making currency vanish entirely. Farm good shrines remain cost-free and readable.
- Great shrines mostly trade direct teeth for route, combat, upgrade, or bargain value. Low average teeth at great quality is expected from the current effect mix, but UAT should confirm players understand that these are stronger non-currency outcomes.
- Declineable bargains currently appear only in Mountains and Swamps good/great pools. That matches the intended riskier-region placement.
- Swamps has the broadest high-quality mix, including double-teeth and bargain effects. UAT should check whether that reads as flavorful rather than noisy.

## UAT Focus

- Verify shrine result screens clearly explain non-currency effects before claim.
- Confirm costly shrine offers feel optional and understandable before accepting or declining.
- Check whether Mountains poor shrines feel too plain compared with Farm and Swamps poor shrines.
- Check whether great shrine low-teeth outcomes feel rewarding once the new effect summaries are visible.
- Record at least one accepted bargain and one declined bargain in Mountains or Swamps.
- Record at least one stat modifier shrine and verify the active run effect remains visible from the run map before the next combat.

## Tuning Levers

- Increase or decrease per-quality weights in `EncounterPrimitiveCatalog::SHRINE_EFFECTS`.
- Add a Mountains poor non-currency effect if pure teeth feels too flat.
- Increase grant-teeth weights in great pools if players still perceive great shrines as low-value after result clarity.
- Reduce bargain weights if declineable offers feel too frequent or interruptive.
- Increase utility weights if shrines should feel more like routing/combat opportunities than currency pickups.


# Run Node Generation
----

Status: active
Last Updated: 2026-07-27
Owner: Engineering
Depends On: `backend/src/Services/RunGraphGenerator.php`, `backend/src/Repositories/RunRepository.php`

## Purpose

Run node generation builds the graph a player traverses during a run, including node types, edges, encounter templates, dialogue inserts, and availability state.

## Top-Level Flow

```mermaid
flowchart TD
  A[Create run] --> B{Region slug}
  B -- mystic_cave --> C[Generate fixed mystic cave graph]
  B -- the_farm --> D[Generate fixed farm graph]
  B -- other --> E[Generate procedural graph]
  C --> F[Apply dialogue nodes]
  D --> F
  E --> F
  F --> G[Validate graph]
  G --> H[Persist nodes and edges]
```

## Fixed Regions

| Region | Nodes | Shape |
| --- | --- | --- |
| `mystic_cave` | Dialogue start, exit | Linear `0 -> 1`; dialogue is available, exit starts locked. |
| `the_farm` | Combat, loot, rest, boss, exit | Linear `0 -> 1 -> 2 -> 3 -> 4`; first combat starts available. |

The farm uses fixed encounter templates for its combat, loot, rest, and boss nodes.

## Procedural Region Defaults

| Region | Rows | Travel columns | Paths | Opening rows | Lane gap | Dead ends | Dead-end chain | Rest weight | Loot weight | Combat weight |
| --- | ---: | ---: | ---: | ---: | ---: | --- | ---: | ---: | ---: | ---: |
| `mountains` | `9` | `8` | `3` | `3` | `2` | `2-3` | `1` | `1` | `2` | `5` |
| `swamps` | `11` | `10` | `4` | `4` | `2` | `3-5` | `1` | `2` | `3` | `3` |
| default | `9` | `9` | `3` | `3` | `2` | `2-4` | `1` | `2` | `2` | `4` |

Procedural generation uses a seed key made from region slug, region id, and run seed.

## Procedural Shape

```mermaid
flowchart TD
  A[Create starting combat at column 0] --> B[Open starting lanes at column 1]
  B --> C[Walk multiple paths through travel columns]
  C --> D[Add deterministic dead ends]
  D --> E[Compact rows toward center]
  E --> F[Add nearby shortcuts]
  F --> G[Remove redundant same-row bypasses]
  G --> H[Add boss after travel columns]
  H --> I[Add exit after boss]
```

Every node in the final travel column links to the boss, and the boss links to the exit.

## Procedural Node Type Assignment

The available start node is always set to `combat`. Boss and exit nodes keep their explicit types. Other nodes are assigned by weighted deterministic rolls.

| Situation | Weight behavior |
| --- | --- |
| Dead end | `loot: 50`, `rest: 30`, `combat: 20`, `shrine: 1`, `chaos: 1` |
| Normal path | Uses region `combat`, `loot`, and `rest` weights, plus `shrine: 1` and `chaos: 1`. |
| Column `<= 2` | Adds combat weight, removes rest, shrine, and chaos. |
| Near final travel column | Adds rest, shrine, and chaos weight. |
| Parent is rest | Removes rest from this node. |
| Parent is shrine | Removes shrine from this node. |
| Parent is chaos | Removes chaos from this node. |
| Chaos disallowed | Removes chaos from all rolls. |

After assignment, the generator guarantees at least one rest node when a candidate exists. If chaos nodes are allowed, it also guarantees at least one chaos node when a candidate exists.

```mermaid
flowchart TD
  A[Unassigned procedural node] --> B{Start node?}
  B -- yes --> C[combat]
  B -- no --> D{Dead end?}
  D -- yes --> E[Dead-end weights]
  D -- no --> F[Region weights]
  E --> G[Apply column and parent constraints]
  F --> G
  G --> H{Chaos allowed?}
  H -- no --> I[Set chaos weight 0]
  H -- yes --> J[Keep chaos candidate]
  I --> K[Weighted deterministic pick]
  J --> K
```

## Dialogue Inserts

After the base graph is generated, `applyDialogueNodes()` can insert dialogue nodes for the region. Inserted dialogue nodes use metadata such as `dialogue_id`, `one_time`, and `tags`. Seen one-time dialogue can be removed from future graphs while preserving connectivity.

See `04-dialogue-flow-determination.md` for dialogue gating details.

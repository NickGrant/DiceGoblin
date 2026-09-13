# Active Execution Issue

`ISSUES.md` contains only the current execution-ready package. Future packages stay summarized in `agent/MILESTONES.md` and the roadmap until promoted. Do not implement them early.

## Milestone 3 - Enter Farm

### Establish Farm authored run content and deterministic generator adaptation

**Status:** Open
**Priority:** High

#### Problem
Package 1 established normalized active-run persistence without allowing the retained prototype generator or SQL-authored catalogs to shape the vNext schema.

Before the run-start transaction can consume that persistence, vNext needs a canonical authored definition of the Farm map/generation inputs and a deterministic generator boundary that reproduces the useful Farm graph behavior without retaining prototype HTTP, PDO, SQL catalog, player-state, transaction, reward, or combat ownership.

This package owns canonical static Farm run-generation content, its validation/projection boundary, and pure deterministic graph generation only. It does not create runs, spend Energy, mutate players, expose run APIs, or render Phaser run UI.

#### Required Context
Read before implementation:
- `documentation/07-development-path/vnext-authored-content-model.md`
- `documentation/07-development-path/vnext-storage-model.md`
- `documentation/07-development-path/vnext-endpoint-inventory.md`
- `documentation/07-development-path/vnext-backend-internal-architecture.md`
- `documentation/07-development-path/vnext-phaser-client-architecture.md`
- `documentation/02-systems/run-node-generation.md`
- `documentation/07-development-path/vnext-prototype-code-disposition.md`
- current `backend/content/**`
- current `ContentRegistry`, `ContentValidator`, `ClientContentProjector`, content tests, generated client projection, and frontend `ClientContentRegistry`
- retained prototype `backend/src/Services/RunGraphGenerator.php` and focused generator tests only as behavior evidence
- Package 1 run persistence and tests

Do not treat prototype SQL encounter/run-pattern catalogs or old Angular run APIs as authority.

#### Canonical content domains
Extend the existing unified Git-backed ContentRegistry rather than creating a second run-content loader.

Add only the static run-generation domains required by the current Farm map slice.

The intended vNext concepts are:
- player-visible run node-type presentation definitions, using stable IDs such as `run_node_type.*`;
- a server-owned Farm generation definition, using a stable ID such as `run_generation.*`;
- a server-owned relationship from `region.the_farm` to its generation definition.

Exact filenames may follow the existing hybrid content organization, but all definitions remain normal canonical JSON read by the same ContentRegistry.

Do not introduce:
- SQL region catalogs;
- SQL node-type catalogs;
- SQL encounter-template catalogs;
- SQL run-pattern catalogs;
- another editable YAML/PHP generation source.

#### Farm scope
Reconcile the retained prototype Farm graph into canonical vNext content deliberately rather than bulk-copying the prototype generator.

For this milestone, preserve the useful established Farm map behavior:
- one deterministic linear Farm path;
- combat -> loot -> rest -> boss -> exit;
- stable authored node-type identities;
- generated/map placement sufficient to reproduce the familiar left-to-right Farm layout;
- first/root node initially available and downstream nodes initially locked;
- deterministic connectivity and ordering.

The generation definition should describe authored/static inputs. The generator should derive runtime graph state such as indexes/connectivity output/initial availability where that is more appropriately computational than authored duplication.

Do not add combat, loot, rest, boss, reward, or exit resolution behavior in this package.

#### Encounter references
Package 1 persistence can store an optional authored encounter reference, but Milestone 3 does not resolve encounters.

Do not fabricate canonical combat encounter definitions merely to populate `run_nodes.encounter_id` early.

It is acceptable for the current Farm Milestone 3 graph output to have no encounter reference until the combat/node-resolution milestone owns the canonical encounter model.

Do not migrate prototype numeric encounter-template IDs.

#### Node-type authored content
Add the node types required by the Farm map, expected to include the semantic equivalents of:
- combat;
- loot;
- rest;
- boss;
- exit.

They need enough safe presentation metadata for a later Phaser map to label/render the node type without hard-coding player-facing strings in the scene.

Prefer a small public shape such as:
- stable ID;
- display name;
- description when useful;
- icon/art key.

Do not put server-only resolution handlers, rewards, encounter payloads, combat rules, or hidden generation topology in the public node-type definitions.

#### Farm generation definition
The canonical server-owned Farm generation definition must provide enough static input for a pure generator to produce the intended graph deterministically.

A reasonable definition may contain:
- generation stable ID;
- generator/algorithm identifier;
- authored node specifications with local authoring keys and node-type references;
- authored placement coordinates/metadata where the layout itself is content;
- authored edge relationships when using a fixed graph.

Keep the model declarative.

Do not embed executable scripts, PHP class names, arbitrary expressions, SQL IDs, or player-state conditions in JSON.

Do not generalize the schema around Mountains/Swamps before those milestones need it.

#### Region relationship
`region.the_farm` should identify its server-owned run-generation definition using a stable authored reference.

This relationship must be semantically validated.

The generation ID is server orchestration information and should remain outside the existing public Region projection unless a later client requirement specifically needs it.

The public region shape should remain presentation-oriented.

#### Structural validation
Extend `ContentValidator` for the new concrete definition types.

Validate at minimum:
- stable namespaces;
- expected field types;
- bounded non-empty display/presentation strings;
- generator identifier vocabulary actually supported now;
- non-empty authored node list;
- unique local node keys;
- valid node-type stable IDs;
- sane integer placement coordinates/metadata used by the current layout;
- edge shape;
- no self edge;
- no duplicate logical edge;
- edge endpoints use existing local node keys.

Do not accept arbitrary unvalidated generation blobs merely because the generator can inspect them.

#### Semantic validation
Cross-reference validation must prove at minimum:
- Farm region -> generation definition exists and is the correct type;
- generation node -> node-type references exist and are the correct type;
- every edge endpoint resolves to a node in the same generation definition;
- the authored fixed Farm graph has a valid start/root and exit path;
- the exit is reachable;
- the graph contains the required Farm boss/exit structure needed by the current fixed slice;
- impossible/disconnected authored graphs fail content validation rather than being discovered only at runtime.

Keep validation generic where naturally reusable, but do not build a universal graph DSL.

#### Client projection boundary
Maintain explicit allowlists.

Public projection should expose the safe node-type presentation definitions needed by the future run map.

Do **not** expose the full Farm generation definition or hidden topology through `game-content.json`.

Do not expose the region's server-only generation-definition reference if the client does not need it.

Remember: anything in `game-content.json` is assumed readable by the player.

The deterministic global content revision still includes the complete canonical source, including server-private generation fields/definitions.

Update the generated frontend content artifact and strict `ClientContentRegistry` parsing/indexing for any newly projected public domain.

Preserve all existing Milestone 1/2 projection behavior.

#### Deterministic generator boundary
Create/adapt a vNext generator in the accepted backend/domain boundary.

The generator must be deterministic and side-effect free with respect to infrastructure.

It must not own or depend on:
- PDO;
- SQL repositories/catalogs;
- HTTP/request objects;
- authenticated player/session state;
- Energy;
- database transactions;
- reward application;
- ContentRegistry lookups from inside the core computational algorithm.

The application layer in Package 3 will load validated authored definitions and pass the required deterministic inputs into the generator.

A thin content-to-generator adapter/factory outside the computational core is acceptable when useful.

#### Generator output
Return an in-memory generated graph suitable for Package 3 to persist.

The output must contain enough deterministic information for persistence, including semantic equivalents of:
- stable sequential/run-local node index;
- node-type stable ID;
- optional encounter ID when genuinely present;
- initial runtime status;
- generated placement metadata;
- edge connectivity expressed using generated/run-local node identity;
- optional generated edge/path metadata when required by the fixed layout.

Do not return database IDs.

Do not persist anything in this package.

#### Farm determinism
For the same validated Farm generation definition and deterministic inputs, output must be identical.

Because the current Farm graph is fixed, a seed may legitimately have no visible effect. Do not add fake randomness just to make a seed appear meaningful.

Do not prematurely migrate the full Mountains/Swamps pattern generator.

#### Graph validation
The vNext computational boundary should reject impossible output/invariants rather than assuming authored input can never be wrong.

At minimum verify:
- non-empty nodes;
- unique sequential indexes;
- all edges resolve;
- no self/duplicate edges;
- start availability is coherent;
- required exit is reachable;
- all nodes required by the fixed Farm graph are reachable from the start.

Content validation catches authored errors; generator/output validation protects the runtime boundary. These responsibilities may share a small pure graph validator if that keeps ownership clear.

#### Prototype disposition
Mine only useful Farm behavior from the retained `Services\RunGraphGenerator`.

Do not route vNext through that service merely because it already exists: it currently mixes generation with PDO and prototype authored catalogs.

Do not bulk-refactor or delete it yet if Mountains/Swamps/later mechanics still contain useful evidence.

If this package proves the Farm-specific fixed-graph portion is fully superseded, update `vnext-prototype-code-disposition.md` narrowly to record that fact while retaining other prototype generator evidence until its owning milestone.

Do not create an archive directory.

#### Tests
Add focused coverage at the owning layers.

At minimum prove:
- representative valid Farm canonical content passes;
- malformed node-type definitions fail;
- missing/wrong-type generation references fail;
- duplicate local node keys fail;
- invalid edge endpoints fail;
- self/duplicate edges fail;
- disconnected/unreachable exit fails;
- wrong/missing required Farm structure fails when required by the accepted fixed model;
- server-private generation data is absent from client projection;
- safe node-type presentation is projected;
- generated frontend content exactly matches the projector output;
- frontend strict content parser rejects malformed projected node types;
- deterministic Farm generator returns the expected five-node linear graph;
- first node is available and downstream nodes locked;
- repeated generation with identical input is identical;
- generator output contains no database IDs and performs no persistence;
- graph-output validation rejects malformed graph state;
- existing region/Warband authored content and projection tests remain green;
- Package 1 run-persistence tests remain green.

Prefer behavior tests over snapshots of large JSON blobs.

#### Documentation
Update existing active documentation only where the accepted current truth changes, especially:
- authored-content source ownership;
- run-node generation boundary;
- prototype disposition when Farm behavior has been conclusively mined.

Do not create a package completion report or legacy copy.

#### Explicitly Out of Scope
Do not implement:
- run creation persistence command;
- `POST /api/v1/runs`;
- run creation idempotency;
- Energy spending or Energy-anchor mutation;
- current-run query/API;
- abandon;
- bootstrap active-run summary;
- active-run Warband locks;
- RunScene;
- Phaser Farm map;
- encounter resolution;
- combat;
- loot/reward application;
- Rest/Chaos interaction;
- boss/exit resolution;
- Mountains/Swamps generation migration;
- run modifiers;
- battles/playback;
- Milestone 4.

Do not alter Package 1 persistence unless this package uncovers a concrete blocking defect in that contract; report any such defect rather than casually expanding schema scope.

#### Verification
Run applicable gates from `agent/QUALITY_GATES.md`.

At minimum run:
- authored-content validation/generation;
- focused backend ContentValidator/ContentRegistry/projector tests;
- focused pure generator tests;
- frontend client-content parser/registry tests;
- Package 1 run-persistence integration regression;
- existing Warband content regressions;
- full backend Docker suite if backend runtime/content code changed;
- full frontend suite and production build because `game-content.json`/client content contracts change;
- bundle check;
- context/docs checks when documentation changes.

No Phaser screenshot capture is required because this package does not add presentation.

Do not claim a command passed unless it actually ran.

#### Review State
When complete:
- set this issue to `In Progress` if needed;
- leave it `In Progress`;
- do not mark it complete;
- do not promote Package 3;
- do not begin the run-start transaction/API.

Architectural review decides completion.

#### Final Report
Report:
1. resulting commit SHA;
2. canonical content files/types added or changed;
3. exact Farm generation definition shape;
4. node-type public projection shape;
5. server-private fields/definitions intentionally withheld;
6. structural/semantic validation rules;
7. generator class/boundary and dependencies;
8. exact deterministic Farm graph output;
9. prototype generator behavior mined/retained;
10. generated content revision;
11. backend/frontend/content verification actually run and results;
12. any unresolved generation/content concern.

Do not begin another package.

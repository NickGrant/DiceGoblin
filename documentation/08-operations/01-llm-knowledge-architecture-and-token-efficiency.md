---
Title: "LLM Knowledge Architecture and Token Efficiency Implementation Plan"
Status: Needs Review
Last Updated: 2026-08-01
Owner: Engineering
Depends On:
  - AGENTS.md
  - agent/LLM_CONTEXT.md
  - agent/CONTEXT_ROUTER.md
  - documentation/README.md
  - documentation/08-operations/02-documentation-style-guide.md
Category: 08-operations
Tags:
  - operations
---

# LLM Knowledge Architecture and Token Efficiency Implementation Plan

## 1. Purpose

This document defines an actionable migration plan for improving documentation discoverability, reducing duplicated project knowledge, and lowering LLM token consumption without reducing answer or implementation quality.

The target state is a repository-native knowledge architecture in which:

- current project truth remains in reviewed canonical documents
- historical evidence and design rationale are preserved without polluting default context
- document discovery is driven by structured metadata rather than manually duplicated routing lists
- agents load the smallest authoritative context set capable of invalidating an incorrect answer
- generated indexes and context bundles can be rebuilt instead of manually maintained
- token consumption, documentation drift, and duplicate ownership can be measured in CI

This plan extends the existing Dice Goblins agent and documentation structure. It does not replace `AGENTS.md`, the existing documentation hierarchy, or the current backlog workflow.

## 2. Problem Statement

Dice Goblins already has several useful context-management mechanisms:

- `AGENTS.md` defines the always-loaded agent contract
- `agent/LLM_CONTEXT.md` defines default and conditional context
- `agent/CONTEXT_ROUTER.md` maps task categories to documentation areas
- `documentation/README.md` defines canonical sources, topic ownership, and read order
- documentation files use status, owner, update date, and dependency headers
- active, backlog, and archived work are separated

These mechanisms reduce noise, but the same routing and ownership information is maintained in several places. As the documentation set grows, this creates four risks:

1. **Token duplication**: agents load multiple files that repeat the same project facts or routing guidance.
2. **Maintenance duplication**: the documentation index, context router, manifests, and task documents can drift apart.
3. **Retrieval overreach**: broad directory-level routing loads unrelated documents for narrow tasks.
4. **Authority ambiguity**: agents spend tokens reconciling current, proposed, historical, and implementation-specific sources.

The immediate goal is not to add a vector database or external knowledge platform. The goal is to make the repository itself a structured, progressively disclosed knowledge base.

## 3. Goals

### 3.1 Primary Goals

- Reduce the default LLM context footprint while retaining all project-wide constraints needed for safe work.
- Make authoritative information discoverable through exact document and section routing.
- Assign each important concept one canonical owner.
- Separate current contracts from decisions, evidence, history, and generated summaries.
- Generate human and LLM discovery surfaces from one metadata catalog.
- Make documentation quality and context cost measurable.
- Allow implementation work to be decomposed into independent, reviewable tasks.

### 3.2 Success Measures

The migration is successful when:

- a typical task starts with no more than the approved default context budget
- topic routing returns exact documents instead of whole directories
- active documents have unique stable IDs and valid metadata
- no two active documents claim canonical ownership of the same concept unless explicitly permitted
- generated indexes match document metadata with no manual drift
- archived and superseded documents are excluded from default retrieval
- common tasks can retrieve summaries before full documents
- CI reports token-budget growth, broken references, and ownership conflicts
- agents can identify the intended contract, implementation surface, and verification source without broad repository searches

## 4. Non-Goals

This proposal does not require:

- an external documentation service
- Obsidian, Notion, Confluence, or another parallel knowledge silo
- embeddings or a vector database in the first implementation phase
- automatic edits to canonical documentation without review
- automatic deletion or merging of similar prose
- moving implementation source code into the documentation system
- replacing the existing issue and milestone workflow
- loading all project history into every agent session

Semantic search may be considered later only if deterministic metadata routing and repository search prove insufficient.

## 5. Design Principles

### 5.1 Canonical Knowledge Is Reviewed

Canonical documentation defines intended current behavior and must remain human-reviewable. Agents may propose edits, but generated or inferred content must not silently replace reviewed contracts.

### 5.2 Evidence Is Preserved, Not Repeated

Playtest notes, design conversations, research, and prior proposals should be retained as source material. Their conclusions should be synthesized into canonical documents or decision records rather than copied into multiple active documents.

### 5.3 Generated Views Are Disposable

Indexes, catalogs, context bundles, dependency reports, and duplicate-content reports should be generated from source metadata. A generated file must be safe to delete and rebuild.

### 5.4 Progressive Disclosure Is the Default

Retrieval should proceed in this order:

1. inspect catalog metadata
2. read the document abstract and contract summary
3. read the relevant section
4. read the complete document only when needed
5. inspect implementation and tests only when the task requires current behavior or code changes
6. inspect decisions and historical sources only when rationale or reconsideration is required

### 5.5 Smallest Sufficient Authoritative Context

The retrieval objective is not merely the smallest context. It is the smallest authoritative context set capable of revealing that a proposed answer or implementation is wrong.

### 5.6 One Fact, One Owner

Important rules should have one canonical owner. Other documents should reference that owner instead of restating the rule.

### 5.7 Intended Truth and Implemented Truth Are Distinct

Documentation should make the following distinction explicit:

- canonical contract: what the project intends to be true
- implementation source and tests: what currently happens
- decision record: why the current contract was chosen
- evidence: what observations or discussions informed the decision

A mismatch between contract and implementation should be reported rather than silently resolved.

## 6. Target Knowledge Layers

### 6.1 Canonical Documentation

Location: existing `documentation/00-*` through `documentation/07-*` directories.

Purpose:

- define current project intent and behavioral contracts
- answer how the game, architecture, systems, and UX should work
- remain the main reviewed source for product and engineering decisions

Ownership:

- human-reviewed
- agents may create proposed changes through normal pull requests

### 6.2 Evidence Sources

Proposed location: `documentation/sources/`

Purpose:

- preserve design discussions, playtest reports, research notes, transcripts, and external-reference summaries
- provide provenance for decisions and canonical updates
- remain excluded from default context

Rules:

- source documents are immutable or append-only after ingestion, except for metadata corrections
- source documents do not override canonical contracts
- source documents must identify capture date, origin, and affected topics
- copyrighted external material should be summarized or linked, not copied wholesale

### 6.3 Decision Records

Proposed location: `documentation/decisions/`

Purpose:

- explain why a meaningful design or architecture choice was made
- record alternatives, tradeoffs, and reversal conditions
- prevent canonical documents from accumulating historical narrative

Rules:

- use stable decision IDs
- decision records are immutable after acceptance except for status and supersession metadata
- superseding a decision creates a new record and links both records
- decision records do not repeat the complete current contract

### 6.4 Generated Views

Proposed location: `documentation/generated/`

Purpose:

- provide generated discovery and retrieval artifacts
- support both human browsing and agent context selection

Initial generated artifacts:

- `documentation/generated/catalog.json`
- `documentation/generated/index.md`
- `documentation/generated/context-router.md`
- `documentation/generated/ownership-report.md`
- `documentation/generated/dependency-report.md`
- `documentation/generated/token-report.md`

Rules:

- all files must begin with a generated-file warning
- generated files must not be edited manually
- generation must be deterministic for the same repository state
- CI must detect generated output drift

### 6.5 Context Bundles

Proposed location: `documentation/generated/context/`

Purpose:

- provide compact, task-oriented summaries for recurring work areas
- reduce repeated loading of full documents

Initial bundle candidates:

- `combat-backend.md`
- `combat-ux.md`
- `run-generation.md`
- `unit-progression.md`
- `onboarding.md`
- `release-validation.md`

A bundle must contain only:

- purpose and scope
- relevant canonical document IDs
- key invariants
- implementation and test paths
- explicit exclusions
- unresolved questions
- validation expectations

A bundle must not duplicate complete canonical sections.

## 7. Document Metadata Contract

### 7.1 Required Metadata

Every active canonical, decision, source, and generated document must expose machine-readable metadata.

The implementation may use YAML frontmatter or another format supported consistently by repository tooling. The chosen format must support the fields below.

Required for all managed documents:

- `id`: stable repository-wide document ID
- `kind`: `canonical`, `decision`, `source`, `generated`, or `index`
- `status`: `active`, `proposed`, `superseded`, `archived`, or `generated`
- `title`: display title
- `owner`: responsible discipline or team
- `last_updated`: ISO date
- `topics`: searchable topic tags

Required for canonical documents:

- `authority`: normally `current-state` or `directional`
- `canonical_for`: concepts owned by this document
- `depends_on`: stable document IDs
- `implementation_refs`: relevant code, data, or test paths when known
- `verification_refs`: tests, validation documents, or commands when known

Required when applicable:

- `superseded_by`
- `related`
- `source_refs`
- `decision_refs`
- `implementation_authority`
- `design_authority`

### 7.2 Stable ID Convention

Use lowercase dotted IDs grouped by knowledge area.

Examples:

- `overview.project`
- `overview.game-loop`
- `architecture.api`
- `architecture.data-model`
- `systems.combat`
- `systems.combat-math`
- `systems.target-resolution`
- `systems.dice`
- `ux.combat-viewer`
- `decision.combat-cumulative-scheduling`
- `source.playtest-2026-07-24-onboarding`

Rules:

- IDs must not contain file paths or sequence numbers
- IDs remain stable when a document moves
- a deleted ID must not be reused for a different subject
- superseded documents retain their original IDs

### 7.3 Authority Values

Supported initial authority values:

- `current-state`: intended behavior for implemented or actively implemented scope
- `directional`: future design direction that does not override implementation
- `reference`: supporting information that is not itself a behavioral contract
- `historical`: retained for rationale or audit only

Retrieval must prefer `current-state` over all other authority values unless the task explicitly requests planning, history, or reconsideration.

### 7.4 Canonical Ownership Rules

- A concept may have only one active `current-state` canonical owner.
- A directional document may discuss the same concept but must not claim current-state authority.
- A document may own multiple closely related concepts.
- A concept should be split into a new owner when it changes independently or is regularly retrieved independently.
- Broad labels such as `combat` may be shared as topics, but precise `canonical_for` values must remain unique.

## 8. Required Document Structure

Each substantial canonical document must support layered retrieval.

### 8.1 Header and Metadata

The document begins with its machine-readable metadata and a human-readable title.

### 8.2 Abstract

A 50-100 word abstract must state:

- what the document owns
- what it does not own
- which related documents contain delegated rules

### 8.3 Contract Summary

A concise list of approximately 5-15 invariants must summarize the current contract.

The summary must be sufficient for orientation and planning but not replace detailed rules.

### 8.4 Detailed Contract

Detailed behavior, edge cases, examples, formulas, and interfaces remain in the main body.

### 8.5 Open Questions and Deferred Decisions

Every high-impact document must clearly list unresolved questions, deferred decisions, and intentional omissions.

### 8.6 Validation Sources

Every implemented current-state contract should identify how it can be verified through tests, data inspection, route capture, simulation, or manual playtest.

## 9. Retrieval and Context Policy

### 9.1 Default Context Budget

Initial target budget for repository-provided default context:

- preferred: 2,000-4,000 estimated tokens
- warning threshold: 4,000 estimated tokens
- failure threshold: to be established after baseline measurement

The default budget excludes:

- the user request
- source files directly required by the task
- tool output generated during the task

The default context should normally include only:

- `AGENTS.md`
- compact active task and milestone state
- a compact generated catalog or router
- any platform-required instructions outside the repository

### 9.2 Default Exclusions

The following remain excluded unless explicitly relevant:

- archives
- backlog-only planning
- full role catalogs
- raw assets
- generated application bundles
- source evidence
- historical decisions
- entire documentation directories
- superseded documents

### 9.3 Routing Output

A routing request should return ranked exact documents:

- `required`
- `likely`
- `optional`
- `excluded`

Each result should include:

- stable ID
- path
- authority
- short reason for selection
- relevant section anchors when available

Directory-level routing is permitted only when the catalog does not yet contain enough metadata to select exact documents.

### 9.4 Task-Type Routing

The initial router must support at least these task types:

- project overview
- architecture and API changes
- data model and seed ownership
- combat behavior
- combat UX and readability
- units, promotions, and progression
- dice and ability slots
- runs, encounters, loot, and map generation
- onboarding and player education
- release validation and testing
- lore and narrative planning
- backlog and milestone operations

### 9.5 Session Context Snapshot

For multi-step work, agents should maintain a compact working snapshot containing:

- current task
- canonical documents loaded
- decisions made during the session
- files inspected or modified
- unresolved questions
- validation performed

The snapshot should be updated rather than repeatedly reloading and resummarizing the same documents.

## 10. Generated Catalog Requirements

The generated catalog is the machine-readable discovery source.

Each catalog record must include:

- document ID
- title
- path
- kind
- status
- authority
- owner
- topics
- canonical ownership values
- dependencies
- related documents
- source and decision references
- implementation references
- verification references
- abstract or compact summary
- estimated token count

Catalog generation must:

- walk only approved documentation and agent paths
- ignore excluded binary, dependency, and build directories
- reject duplicate IDs
- reject invalid enumerated metadata values
- report broken references
- produce deterministic ordering
- normalize path separators
- preserve no hidden model-generated state

## 11. Token Measurement Policy

### 11.1 Measurement Scope

Token reporting should estimate:

- each managed document
- default context files
- generated context bundles
- active issue and milestone documents
- aggregate context for each configured task route

### 11.2 Reporting

The token report should identify:

- current estimated default-context size
- configured target and warning threshold
- largest contributors
- change from the baseline or target branch
- routes that exceed their configured budgets
- active documents exceeding recommended summary or total size

### 11.3 Enforcement Rollout

Token checks should progress through three stages:

1. report-only
2. CI warning
3. CI failure for agreed hard limits

No hard failure threshold should be introduced until at least two weeks of measurements or an equivalent representative sample is available.

## 12. Documentation Quality Checks

The knowledge validation workflow must eventually check:

- required metadata exists
- IDs are unique and valid
- enumerated values are valid
- dependencies and related references resolve
- superseded documents identify replacements when applicable
- active current-state canonical ownership is unique
- generated artifacts match current source metadata
- active documents do not depend on superseded documents as authority
- default routes do not include archived or superseded content
- router entries resolve to exact documents
- abstracts and contract summaries exist for high-impact documents
- implementation and verification references exist when required
- token budgets are reported
- exact duplicated paragraphs are reported
- likely semantic duplication is reported separately and remains advisory

The existing documentation header and encoding lint should be retained until the replacement validator covers all current checks.

## 13. Implementation Work Breakdown

The following task sequence is intended to be transferable into issues without additional design work.

## Task KA-01: Establish Baseline Measurements

### Objective

Measure the current documentation and default-context footprint before changing behavior.

### Files and Surfaces

- `AGENTS.md`
- `agent/LLM_CONTEXT.md`
- `agent/CONTEXT_ROUTER.md`
- `agent/ISSUES.md`
- `agent/MILESTONES.md`
- `documentation/**/*.md`
- proposed baseline report under `documentation/generated/`

### Steps

1. Inventory all Markdown files under `agent/` and `documentation/`.
2. Record line count, byte count, and estimated token count per file.
3. Define the current default context exactly as loaded by startup rules.
4. Calculate aggregate estimated tokens for the current default context.
5. Identify the ten largest contributors.
6. Identify repeated routing information across `README.md`, `documentation/README.md`, `agent/LLM_CONTEXT.md`, and `agent/CONTEXT_ROUTER.md`.
7. Record baseline results in a generated report.

### Acceptance Criteria

- every managed Markdown file appears in the baseline inventory
- default-context composition is explicitly listed
- aggregate and per-file estimates are available
- the report is deterministic across repeated runs with no content changes
- no behavior or context-loading rules change in this task

### Validation

- run the measurement twice and confirm identical output
- manually compare the default-context list against `AGENTS.md` and `agent/LLM_CONTEXT.md`

### Dependencies

None.

## Task KA-02: Define Metadata Schema and Migration Rules

### Objective

Create the authoritative schema for managed documentation metadata.

### Files and Surfaces

- new schema document under `documentation/`
- `documentation/08-operations/02-documentation-style-guide.md`
- metadata parser or validator planning surface

### Steps

1. Select YAML frontmatter or another single machine-readable format.
2. Define required fields by document kind.
3. Define valid enum values.
4. Define stable ID syntax and uniqueness rules.
5. Define canonical ownership conflict rules.
6. Define supersession behavior.
7. Define path inclusion and exclusion rules.
8. Define how legacy header metadata maps into the new format.
9. Document an incremental migration path that permits mixed legacy and new metadata during rollout.
10. Update the style guide with the approved contract.

### Acceptance Criteria

- all fields in Section 7 have explicit types and required/optional status
- examples exist for canonical, decision, source, and generated documents
- legacy documents remain valid during the migration window
- ambiguous ownership and supersession cases have documented resolutions

### Validation

- manually model at least one overview, architecture, gameplay, UX, decision, and source document against the schema
- verify no required field depends on LLM inference during validation

### Dependencies

KA-01.

## Task KA-03: Build Metadata Parser and Catalog Generator

### Objective

Generate one deterministic catalog from repository documentation metadata.

### Files and Surfaces

- new documentation tooling under `scripts/`
- root `package.json` command surface
- `documentation/generated/catalog.json`
- tests or fixtures for the parser and generator

### Steps

1. Walk approved documentation and agent paths.
2. Parse new metadata and supported legacy headers.
3. Normalize records into one internal catalog shape.
4. Calculate file metrics and estimated tokens.
5. Sort records deterministically by ID.
6. Write `documentation/generated/catalog.json`.
7. Add a command for catalog generation.
8. Add fixtures covering valid, missing, duplicate, superseded, and malformed documents.
9. Add clear non-zero exit behavior for structural errors.
10. Document how generated output is refreshed.

### Acceptance Criteria

- catalog generation succeeds against the current repository during migration
- duplicate IDs fail validation
- malformed metadata reports file and field
- path separators are platform-independent
- generated output is byte-for-byte stable when inputs do not change
- token estimates are present for each record

### Validation

- run parser fixture tests
- generate the catalog twice and compare output
- run on Windows-compatible and CI-compatible command paths

### Dependencies

KA-02.

## Task KA-04: Migrate High-Value Documents First

### Objective

Apply stable IDs, abstracts, contract summaries, and ownership metadata to the documents most frequently used by agents.

### Initial Migration Set

- `AGENTS.md`
- `agent/LLM_CONTEXT.md`
- `agent/CONTEXT_ROUTER.md`
- `documentation/README.md`
- `documentation/00-overview/00-project-overview.md`
- `documentation/00-overview/01-core-gameplay-loop.md`
- `documentation/05-technical/02-frontend-state-and-scene-contracts.md`
- `documentation/05-technical/03-backend-api-contracts.md`
- `documentation/05-technical/04-data-model.md`
- `documentation/02-systems/mvp-reference/00-combat-system.md`
- `documentation/02-systems/mvp-reference/01-dice-system.md`
- `documentation/02-systems/mvp-reference/02-units-and-progression.md`
- `documentation/04-ux/00-ux-and-debug-scope.md`
- `documentation/04-ux/04-combat-viewer-readability.md`
- `documentation/04-ux/09-first-session-player-journey.md`

### Steps

1. Assign stable IDs.
2. Add topics and canonical ownership values.
3. Add or refine 50-100 word abstracts.
4. Add compact contract summaries.
5. Convert path-based dependencies to stable ID references while retaining readable links where useful.
6. Add implementation and verification references.
7. Identify duplicated rules and select one canonical owner for each.
8. Replace duplicate prose with references where the meaning remains clear.
9. Record unresolved ownership conflicts for separate review rather than guessing.

### Acceptance Criteria

- every document in the initial set has valid metadata
- every current-state concept claimed by the set has one owner
- each document can be discovered by its primary topics
- no current behavior changes are introduced by the migration
- abstracts and summaries accurately reflect detailed content

### Validation

- run catalog validation
- conduct manual product and engineering review of ownership assignments
- compare before and after documents for accidental contract changes

### Dependencies

KA-03.

## Task KA-05: Generate Documentation Index and Context Router

### Objective

Eliminate manually duplicated discovery maps by generating human and agent views from the catalog.

### Files and Surfaces

- `documentation/generated/index.md`
- `documentation/generated/context-router.md`
- existing `documentation/README.md`
- existing `agent/CONTEXT_ROUTER.md`

### Steps

1. Define ordering rules for human-readable indexes.
2. Define task-type-to-topic routing configuration.
3. Generate topic, authority, owner, and task-entry sections.
4. Generate exact document routes ranked as required, likely, optional, and excluded.
5. Add migration notices to existing manually maintained files.
6. During the transition, compare generated output to existing indexes.
7. After parity is accepted, reduce manual files to compact entrypoints that reference generated artifacts.
8. Prevent generated files from becoming canonical authorities.

### Acceptance Criteria

- generated index covers all active canonical documents
- generated router resolves each configured task type to exact documents
- no default route points only to an entire directory
- archived and superseded documents are absent from default routes
- a single metadata edit updates all affected generated views

### Validation

- test each task type listed in Section 9.4
- manually compare generated routes against current `CONTEXT_ROUTER.md`
- verify generated-file drift is detectable

### Dependencies

KA-04.

## Task KA-06: Add Knowledge Validation to Documentation Linting

### Objective

Extend documentation checks beyond headers and encoding into knowledge integrity.

### Files and Surfaces

- existing `scripts/lint-doc-headers.mjs`
- new or replacement knowledge-validation command
- root `package.json`
- CI verification workflow

### Steps

1. Preserve existing header and encoding checks.
2. Add schema validation.
3. Add unique ID validation.
4. Add broken dependency and related-reference validation.
5. Add canonical ownership conflict validation.
6. Add supersession validation.
7. Add generated-output drift validation.
8. Add exact duplicate paragraph reporting.
9. Keep likely semantic duplication advisory and separate from structural failures.
10. Introduce report-only mode before hard CI enforcement.

### Acceptance Criteria

- every structural failure names the affected file and field or concept
- duplicate ownership fails validation for active current-state documents
- exact duplicate paragraphs produce an actionable report
- existing documents can migrate incrementally without disabling the validator
- CI can run validation non-interactively

### Validation

- fixture tests for each failure class
- one intentional repository-level failure per rule to verify messages
- successful full-repository validation after fixtures are removed

### Dependencies

KA-03 and KA-04.

## Task KA-07: Implement Token Budget Reporting

### Objective

Make context cost visible and prevent silent growth.

### Files and Surfaces

- catalog generator metrics
- `documentation/generated/token-report.md`
- default-context configuration
- CI output

### Steps

1. Select and document the estimation method.
2. Measure each managed document.
3. Measure configured default context.
4. Measure each generated task route and context bundle.
5. Compare results against KA-01 baseline.
6. Report largest contributors and growth deltas.
7. Add configurable warning thresholds.
8. Keep enforcement report-only until sufficient baseline history exists.
9. Define criteria for moving warnings to failures.

### Acceptance Criteria

- per-file and aggregate estimates are available
- default-context estimate is shown prominently
- route estimates identify required and optional portions separately
- the report identifies growth relative to the selected comparison point
- thresholds are configuration-driven rather than hard-coded into prose

### Validation

- modify a fixture document and verify the reported delta changes predictably
- verify repeated runs are stable
- confirm reports remain useful when the exact production model tokenizer differs

### Dependencies

KA-01 and KA-03.

## Task KA-08: Create Decision and Source Templates

### Objective

Separate rationale and evidence from current contracts.

### Files and Surfaces

- `documentation/decisions/README.md`
- `documentation/decisions/TEMPLATE.md`
- `documentation/sources/README.md`
- `documentation/sources/TEMPLATE.md`
- style guide and catalog validation

### Decision Template Requirements

- stable ID
- status
- decision date
- affected topics
- context
- decision
- alternatives considered
- consequences and tradeoffs
- reversal or review conditions
- supersedes and superseded-by relationships
- canonical documents affected
- source references

### Source Template Requirements

- stable ID
- capture date
- origin type
- source location or reference
- affected topics
- factual summary
- observations
- candidate implications
- canonical documents potentially affected
- ingestion status

### Acceptance Criteria

- templates pass metadata validation
- decision and source records cannot claim current-state canonical ownership
- templates clearly distinguish observation from accepted project truth
- README files explain when to create each record type

### Validation

- create one representative decision fixture and one source fixture
- confirm both appear in the catalog but not in default current-state routes

### Dependencies

KA-02 and KA-03.

## Task KA-09: Pilot Source-to-Canonical Ingestion

### Objective

Test the workflow using one real design conversation or playtest report.

### Recommended Pilot

Use the first-session onboarding and hidden target-resolution observations from the July 24, 2026 playtest discussion.

### Steps

1. Create an immutable source record summarizing the observations.
2. Identify affected canonical documents through catalog routing.
3. Determine whether a decision record is required.
4. Propose focused canonical updates without copying the source narrative.
5. Add source and decision references to affected canonical metadata.
6. Review the change for factual accuracy and authority boundaries.
7. Regenerate catalog and indexes.
8. Record lessons and update templates or ingestion rules.

### Acceptance Criteria

- the source observation is preserved
- current conclusions appear only in canonical documents or accepted decisions
- affected documents link back to provenance
- default routing does not load the raw source
- no duplicate current-state explanation is introduced

### Validation

- ask a test agent to answer the current target-resolution contract using default routing
- ask a separate rationale question and verify the decision or source is loaded only then

### Dependencies

KA-05 and KA-08.

## Task KA-10: Generate Implementation Maps

### Objective

Reduce repeated repository exploration by linking knowledge topics to implementation surfaces.

### Files and Surfaces

- metadata `implementation_refs` and `verification_refs`
- generated implementation-map view
- high-value canonical documents

### Steps

1. Define supported implementation reference types: directory, file, data, migration, test, command, route, or asset.
2. Populate references for the high-value migration set.
3. Generate a topic-oriented map containing contract, implementation, test, data, and UX paths.
4. Report missing implementation or verification references for implemented current-state documents.
5. Keep implementation maps path-based and concise; do not generate stale code summaries.

### Acceptance Criteria

- an agent can locate contract, implementation, tests, and data for each pilot topic from one map
- missing references are reported but may remain warnings during rollout
- moved files can be corrected through metadata without rewriting multiple indexes

### Validation

- test combat, dice, run generation, units, and onboarding topics
- manually verify a sample of mapped paths against the repository

### Dependencies

KA-04 and KA-05.

## Task KA-11: Generate Initial Context Bundles

### Objective

Provide compact orientation for recurring high-value task categories.

### Initial Bundles

- combat backend
- combat UX
- run generation
- unit progression
- onboarding
- release validation

### Steps

1. Define bundle schema and maximum recommended size.
2. Generate bundle content from document metadata and explicitly marked contract summaries.
3. Include document IDs, invariants, implementation paths, exclusions, unresolved questions, and validation sources.
4. Exclude detailed examples and historical rationale.
5. Add route configuration so agents retrieve bundles before complete documents.
6. Report when a bundle exceeds its token target.

### Acceptance Criteria

- each bundle is materially smaller than loading all referenced documents
- each invariant is traceable to one canonical source
- bundles contain no unsupported synthesized rules
- bundle regeneration updates references after metadata changes
- full documents remain available when detailed work requires them

### Validation

- compare answer quality and tokens for representative tasks with and without bundles
- manually verify every invariant against its canonical source

### Dependencies

KA-04, KA-05, KA-07, and KA-10.

## Task KA-12: Reduce Default Context and Remove Manual Duplication

### Objective

Apply the new retrieval system to actual agent startup behavior.

### Files and Surfaces

- `AGENTS.md`
- `agent/LLM_CONTEXT.md`
- `agent/CONTEXT_ROUTER.md`
- `documentation/README.md`
- generated catalog, router, and bundles
- startup validation

### Steps

1. Compare the new catalog and router against current startup requirements.
2. Remove repeated routing prose from always-loaded files.
3. Replace manual document lists with compact references to generated discovery artifacts.
4. Keep project-wide safety, workflow, and precedence rules directly in `AGENTS.md`.
5. Keep only active task and milestone context in default load.
6. Exclude archives, backlog-only plans, decisions, and sources by default.
7. Update startup checks to validate generated artifacts are current.
8. Measure the resulting context against KA-01 baseline.
9. Run representative agent tasks before and after the change.

### Acceptance Criteria

- default context remains within the approved target or has a documented exception
- all project-wide constraints required for safe execution remain directly available
- task routing continues to find the correct canonical documents
- archived, superseded, and source material are not loaded by default
- duplicated router and index lists are removed or generated
- representative task quality does not regress

### Validation

- run startup checks
- run documentation and catalog validation
- execute the evaluation matrix in Section 14
- compare token use and output quality to baseline

### Dependencies

KA-05, KA-07, and KA-11.

## Task KA-13: Complete Metadata Migration

### Objective

Migrate the remaining active documentation after the pilot proves the schema and tooling.

### Steps

1. Group remaining documents by directory and owner.
2. Assign stable IDs and topics.
3. Resolve canonical ownership conflicts.
4. Add abstracts and summaries to high-impact documents.
5. Add authority and supersession metadata.
6. Add implementation and verification references where applicable.
7. Archive or supersede obsolete documents.
8. Regenerate all views after each migration batch.
9. Keep each pull request scoped to one coherent documentation area.

### Acceptance Criteria

- every active managed document appears in the catalog
- every active canonical document has valid ownership metadata
- all broken references are resolved
- superseded and archived material is excluded from default routes
- no manual routing list remains authoritative

### Validation

- full knowledge validation passes
- generated output is current
- owner review is complete for each migrated area

### Dependencies

KA-12.

## Task KA-14: Evaluate Need for Semantic Search

### Objective

Determine whether metadata routing and repository search leave meaningful retrieval gaps.

### Evaluation Questions

- Are users or agents failing to find relevant documents despite correct metadata?
- Are topic tags too expensive to maintain relative to search quality?
- Are natural-language queries regularly missing exact terminology?
- Is the documentation set large enough that deterministic search latency or result volume is a problem?
- Would hybrid search improve recall without making authority ranking less predictable?

### Steps

1. Collect failed or inefficient retrieval examples after rollout.
2. Categorize failures as missing metadata, bad routing, missing documentation, terminology mismatch, or scale.
3. Fix metadata and routing defects before testing embeddings.
4. If failures remain, run a limited offline hybrid-search experiment.
5. Require authority and status filtering before semantic ranking.
6. Compare precision, recall, token use, maintenance cost, and explainability.
7. Create a separate decision record before adopting external storage or embeddings.

### Acceptance Criteria

- the decision is evidence-based
- semantic search is not adopted solely because it is LLM-oriented
- any proposed system preserves canonical authority and default exclusions
- operating and maintenance costs are documented

### Validation

- evaluate against a fixed retrieval test set
- compare deterministic and hybrid results using the same task queries

### Dependencies

KA-13 and a representative usage period.

## 14. Quality Evaluation Matrix

Before reducing default context or changing routing behavior, test at least the following scenarios:

| Scenario | Expected Required Knowledge | Quality Check |
| --- | --- | --- |
| Explain current combat scheduling | combat contract | correct 20-tick cumulative scheduling without loading roadmap history |
| Change damage calculation | combat math, relevant implementation and tests | retrieves formulas and validation sources, not unrelated UX docs |
| Improve target-resolution visibility | targeting contract, combat viewer UX, onboarding | distinguishes server behavior from player-facing explanation |
| Plan a future biome | world/lore and directional progression docs | does not treat roadmap direction as implemented behavior |
| Modify API behavior | API contract, data model, implementation and tests | identifies intended and implemented truth separately |
| Triage active work | active issues, milestones, backlog operations | does not load archived issues |
| Explain why a design choice was made | current contract plus linked decision | loads rationale only after the question requires it |
| Review release readiness | release gates, testing strategy, active milestone | avoids loading unrelated game-system detail |

For each scenario, record:

- documents and sections loaded
- estimated input tokens
- correctness
- omissions
- irrelevant context
- authority mistakes
- whether implementation references were sufficient

A rollout should not advance if lower token use is achieved by omitting information needed to detect an incorrect answer.

## 15. Suggested Pull Request Sequence

To keep changes reviewable, use the following PR sequence:

1. Baseline measurement and metadata schema.
2. Catalog generator and validation fixtures.
3. High-value document metadata migration.
4. Generated index and context router.
5. Knowledge lint and token reporting.
6. Decision and source templates plus ingestion pilot.
7. Implementation maps and initial context bundles.
8. Default-context reduction and startup integration.
9. Remaining documentation migration in area-specific batches.
10. Semantic-search evaluation, only if justified by evidence.

Each PR should include:

- exact scope
- affected document IDs
- migration or compatibility behavior
- generated artifacts changed
- validation performed
- token impact when measurable
- any unresolved ownership decisions

## 16. Risks and Mitigations

### Risk: Metadata Becomes Another Maintenance Burden

Mitigation:

- keep fields limited to retrieval, authority, and validation needs
- generate all repeated views from metadata
- add clear validation messages
- migrate incrementally

### Risk: Summaries Drift From Detailed Contracts

Mitigation:

- keep summaries in the same reviewed document
- require owner review
- add summary-to-source traceability in generated bundles
- do not let generated summaries become canonical

### Risk: Excessive Fragmentation

Mitigation:

- split documents only when ownership, change cadence, or retrieval patterns justify it
- use section-level retrieval before creating new files
- review dependency depth and orphan counts

### Risk: Token Reduction Removes Necessary Context

Mitigation:

- use the quality evaluation matrix
- require smallest sufficient authoritative context, not simply smallest context
- retain direct project-wide constraints in `AGENTS.md`
- roll out warning-only measurements before enforcement

### Risk: Canonical Documentation Conflicts With Code

Mitigation:

- distinguish design authority from implementation authority
- include implementation and verification references
- report mismatches explicitly
- avoid automatic synchronization of behavioral claims

### Risk: Generated Files Are Edited Manually

Mitigation:

- include generated warnings
- add drift validation
- document the regeneration command
- keep authoritative metadata in source documents only

### Risk: Semantic Search Obscures Authority

Mitigation:

- defer semantic search
- filter by status and authority before ranking
- retain exact source references in all results
- require a decision record before adoption

## 17. Operational Ownership

Recommended ownership:

- Product and Systems Design: canonical gameplay and UX ownership
- Engineering: architecture, implementation references, tooling, and CI
- QA: verification references and evaluation scenarios
- Documentation maintainers or assigned feature owners: metadata quality and summaries
- Agents: generation, validation, reports, and proposed updates

No generated system should be the sole owner of a current-state contract.

## 18. Definition of Done

The full initiative is complete when:

- all active managed documents have stable IDs and valid metadata
- canonical ownership conflicts are resolved
- the catalog, index, router, reports, and context bundles are generated deterministically
- startup uses compact generated discovery instead of duplicated routing lists
- default context meets the approved budget or documented exceptions
- archived, superseded, source, and historical content is excluded by default
- decision and source workflows have been proven with at least one real ingestion
- implementation maps cover the highest-use systems
- documentation and token checks run in CI
- the quality evaluation matrix shows no material regression
- remaining retrieval failures have been categorized and addressed
- any semantic-search adoption has a separate evidence-backed decision record

## 19. Immediate Next Actions

After this proposal is accepted, create the first implementation issues in this order:

1. KA-01: baseline measurements.
2. KA-02: metadata schema and migration contract.
3. KA-03: parser and catalog generator.
4. KA-04: high-value document migration.
5. KA-05: generated index and router.

Do not begin default-context reduction until the catalog, routing, validation, and evaluation foundations are available. This preserves quality while making token reductions measurable and reversible.

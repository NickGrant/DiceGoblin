# Current Status Evaluation
----

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering + QA  
Depends On: `ROLES.md`, `ISSUES.md`, `MILESTONES.md`, `documentation/README.md`

## Purpose
- Run a structured multi-role quality pass over codebase and documentation.
- Convert findings into actionable issue/milestone updates.
- Reconcile role perspectives before execution planning.

## Trigger Phrase
- `run current status evaluation`
- `current status evaluation`

## Workflow
1. Load context sources:
   - `ROLES.md`
   - `ISSUES.md`
   - `MILESTONES.md`
   - key code/docs relevant to current roadmap
2. Cycle through each role in order listed in `ROLES.md`.
3. For each role in cycle 1:
   - evaluate codebase and documentation
   - produce top 5 concerns
   - add/update issues and milestones for those concerns
4. After all roles complete cycle 1, run cycle 2 through all roles:
   - re-evaluate while considering issues created by other roles
   - determine if new issues are needed
   - revise existing issues/milestones where overlap, dependency, or priority changes are found
5. Validate backlog coherence:
   - issue titles referenced by milestones exist in `ISSUES.md`
   - milestone descriptions match issue scope
   - statuses and execution flags remain valid
6. Report outputs:
   - top concerns per role
   - issues added/revised
   - milestones added/revised
   - unresolved conflicts or open questions

## Output Expectations
- Add concrete issues with clear role attribution and implementation intent.
- Add milestone groupings when concerns form an execution lane.
- Prefer revising existing issues over duplicating semantically equivalent items.

## Quality Rules
- Preserve compatibility-critical naming where required (routes/tables/public contracts), even when terminology updates are requested.
- If role concerns conflict, document the conflict and choose the least risky sequencing path.
- Keep changes scoped to backlog/governance unless user explicitly requests implementation.

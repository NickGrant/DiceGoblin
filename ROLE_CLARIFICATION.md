# Role Clarification Log
----

## Purpose
- Track role-definition gaps discovered during role-based evaluations.
- Capture what additional role guidance would improve decision quality.

## Usage
- Add an entry whenever a role evaluation would benefit from a clearer definition.
- Use this exact entry format:
  - `name: <role name>`
  - `decision: <brief summary of decision made>`
  - `definition: <aspect of the role to better define>`

## Entries
- name: Technical Product Manager
  decision: Added documentation-contract issues directly into Milestones 4-6 to unblock execution planning for previously empty milestones.
  definition: Clarify when TPM should place doc-only work inside implementation milestones versus creating separate governance milestones.

- name: Combat Systems Reviewer
  decision: Added encounter reward-surface and dice-visualization issues that overlap UX presentation concerns.
  definition: Define the boundary between combat-rules clarity responsibilities (Combat Systems Reviewer) and presentation/usability responsibilities (Game Designer).

- name: QA Lead
  decision: Added a manual stale-state recovery checklist issue under Milestone 6 instead of only automation tasks.
  definition: Define minimum evidence required for manual validation artifacts and when manual checks are acceptable substitutes before adding automation.

- name: Backlog Curator
  decision: Raised a follow-up issue to trim active tracking docs after governance completion because guardrails are currently exceeded.
  definition: Clarify whether context-budget guardrails are hard stop criteria or soft targets when the user explicitly requests large multi-role backlog expansion.

- name: Technical Product Manager
  decision: Raised a post-governance issue to select/open the next current milestone after archiving Milestone 9.
  definition: Define whether milestone transition should happen automatically when a current milestone is archived complete, or only on explicit user confirmation.

- name: Technical Product Manager
  decision: Consolidated three overlapping governance docs into a single canonical backlog-operations source.
  definition: Clarify TPM authority boundaries for consolidating policy docs directly versus raising consolidation-only issues first.

- name: Game Designer
  decision: Chose a shared end-of-run summary shell and embedded promotion in unit details while keeping dice inventory as a dedicated screen.
  definition: Clarify default UI placement rules when a run-scoped context (rest) temporarily enables out-of-run management actions.

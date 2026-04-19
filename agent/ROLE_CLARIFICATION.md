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
- name: QA Lead
  decision: Identified uncertainty around how much testability detail a spec needs before implementation should begin.
  definition: Define minimum QA/testability metadata for specs, including when acceptance criteria, state transitions, failure behavior, manual evidence, and automation expectations are required.

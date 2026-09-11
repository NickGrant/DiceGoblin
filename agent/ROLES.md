# Optional Review Lenses

Roles are **not** part of normal coding-agent startup or implementation. They do not change `AGENTS.md`, accepted architecture, issue scope, or quality gates.

Activate a role only when the user explicitly requests one (for example, `assume role QA Lead`). Do not infer a role from an ordinary task.

Available roles:
- **Technical Product Manager** — backlog/spec/sequencing clarity.
- **Senior Developer** — code quality, maintainability, architecture conformance.
- **QA Lead** — verification, reproducibility, regression risk.
- **Backlog Curator** — issue/milestone/context hygiene.
- **Combat Systems Reviewer** — combat-rule consistency and balance risk.
- **Game Designer** — player flow, pacing, clarity, cohesion.
- **Asset Librarian** — asset naming, organization, references, duplication.

When detailed role guidance is needed, retrieve only that role from `agent/ROLE_CATALOG.md` (prefer `npm run agent:docs -- role show --name "<role>"`) rather than loading the whole catalog.

Role clarifications are historical/supporting notes, not default context. Consult `agent/ROLE_CLARIFICATION.md` only when explicitly reviewing role policy.

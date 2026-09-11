# Context Router

Use this only when the current issue/source does not make the required authority obvious. Load the narrow file, not its whole directory.

| Task / question | Load |
| --- | --- |
| Overall vNext sequence/status | `documentation/07-development-path/vnext-game-overhaul.md` |
| Prototype reuse/migration | `documentation/07-development-path/vnext-prototype-code-disposition.md` |
| Backend layering/transactions | `documentation/07-development-path/vnext-backend-internal-architecture.md` |
| API semantics | `documentation/07-development-path/vnext-api-contract-model.md`; endpoint names only if needed: `vnext-endpoint-inventory.md` |
| Database/runtime storage | `documentation/07-development-path/vnext-storage-model.md` |
| Authored JSON/content ownership | `documentation/07-development-path/vnext-authored-content-model.md` |
| Phaser/Angular boundary, client state, responsive rules | `documentation/07-development-path/vnext-phaser-client-architecture.md` |
| Rewards/unlocks/idempotency | `documentation/07-development-path/vnext-reward-unlock-model.md` |
| Currency/economy | `documentation/07-development-path/vnext-currency-economy-model.md` |
| Energy | `documentation/07-development-path/vnext-energy-model.md` |
| Progression/Codex/objectives | `documentation/07-development-path/vnext-progression-state-model.md` |
| Preserved gameplay/combat rule | `documentation/02-systems/README.md`, then one relevant system doc |
| Visual direction | `documentation/04-ux/01-visual-design-guide.md` |
| Product terminology/core loop | `documentation/00-overview/02-glossary.md` and, only if needed, `01-core-gameplay-loop.md` |
| Lore/character voice | relevant file under `documentation/01-lore/` |
| Testing philosophy | `documentation/06-testing-release/00-testing-strategy.md` |
| Engineering/documentation policy | relevant file under `documentation/08-operations/` |
| Base-game biome allocation | `documentation/07-development-path/01-base-game-content-roster.md` |

Rules:
- Accepted vNext decisions override conflicting prototype implementation shape.
- Source/tests are required evidence when replacing behavior, but are not architectural authority.
- Markdown content catalogs are design/migration references until their domain moves to canonical authored JSON.
- Do not search Git history or deleted docs for current requirements unless the user explicitly asks for history/recovery.

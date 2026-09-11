---
Title: "Documentation Index"
Status: Canonical
Last Updated: 2026-09-10
Owner: Product + Engineering
Depends On:
  - AGENTS.md
  - agent/CONTEXT_ROUTER.md
Category: documentation
Tags:
  - documentation
  - index
---

# Documentation Index

## Purpose
This branch's documentation is working project context, not a historical archive. It contains only guidance intended to help build, understand, test, or author the vNext game.

Superseded proposals, prototype implementation contracts, dated audits, release evidence, and partially implemented experiments are intentionally removed. Git history is the archive.

## Authority
- `07-development-path/vnext-game-overhaul.md` is the vNext implementation/status roadmap.
- Accepted `07-development-path/vnext-*.md` files are authoritative for their defined vNext decisions until those decisions are folded into long-lived canonical docs after implementation.
- `00-overview/` owns product orientation and terminology.
- `01-lore/` owns durable setting and character voice.
- `02-systems/` owns preserved gameplay rules that remain relevant to vNext.
- `03-content/` contains retained content-design references during migration; canonical authored runtime content moves to JSON as vNext milestones implement it.
- `04-ux/` owns durable visual direction; detailed Phaser layout/interaction architecture currently lives in the accepted vNext client document.
- `05-technical/` contains only stable platform-level technical summaries; detailed vNext contracts currently live in `07-development-path/`.
- `06-testing-release/` owns vNext verification principles.
- `08-operations/` owns engineering and documentation standards.

## Recommended Read Order
1. `07-development-path/vnext-game-overhaul.md`
2. the relevant accepted `07-development-path/vnext-*.md` decision documents
3. the narrow overview/system/content/UX document needed for the task
4. implementation and tests when migration evidence is needed

Do not use Git history or removed prototype docs as current design authority unless the user explicitly asks to recover or reconsider an old decision.

## Documentation Hygiene
- Current intent belongs in the active tree.
- History belongs in Git.
- Do not add `Legacy Reference`, `Superseded`, dated audit, or completed-roadmap files to active documentation merely to preserve context.
- When a vNext milestone replaces an old behavior, update/delete conflicting documentation in the same work.
- Avoid duplicating accepted vNext rules across many files; reference their authoritative document.

---
Title: "Technical Documentation"
Status: Canonical
Last Updated: 2026-09-10
Owner: Engineering
Depends On:
  - documentation/README.md
  - documentation/07-development-path/vnext-game-overhaul.md
Category: 05-technical
Tags: [technical, vnext]
---

# Technical Documentation

This folder keeps only stable platform summaries during the vNext overhaul:
- `00-tech-stack.md`
- `01-authentication-and-sessions.md`

Detailed current vNext architecture, storage, API, endpoint, authored-content, backend-internal, and Phaser-client decisions are intentionally maintained as accepted decision records under `07-development-path/` while implementation is in flux.

The previous Angular-first frontend architecture, page-oriented API contract, prototype data model, seed/SQL catalog ownership, component inventory, hybrid Phaser boundary, domain-event evaluation, and prototype reset review were removed from this branch because they conflict with the accepted vNext target.

After implementation stabilizes, durable accepted vNext decisions should be folded back into canonical `05-technical` documents rather than preserving two parallel specifications.

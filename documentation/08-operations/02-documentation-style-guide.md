---
Title: "Documentation Style Guide"
Status: Canonical
Last Updated: 2026-09-10
Owner: Engineering
Depends On:
  - documentation/README.md
Category: 08-operations
Tags: [operations, documentation]
---

# Documentation Style Guide

## Purpose
Keep documentation compact, current, and safe for human and coding-agent retrieval.

## Active-Tree Rule
The active vNext documentation tree describes current intent. Git history is the archive.

Do not keep superseded proposals, completed audits, old release evidence, abandoned experiments, or prototype implementation contracts as `Legacy Reference` files in the active tree. Recover history from Git only when needed.

## Metadata
High-impact Markdown documents include `Status`, `Last Updated`, `Owner`, `Depends On`, `Category`, and `Tags`.

## Writing
- Start with purpose/scope or the governing decision.
- State durable rules, not implementation archaeology.
- Prefer one authoritative owner for a concept and link to it rather than duplicating policy.
- Distinguish authored game rules from current prototype implementation evidence.
- Do not document unresolved speculation as if it were an accepted target.
- Keep transitional vNext decision records under `07-development-path/` until implementation stabilizes, then reconcile durable concepts into canonical system/technical docs.

## Hygiene
When a contract changes, update/delete conflicting docs and routing references in the same work. Broken references to removed documents are defects, not historical breadcrumbs.

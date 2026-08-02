---
Title: "Documentation Style Guide"
Status: Canonical
Last Updated: 2026-08-01
Owner: Engineering
Depends On:
  - documentation/README.md
Category: 08-operations
Tags:
  - operations
---

# Documentation Style Guide

## Purpose
- Keep docs consistent, compact, and machine-readable.

## Encoding and Characters
- UTF-8 for markdown files.
- Prefer ASCII punctuation (`-`, `->`, straight quotes).
- Avoid mojibake artifacts.

## Required Metadata (High-Impact Docs)
- `Status`
- `Last Updated` (YYYY-MM-DD)
- `Owner`
- `Depends On`

## Structure Rules
- Start with purpose/scope.
- Prefer short sections and direct references to source-of-truth docs.
- Avoid duplicated policy text; reference canonical docs instead.

## Hygiene Rules
- Active work in `agent/ISSUES.md` and `agent/MILESTONES.md`.
- Archive completed items promptly.
- Record major doc contract changes in `documentation/07-development-path/CHANGELOG.md`.

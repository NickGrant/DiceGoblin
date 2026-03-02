# Documentation Style Guide
----

Status: active  
Last Updated: 2026-03-02  
Owner: Product + Engineering  
Depends On: `documentation/README.md`

## Purpose
- Keep project docs machine-readable, consistent, and low-noise for both humans and LLM workflows.

## Encoding and Characters
- Use UTF-8 encoding for markdown files.
- Prefer plain ASCII punctuation for portability:
  - use `-` instead of en/em dashes
  - use `->` instead of arrow glyphs
  - use straight quotes (`"` and `'`) instead of curly quotes
- Avoid mojibake artifacts (for example `-`, `->`, `"`).

## Required Metadata (High-Impact Docs)
High-impact docs should include:
- `Status` (`active`, `draft`, `deprecated`, `superseded`)
- `Last Updated` (YYYY-MM-DD)
- `Owner`
- `Depends On`

## Structure Conventions
- Start with purpose/scope before detailed content.
- Keep headings short and predictable.
- Prefer concise sections over long narrative blocks.
- Keep references to source-of-truth files explicit.

## Backlog and Change Hygiene
- Track active work in `ISSUES.md`.
- Move completed issue entries to `ISSUES_ARCHIVE.md`.
- Record major documentation contract/roadmap changes in `documentation/CHANGELOG.md`.

# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Hybrid Seed Catalog Ownership

### HDC-003: Add hybrid catalog slug parity tests

**Milestone:** Hybrid Seed Catalog Ownership
**Status:** Open
**Priority:** High

#### Problem

Hybrid-owned catalog rows reference behavior-bearing slugs for abilities, affixes, bounty objectives, enemy loadouts, and encounter content. Without explicit parity tests, seeded rows can drift away from code handlers or reference missing catalog records.

#### Acceptance Criteria

- Add backend coverage that verifies unit and enemy ability slugs resolve through the ability registry.
- Verify behavior-bearing affix definitions use supported behavior kinds or handlers.
- Verify bounty objective kinds resolve to backend objective evaluation support.
- Verify encounter templates reference seeded enemy templates and valid region context where applicable.
- Keep tests focused on seeded catalog integrity without changing runtime behavior.

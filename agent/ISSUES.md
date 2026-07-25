# ISSUES FILE
----
Active issues only. Move completed entries to `agent/ISSUES_ARCHIVE.md`.

## Wrong Machine and Kin Foundation

### KRB-003: Add Wrong Machine reconstruction transaction

**Milestone:** Wrong Machine and Kin Foundation
**Status:** Open
**Priority:** High

#### Problem

Pig Kin should be unlocked through a backend-authoritative Wrong Machine reconstruction flow rather than appearing only through random kin assignment. Reconstruction needs a cost preview, transactional spending, lineage unlock grant, and tutorial unit grant without consuming materials on failed or duplicate requests.

#### Acceptance Criteria

- Add a backend service/API surface for reconstructing the first explicit lineage.
- Require the Wrong Machine feature before reconstruction actions succeed.
- Preview Pig Kin costs from backend-owned item and Raw Chaos requirements.
- Spend required materials and boss catalysts only when reconstruction succeeds.
- Grant the Pig Kin lineage and a Pig Kin unit atomically with the spend.
- Make duplicate reconstruction idempotent and never double-spend resources.
- Keep old migrations untouched.
- Do not add new `region_items` dependencies.

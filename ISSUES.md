# ISSUES FILE
----
Active issues only. Move completed entries to `ISSUES_ARCHIVE.md`.

---
title: Define modal abstraction contract and migration plan
status: in-progress
priority: high
execution: active
ready: yes
milestone: Milestone 21 - Reusable Modal Architecture
description: Define BaseModal API and composition model (backdrop, frame, title, body, action row, keyboard lifecycle, close semantics) with concrete migration plan for current ConfirmationDialog and input-enabled modal flows.

---
title: Implement BaseModal and yes/no confirmation variant
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 21 - Reusable Modal Architecture
description: Implement reusable BaseModal and ConfirmModal variant for standard yes/no actions with shared sizing, spacing, button alignment, and callback behavior.

---
title: Implement InputModal by extending confirmation modal
status: unstarted
priority: high
execution: active
ready: yes
milestone: Milestone 21 - Reusable Modal Architecture
description: Extend modal stack with InputModal that reuses confirmation action surface while adding typed input, caret editing, allowed-character filtering, and enter/escape handling.

---
title: Migrate existing modal call sites to new modal hierarchy
status: unstarted
priority: medium
execution: active
ready: yes
milestone: Milestone 21 - Reusable Modal Architecture
description: Replace direct ConfirmationDialog usages in map, warband, and squad scenes with BaseModal-derived variants and remove duplicate layout logic.

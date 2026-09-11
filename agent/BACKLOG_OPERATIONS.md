# Backlog Operations

## Authority
- Full roadmap/status: `documentation/07-development-path/vnext-game-overhaul.md`.
- Active milestone/package queue: `agent/MILESTONES.md`.
- Current executable package only: `agent/ISSUES.md`.
- Backlog files contain explicitly deferred work only; they are not alternate roadmaps.
- Git history is completed-work history. Do not maintain archive files.

## Just-in-Time Workflow
1. Keep exactly one normal execution package in `agent/ISSUES.md` unless parallel work is explicitly requested.
2. Implement only that package against accepted vNext decisions.
3. Run applicable `agent/QUALITY_GATES.md` checks.
4. When its acceptance criteria pass, reflect completion in the roadmap/milestone state and remove the completed issue text.
5. Promote/decompose the next package from `agent/MILESTONES.md` into concise, implementation-ready acceptance criteria.
6. Do not pre-write detailed criteria for distant blocked packages; later architecture/implementation may make them stale.

Prefer walking slices and shallow dependency chains. Do not create separate competing frontend/backend/database roadmaps.

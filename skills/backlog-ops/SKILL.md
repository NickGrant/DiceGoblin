# backlog-ops

## Purpose
Reusable workflow for active issue/milestone maintenance and deferred backlog movement in this repo.

## When To Use
- User asks to triage/update issues or milestones.
- Architectural review approves a package and the next package must be promoted.
- User asks for backlog consistency validation.

## Workflow
1. Run validation:
   - `npm run llm:check`
2. Apply the requested lifecycle edits:
   - `agent/ISSUES.md` contains exactly the current executable package;
   - `agent/MILESTONES.md` contains the active milestone and short package queue;
   - `agent/ISSUES_BACKLOG.md` / `agent/MILESTONES_BACKLOG.md` contain explicitly deferred work only.
3. When work is approved complete:
   - reflect completion in the roadmap/milestone state as required;
   - remove completed execution text from active files;
   - rely on Git history for completed-work history rather than creating or moving to archive files;
   - promote only the next implementation-ready package.
4. Re-run validation:
   - `npm run llm:check`
5. Report only:
   - lifecycle updates made;
   - new active package, if promoted;
   - blockers.

## Rules
- Preserve exact package/milestone titles when updating lifecycle state.
- Do not retain completed packages in `agent/ISSUES.md`.
- Do not create parallel archive files; Git history is the archive.
- Do not pre-write detailed distant packages.
- Keep active files lean and execution-focused.

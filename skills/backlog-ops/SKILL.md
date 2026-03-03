# backlog-ops

## Purpose
Reusable workflow for issue/milestone lifecycle maintenance in this repo.

## When To Use
- User asks to triage/update issues or milestones.
- User asks to close/archive completed backlog items.
- User asks for backlog consistency validation.

## Workflow
1. Run validation:
   - `npm.cmd run llm:check`
2. Apply requested issue/milestone edits:
   - update `ISSUES.md` / `MILESTONES.md` for active execution
   - update `ISSUES_BACKLOG.md` / `MILESTONES_BACKLOG.md` for deferred planning
3. Archive completed items:
   - move completed issues to `ISSUES_ARCHIVE.md`
   - move completed milestones to `MILESTONES_ARCHIVE.md`
4. Re-run validation:
   - `npm.cmd run llm:check`
5. Report:
   - completed updates
   - remaining active items
   - blockers

## Rules
- Preserve exact issue/milestone titles during moves.
- Never keep completed items in active files.
- Keep `ISSUES.md` and `MILESTONES.md` lean and execution-focused.

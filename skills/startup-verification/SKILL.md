# startup-verification

## Purpose
Standard startup hygiene check for LLM operating docs before task execution.

## When To Use
- Beginning of a new working session.
- Before major backlog/doc workflow operations.
- After large markdown restructuring changes.

## Workflow
1. Run startup and backlog checks:
   - `npm.cmd run startup:check`
   - `npm.cmd run backlog:validate`
2. If checks fail:
   - fix missing files/invalid enum fields/reference errors
   - re-run checks until clean
3. If checks pass:
   - proceed with requested task

## Output
- Explicitly report pass/fail for both checks.
- If failing, list top actionable failures first.

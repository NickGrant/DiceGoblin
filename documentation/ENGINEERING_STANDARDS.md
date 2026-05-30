# Engineering Standards
----

Status: active  
Last Updated: 2026-05-29  
Owner: Engineering  
Depends On: `documentation/TESTING_STRATEGY.md`, `documentation/01-architecture/05-angular-frontend-architecture-plan.md`, `documentation/01-architecture/06-angular-component-service-inventory.md`

## Purpose
- Define project-wide coding standards for frontend implementation and shared engineering practices.
- Keep TypeScript, HTML, SCSS, testing, and architecture decisions consistent.

## Scope
- These standards apply to active source code in `frontend/src/`.
- Backend code may follow local backend idioms, but testing and architecture expectations still apply.

## Test Coverage

### Minimum Expectations
- Every behavior change should be verified at the level where the behavior lives.
- New user-facing logic should ship with automated coverage unless the change is purely presentational.
- Bug fixes should add a regression test when the failure can be reproduced in an automated way.

### Frontend Coverage Rules
- Services:
  - cover success paths
  - cover important failure handling
  - cover state refresh or mutation behavior
- Page components:
  - cover route-driven state
  - cover loading, empty, error, and success states when present
  - cover CTA enable/disable behavior when it affects user flow
- Shared UI primitives:
  - test logic, accessibility, and stateful behavior
  - do not add shallow tests for style-only wrappers

### Coverage Priorities
- High priority:
  - authentication/session bootstrap
  - run progression
  - profile-refreshing mutations
  - squad/unit/dice management
  - shop purchases
- Medium priority:
  - page-level conditional rendering
  - reusable directives/components with non-trivial inputs
  - debug tooling
- Low priority:
  - static markup-only sections
  - purely decorative SCSS changes verified visually

### When Manual Verification Is Enough
- Cosmetic spacing, typography, and art-placement changes can rely on screenshot/manual verification when no logic changed.
- Manual-only verification is not enough for new service logic, mutation flows, or contract-sensitive state handling.

## SCSS Standards
- Keep styles close to the feature that owns them:
  - page styles in page SCSS
  - layout styles in layout SCSS
  - shared primitive styles only when reused
- Prefer existing tokens and variables from `frontend/src/styles.scss` before adding new raw colors.
- Use class-based styling; avoid broad tag selectors unless scoped inside a component stylesheet.
- Avoid deep selector chains and brittle DOM-coupled selectors.
- Favor readable layout primitives:
  - flex
  - grid
  - spacing via gap/padding/margin
- Do not encode product logic in SCSS class names.
- Keep motion subtle and purposeful.
- For image-backed layouts:
  - use explicit sizing and alignment rules
  - verify with screenshots
  - use art-aligned offsets only when the layout intentionally depends on the art

## HTML Standards
- Keep templates declarative and readable.
- Move transformation logic out of templates and into component fields, computed values, or helper methods.
- Prefer semantic elements when they improve meaning:
  - `button` for actions
  - `a` for navigation
  - headings in a valid hierarchy
- Accessibility is required:
  - meaningful text or `aria-label` for controls
  - keyboard reachability
  - status/alert semantics in shared feedback components
- Avoid duplicated structural shells when a shared component already exists.
- Split large templates into smaller components before they become difficult to scan.

## TypeScript Standards
- Prefer typed contracts over `any`.
- Keep data mapping and mutation logic in services or clearly owned page-level state, not scattered across templates.
- Use Angular signals/computed state for local reactive state in the current frontend architecture.
- Prefer `inject()` in standalone Angular code for consistency with the existing codebase.
- Keep component classes focused on view state and orchestration.
- Services should own:
  - API calls
  - mutation flows
  - state refresh/invalidation
  - payload shaping when reused across pages
- Avoid large utility-style god files.
- Add helper methods when they improve clarity, but avoid abstraction that hides simple intent.
- Default to explicit return types on exported functions and non-trivial methods.

## Code Architecture

### Ownership Boundaries
- Pages own route-level composition.
- Layout components own shell framing and persistent navigation.
- Shared UI components own reusable presentation patterns.
- Services own network access and domain mutations.
- Models define typed contracts shared across pages and services.

### Preferred Structure
- Keep files in feature folders:
  - `pages/<feature-page>/`
  - `layout/<feature>/`
  - `shared/ui/<primitive>/`
  - `core/services/<domain>/`
- Extend existing feature folders before introducing new top-level patterns.

### Architectural Rules
- Do not let pages call raw `fetch`; all HTTP goes through the API service layer.
- Do not let templates assemble backend payloads.
- Keep session/profile refresh behavior centralized instead of reimplemented per page.
- Reuse shared primitives before duplicating frame, alert, or command-button patterns.
- Introduce a new shared component only when:
  - the structure is repeated
  - the naming is stable
  - the abstraction reduces duplication without hiding intent
- Prefer directives over wrapper components when behavior must apply to multiple native elements.

## Review Checklist
- Does the change follow the existing route/service/component ownership model?
- Is the logic covered by the right level of automated test?
- Is the template readable without embedding business logic?
- Is the SCSS scoped, maintainable, and aligned with project tokens?
- Did the change reuse an existing shared primitive where appropriate?
- Were docs updated if contracts or engineering conventions changed?

## References
- `documentation/TESTING_STRATEGY.md`
- `documentation/01-architecture/05-angular-frontend-architecture-plan.md`
- `documentation/01-architecture/06-angular-component-service-inventory.md`
- `documentation/03-ux/08-page-layout-zones.md`

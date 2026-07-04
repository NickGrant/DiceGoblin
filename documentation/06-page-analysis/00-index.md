# Page Analysis Index

Status: active  
Last Updated: 2026-07-04  
Owner: UX + Frontend  
Depends On: `documentation/01-architecture/02-frontend-state-and-scene-contracts.md`, `frontend/src/app/app.routes.ts`, `frontend/src/app/pages/`

## Purpose

- Provide a route-by-route UX inventory of the current frontend.
- Capture what appears on each page and what data the page surfaces.
- Support UX redesign, content planning, and implementation audits without requiring a code read first.

## Shared Shell Notes

Authenticated routes share the global command HUD defined in `frontend/src/app/layout/command-controls/`.

That shell currently displays:

- commander energy as `energyCurrent / energyMax`
- soft currency as teeth
- commander display name
- primary nav icons for home, warband, inventory, shop, and field guide
- logout and mobile menu controls

Public routes still render the global HUD, but with guest-safe behavior:

- a login button instead of the authenticated commander identity
- the guide remains reachable
- non-public nav items appear unavailable

## Route Inventory

- [01-login.md](./01-login.md)
- [02-guide-public.md](./02-guide-public.md)
- [04-home.md](./04-home.md)
- [05-field-guide.md](./05-field-guide.md)
- [06-academy.md](./06-academy.md)
- [07-regions.md](./07-regions.md)
- [08-warband.md](./08-warband.md)
- [09-squad-details.md](./09-squad-details.md)
- [10-unit-details.md](./10-unit-details.md)
- [11-dice-inventory.md](./11-dice-inventory.md)
- [12-shop.md](./12-shop.md)
- [13-run-map.md](./13-run-map.md)
- [14-run-node.md](./14-run-node.md)
- [15-run-rest.md](./15-run-rest.md)
- [16-run-summary.md](./16-run-summary.md)
- [17-debug.md](./17-debug.md)

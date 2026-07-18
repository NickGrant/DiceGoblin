# Screen Pack

Generated on 2026-07-14 from branch `agent/ui-prototype-pages`.

## Screens

- `login` - [login.png](./login.png) - public route captured successfully
- `guide` - [guide.png](./guide.png) - public guide captured successfully
- `field-guide` - [field-guide.png](./field-guide.png) - authenticated codex-style guide captured successfully
- `home` - [home.png](./home.png) - updated game-style home hub captured successfully
- `regions` - [regions.png](./regions.png) - biome select captured successfully
- `warband` - [warband.png](./warband.png) - unit inventory / warband overview captured successfully
- `unit-details` - [unit-details.png](./unit-details.png) - direct parameterized route captured successfully
- `squad-details` - [squad-details.png](./squad-details.png) - direct parameterized route captured successfully
- `dice` - [dice.png](./dice.png) - dice inventory captured successfully
- `shop` - [shop.png](./shop.png) - route captured, but the page is currently showing a `Failed to fetch` banner in debug capture mode
- `academy` - [academy.png](./academy.png) - route captured, but the page is currently showing a `Failed to fetch` banner while still rendering shell content
- `run-map` - [run-map.png](./run-map.png) - route captured, but debug capture currently falls back to `No active run`
- `run-node` - [run-node.png](./run-node.png) - route captured, but debug capture currently stalls on encounter loading with a `Failed to fetch` banner
- `run-rest` - [run-rest.png](./run-rest.png) - route captured, but debug capture currently shows a `Failed to fetch` banner
- `run-summary` - [run-summary.png](./run-summary.png) - route captured, but debug capture currently falls back to `No run summary available`
- `debug` - [debug.png](./debug.png) - route captured, but the page is currently showing a `Failed to fetch` banner in debug capture mode

## Notes

- Session-backed pages capture reliably with debug auth enabled.
- API-backed pages still need either live authenticated backend data or dedicated debug service mocks to produce fully representative screenshots.

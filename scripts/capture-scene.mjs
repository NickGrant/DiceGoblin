import { mkdir } from "node:fs/promises";
import path from "node:path";
import process from "node:process";
import { setTimeout as delay } from "node:timers/promises";
import { spawn } from "node:child_process";
import { chromium } from "playwright";

const DEFAULT_HOST = "127.0.0.1";
const DEFAULT_PORT = "4173";
const DEFAULT_WAIT_TIMEOUT_MS = 45_000;
const DEFAULT_SETTLE_MS = 1_200;

function parseArgs(argv) {
  const options = {
    scene: "",
    output: "",
    baseUrl: "",
    host: DEFAULT_HOST,
    port: DEFAULT_PORT,
    auth: "authenticated",
    displayName: "Debug Goblin",
    userId: "debug-user",
    sceneData: "{}",
    initialTab: "",
    settleMs: DEFAULT_SETTLE_MS,
    timeoutMs: DEFAULT_WAIT_TIMEOUT_MS,
    useExistingServer: false,
    fullPage: false,
    width: 1440,
    height: 900,
    mobile: false,
    expectLayout: '',
    expectGate: '',
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    const next = argv[index + 1];

    switch (arg) {
      case "--scene":
        options.scene = next ?? "";
        index += 1;
        break;
      case "--output":
        options.output = next ?? "";
        index += 1;
        break;
      case "--base-url":
        options.baseUrl = next ?? "";
        index += 1;
        break;
      case "--host":
        options.host = next ?? DEFAULT_HOST;
        index += 1;
        break;
      case "--port":
        options.port = next ?? DEFAULT_PORT;
        index += 1;
        break;
      case "--auth":
        options.auth = next ?? "authenticated";
        index += 1;
        break;
      case "--display-name":
        options.displayName = next ?? options.displayName;
        index += 1;
        break;
      case "--user-id":
        options.userId = next ?? options.userId;
        index += 1;
        break;
      case "--scene-data":
        options.sceneData = next ?? "{}";
        index += 1;
        break;
      case "--initial-tab":
        options.initialTab = next ?? "";
        index += 1;
        break;
      case "--settle-ms":
        options.settleMs = Number.parseInt(next ?? `${DEFAULT_SETTLE_MS}`, 10);
        index += 1;
        break;
      case "--timeout-ms":
        options.timeoutMs = Number.parseInt(next ?? `${DEFAULT_WAIT_TIMEOUT_MS}`, 10);
        index += 1;
        break;
      case "--use-existing-server":
        options.useExistingServer = true;
        break;
      case "--full-page":
        options.fullPage = true;
        break;
      case "--width":
        options.width = Number.parseInt(next ?? '1440', 10);
        index += 1;
        break;
      case "--height":
        options.height = Number.parseInt(next ?? '900', 10);
        index += 1;
        break;
      case "--mobile":
        options.mobile = true;
        break;
      case "--expect-layout":
        options.expectLayout = next ?? '';
        index += 1;
        break;
      case "--expect-gate":
        options.expectGate = next ?? '';
        index += 1;
        break;
      case "--help":
        printHelp();
        process.exit(0);
        break;
      default:
        throw new Error(`Unknown argument: ${arg}`);
    }
  }

  if (!options.scene) {
    throw new Error("Missing required --scene argument.");
  }

  return options;
}

function printHelp() {
  console.log(`Usage:
  npm run capture:scene -- --scene <SceneNameOrAlias> [options]

Options:
  --output <path>            Output PNG path. Defaults to artifacts/screenshots/<scene>.png
  --base-url <url>          Use an already-running frontend URL instead of starting Vite
  --use-existing-server     Alias for using the provided base URL or current host/port
  --host <host>             Dev server host when auto-starting Vite (default: ${DEFAULT_HOST})
  --port <port>             Dev server port when auto-starting Vite (default: ${DEFAULT_PORT})
  --auth <mode>             authenticated | guest | live (default: authenticated)
  --display-name <name>     Debug display name for authenticated mode
  --user-id <id>            Debug user id for authenticated mode
  --scene-data <json>       JSON object passed to scene init/create
  --initial-tab <tab>       Optional debugInitialTab query value for tabbed scenes
  --settle-ms <ms>          Extra wait after the scene signals ready (default: ${DEFAULT_SETTLE_MS})
  --timeout-ms <ms>         Overall timeout waiting for app and scene readiness
  --full-page               Capture full page instead of viewport only
  --width <px>              Browser CSS viewport width (default: 1440)
  --height <px>             Browser CSS viewport height (default: 900)
  --mobile                  Emulate touch/mobile input capabilities
  --expect-layout <mode>    Require compact | standard | wide runtime mode
  --expect-gate <state>     Require active | inactive portrait-gate state
`);
}

async function installGameFixtureRoutes(page, options) {
  if (!['camp', 'camp-portrait'].includes(options.scene.trim().toLowerCase())) return;

  const revision = 'a'.repeat(64);
  await page.route('**/game-content.json', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      revision,
      content: {
        regions: {
          'region.the_farm': {
            id: 'region.the_farm',
            display_name: 'The Farm',
            art_key: 'farm',
          },
        },
        kin: {},
        unit_types: {},
        abilities: {},
        dice_materials: {},
        dice_aspects: {},
        dice_profiles: {},
      },
    }),
  }));
  await page.route('**/api/v1/game/bootstrap', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ok: true,
      data: {
        account: { id: options.userId, display_name: options.displayName, role: 'user' },
        player: {
          teeth: 1234,
          raw_chaos: 17,
          energy: {
            current: 57,
            normal_max: 50,
            regeneration_per_hour: 12,
            regeneration_interval_seconds: 300,
            last_regeneration_at: '2026-09-11T00:00:00Z',
            next_regeneration_at: null,
            fully_regenerated_at: null,
          },
          player_revision: 3,
        },
        session: { authenticated: true, csrf_token: 'debug-csrf-token' },
        server_time: '2026-09-11T00:00:00Z',
        content_revision: revision,
        progression: { unlock_ids: [] },
        active_squad: null,
        active_run: null,
      },
    }),
  }));
}

function createCaptureUrl(options) {
  const baseUrl = options.baseUrl || `http://${options.host}:${options.port}/`;
  const url = new URL(baseUrl);
  url.searchParams.set("debugScene", options.scene);
  url.searchParams.set("debugAuth", options.auth);
  url.searchParams.set("debugDisplayName", options.displayName);
  url.searchParams.set("debugUserId", options.userId);
  url.searchParams.set("debugSceneData", options.sceneData);
  url.searchParams.set("debugSettleMs", `${options.settleMs}`);
  if (options.initialTab) {
    url.searchParams.set("debugInitialTab", options.initialTab);
  }
  return url.toString();
}

function sanitizeName(value) {
  return value.replace(/[^a-z0-9-_]+/gi, "-").replace(/^-+|-+$/g, "").toLowerCase() || "scene";
}

async function ensureOutputPath(outputPath) {
  await mkdir(path.dirname(outputPath), { recursive: true });
}

async function readDebugState(page) {
  return page.evaluate(() => window.__DG_DEBUG__ ?? null).catch(() => null);
}

async function waitForHttp(url, timeoutMs) {
  const startedAt = Date.now();
  while (Date.now() - startedAt < timeoutMs) {
    try {
      const response = await fetch(url, { method: "GET" });
      if (response.ok) {
        return;
      }
    } catch {
      // Keep polling until timeout.
    }

    await delay(500);
  }

  throw new Error(`Timed out waiting for frontend at ${url}`);
}

async function isHttpAvailable(url, timeoutMs = 1000) {
  try {
    await waitForHttp(url, timeoutMs);
    return true;
  } catch {
    return false;
  }
}

function startDevServer(options) {
  const npmCommand = process.platform === "win32" ? "npm.cmd" : "npm";
  const child = spawn(
    npmCommand,
    [
      "--prefix",
      "frontend",
      "run",
      "dev",
      "--",
      "--host",
      options.host,
      "--port",
      options.port,
    ],
    {
      cwd: process.cwd(),
      stdio: "inherit",
      shell: process.platform === "win32",
    }
  );

  return child;
}

async function stopDevServer(child) {
  if (!child?.pid) return;
  if (process.platform !== 'win32') {
    child.kill();
    return;
  }

  await new Promise((resolve) => {
    const killer = spawn('taskkill', ['/pid', `${child.pid}`, '/T', '/F'], {
      stdio: 'ignore',
      windowsHide: true,
    });
    killer.once('close', resolve);
    killer.once('error', resolve);
  });
}

async function captureScene(options) {
  const outputPath =
    options.output || path.join("artifacts", "screenshots", `${sanitizeName(options.scene)}.png`);
  const baseUrl = options.baseUrl || `http://${options.host}:${options.port}/`;
  const captureUrl = createCaptureUrl(options);
  const shouldStartServer = !options.useExistingServer && !options.baseUrl && !(await isHttpAvailable(baseUrl));
  let serverProcess = null;
  /** @type {string[]} */
  const pageErrors = [];
  /** @type {string[]} */
  const requestFailures = [];
  /** @type {string[]} */
  const consoleMessages = [];

  try {
    if (shouldStartServer) {
      serverProcess = startDevServer(options);
    }

    await waitForHttp(baseUrl, options.timeoutMs);
    await ensureOutputPath(outputPath);

    const browser = await chromium.launch();
    try {
      const context = await browser.newContext({
        viewport: { width: options.width, height: options.height },
        isMobile: options.mobile,
        hasTouch: options.mobile,
      });
      const page = await context.newPage();
      await installGameFixtureRoutes(page, options);
      page.on("pageerror", (error) => {
        pageErrors.push(error instanceof Error ? error.message : String(error));
      });
      page.on("requestfailed", (request) => {
        requestFailures.push(`${request.url()} :: ${request.failure()?.errorText ?? "unknown failure"}`);
      });
      page.on("console", (message) => {
        consoleMessages.push(`${message.type()}: ${message.text()}`);
      });
      await page.goto(captureUrl, { waitUntil: "load", timeout: options.timeoutMs });
      try {
        const startedAt = Date.now();
        let debugState = null;
        while (Date.now() - startedAt < options.timeoutMs) {
          debugState = await readDebugState(page);
          if (
            debugState &&
            debugState.ready === true &&
            typeof debugState.readyScene === "string" &&
            typeof debugState.requestedScene === "string" &&
            debugState.readyScene === debugState.requestedScene
          ) {
            break;
          }

          await page.waitForTimeout(100);
        }

        if (
          !debugState ||
          debugState.ready !== true ||
          typeof debugState.readyScene !== "string" ||
          typeof debugState.requestedScene !== "string" ||
          debugState.readyScene !== debugState.requestedScene
        ) {
          throw new Error(`Timed out waiting for debug scene '${options.scene}' to become ready.`);
        }
        if (options.expectLayout) {
          await page.waitForSelector(
            `[data-game-layout="${options.expectLayout}"]`,
            { timeout: options.timeoutMs },
          );
        }
        if (options.expectGate) {
          await page.waitForSelector(
            `[data-game-orientation-gate="${options.expectGate}"]`,
            { timeout: options.timeoutMs },
          );
        }
        if (['camp', 'camp-portrait'].includes(options.scene.trim().toLowerCase())) {
          await page.waitForSelector('.game-host__mount canvas', { timeout: options.timeoutMs });
          await page.waitForSelector('[data-game-screen="camp"]', { timeout: options.timeoutMs });
          const runtimeMetrics = await page.evaluate(() => {
            const host = document.querySelector('.game-host__mount');
            const canvas = host?.querySelector('canvas');
            const rect = canvas?.getBoundingClientRect();
            return {
              hostWidth: host?.clientWidth,
              hostHeight: host?.clientHeight,
              canvasWidth: canvas?.width,
              canvasHeight: canvas?.height,
              canvasCssWidth: rect?.width,
              canvasCssHeight: rect?.height,
              layout: host?.getAttribute('data-game-layout'),
              gate: host?.getAttribute('data-game-orientation-gate'),
              screen: host?.getAttribute('data-game-screen'),
            };
          });
          console.log(`Runtime metrics: ${JSON.stringify(runtimeMetrics)}`);
        }
      } catch (error) {
        const debugState = await readDebugState(page);
        const diagnostics = [
          `Capture URL: ${captureUrl}`,
          `Debug state: ${JSON.stringify(debugState)}`,
        ];
        if (pageErrors.length > 0) {
          diagnostics.push(`Page errors: ${pageErrors.join(" | ")}`);
        }
        if (requestFailures.length > 0) {
          diagnostics.push(`Request failures: ${requestFailures.join(" | ")}`);
        }
        if (consoleMessages.length > 0) {
          diagnostics.push(`Recent console: ${consoleMessages.slice(-12).join(" | ")}`);
        }
        const message = error instanceof Error ? error.message : String(error);
        throw new Error([message, ...diagnostics].join("\n"));
      }

      if (options.settleMs > 0) {
        await page.waitForTimeout(options.settleMs);
      }

      if (options.expectLayout || options.expectGate) {
        const settledRuntimeState = await page.evaluate(() => {
          const host = document.querySelector('.game-host__mount');
          return {
            layout: host?.getAttribute('data-game-layout') ?? '',
            gate: host?.getAttribute('data-game-orientation-gate') ?? '',
          };
        });
        if (options.expectLayout && settledRuntimeState.layout !== options.expectLayout) {
          throw new Error(
            `Expected settled runtime layout '${options.expectLayout}', got '${settledRuntimeState.layout}'.`,
          );
        }
        if (options.expectGate && settledRuntimeState.gate !== options.expectGate) {
          throw new Error(
            `Expected settled orientation gate '${options.expectGate}', got '${settledRuntimeState.gate}'.`,
          );
        }
      }

      if (pageErrors.length > 0 || requestFailures.length > 0) {
        throw new Error([
          `Capture encountered browser errors for '${options.scene}'.`,
          pageErrors.length > 0 ? `Page errors: ${pageErrors.join(' | ')}` : '',
          requestFailures.length > 0 ? `Request failures: ${requestFailures.join(' | ')}` : '',
          consoleMessages.length > 0 ? `Recent console: ${consoleMessages.slice(-12).join(' | ')}` : '',
        ].filter(Boolean).join('\n'));
      }

      await page.screenshot({
        path: outputPath,
        fullPage: options.fullPage,
      });

      console.log(`Saved screenshot to ${outputPath}`);
      console.log(`Capture URL: ${captureUrl}`);
    } finally {
      await browser.close();
    }
  } catch (error) {
    if (String(error).includes("Executable doesn't exist")) {
      throw new Error(
        "Playwright Chromium is not installed. Run `npm run capture:scene:install` first."
      );
    }

    throw error;
  } finally {
    if (serverProcess) {
      await stopDevServer(serverProcess);
    }
  }
}

try {
  const options = parseArgs(process.argv.slice(2));
  await captureScene(options);
} catch (error) {
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
}

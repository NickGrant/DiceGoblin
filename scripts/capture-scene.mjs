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
    settleMs: DEFAULT_SETTLE_MS,
    timeoutMs: DEFAULT_WAIT_TIMEOUT_MS,
    useExistingServer: false,
    fullPage: false,
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
  --settle-ms <ms>          Extra wait after the scene signals ready (default: ${DEFAULT_SETTLE_MS})
  --timeout-ms <ms>         Overall timeout waiting for app and scene readiness
  --full-page               Capture full page instead of viewport only
`);
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
  return url.toString();
}

function sanitizeName(value) {
  return value.replace(/[^a-z0-9-_]+/gi, "-").replace(/^-+|-+$/g, "").toLowerCase() || "scene";
}

async function ensureOutputPath(outputPath) {
  await mkdir(path.dirname(outputPath), { recursive: true });
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
      "--strictPort",
    ],
    {
      cwd: process.cwd(),
      stdio: "inherit",
      shell: process.platform === "win32",
    }
  );

  return child;
}

async function captureScene(options) {
  const outputPath =
    options.output || path.join("artifacts", "screenshots", `${sanitizeName(options.scene)}.png`);
  const baseUrl = options.baseUrl || `http://${options.host}:${options.port}/`;
  const captureUrl = createCaptureUrl(options);
  const shouldStartServer = !options.useExistingServer && !options.baseUrl;
  let serverProcess = null;

  try {
    if (shouldStartServer) {
      serverProcess = startDevServer(options);
    }

    await waitForHttp(baseUrl, options.timeoutMs);
    await ensureOutputPath(outputPath);

    const browser = await chromium.launch();
    try {
      const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
      await page.goto(captureUrl, { waitUntil: "load", timeout: options.timeoutMs });
      await page.waitForFunction(
        () => {
          const debugState = window.__DG_DEBUG__;
          return (
            !!debugState &&
            debugState.ready === true &&
            typeof debugState.readyScene === "string" &&
            typeof debugState.requestedScene === "string" &&
            debugState.readyScene === debugState.requestedScene
          );
        },
        undefined,
        { timeout: options.timeoutMs }
      );

      if (options.settleMs > 0) {
        await page.waitForTimeout(options.settleMs);
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
      serverProcess.kill();
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

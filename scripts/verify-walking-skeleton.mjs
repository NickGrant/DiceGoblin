import process from 'node:process';
import { chromium } from 'playwright';

function option(name, fallback) {
  const index = process.argv.indexOf(name);
  return index >= 0 ? process.argv[index + 1] ?? fallback : fallback;
}

const frontendUrl = option('--frontend-url', 'http://127.0.0.1:4173');
const apiUrl = option('--api-url', 'http://127.0.0.1:8080');
const screenshotPath = option(
  '--screenshot',
  'artifacts/screenshots/walking-skeleton-real-backend.png',
);

const browser = await chromium.launch();
const requests = [];
const requestFailures = [];
const pageErrors = [];
let bootstrapPromise = null;
let contentPromise = null;

try {
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
  const page = await context.newPage();

  await page.route('**/runtime-config.js', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/javascript',
      body: `window.__DICE_GOBLIN_CONFIG__ = ${JSON.stringify({ apiBaseUrl: apiUrl })};`,
    }),
  );

  page.on('request', (request) => requests.push(request.url()));
  page.on('requestfailed', (request) => {
    const url = request.url();
    if (url.startsWith(frontendUrl) || url.startsWith(apiUrl)) {
      requestFailures.push(`${url} :: ${request.failure()?.errorText ?? 'unknown failure'}`);
    }
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));
  page.on('response', (response) => {
    if (response.url() === `${apiUrl}/api/v1/game/bootstrap`) {
      bootstrapPromise = response.json();
    }
    if (response.url() === `${frontendUrl}/game-content.json`) {
      contentPromise = response.json();
    }
  });

  await page.goto(`${frontendUrl}/login`, {
    waitUntil: 'domcontentloaded',
    timeout: 45_000,
  });
  await page.getByRole('button', { name: 'Create an account' }).click();
  await page.getByLabel('Display Name').fill('Closure Goblin');
  await page
    .getByLabel('Email or Username')
    .fill(`walking-skeleton-${Date.now()}@example.test`);
  await page.getByLabel('Password').fill('walking-skeleton-pass');
  const gameNavigation = page.waitForURL('**/game', { timeout: 45_000 });
  await page.getByRole('button', { name: 'Claim Warband' }).click();
  await gameNavigation;
  await page.waitForSelector('[data-game-screen="camp"]', { timeout: 45_000 });
  await page.waitForSelector('.game-host__mount canvas', { timeout: 45_000 });

  const [bootstrap, content] = await Promise.all([bootstrapPromise, contentPromise]);
  const runtime = await page.evaluate(() => {
    const host = document.querySelector('.game-host__mount');
    return {
      path: window.location.pathname,
      layout: host?.getAttribute('data-game-layout') ?? null,
      orientationGate: host?.getAttribute('data-game-orientation-gate') ?? null,
      screen: host?.getAttribute('data-game-screen') ?? null,
      canvasCount: host?.querySelectorAll('canvas').length ?? 0,
    };
  });
  const apiRequests = requests.filter((url) => url.startsWith(`${apiUrl}/api/v1/`));
  const profileRequestCount = apiRequests.filter((url) => url.includes('/profile')).length;
  const bootstrapRequestCount = apiRequests.filter(
    (url) => url === `${apiUrl}/api/v1/game/bootstrap`,
  ).length;
  const contentRequestCount = requests.filter(
    (url) => url === `${frontendUrl}/game-content.json`,
  ).length;

  const result = {
    ...runtime,
    apiRequests,
    profileRequestCount,
    bootstrapRequestCount,
    contentRequestCount,
    contentRevisionMatches:
      bootstrap?.data?.content_revision === content?.revision,
    account: bootstrap?.data?.account ?? null,
    player: bootstrap?.data?.player ?? null,
    requestFailures,
    pageErrors,
  };

  const valid =
    runtime.path === '/game' &&
    runtime.screen === 'camp' &&
    runtime.canvasCount === 1 &&
    profileRequestCount === 0 &&
    bootstrapRequestCount === 1 &&
    contentRequestCount === 1 &&
    result.contentRevisionMatches &&
    requestFailures.length === 0 &&
    pageErrors.length === 0;

  if (!valid) {
    throw new Error(`Walking-skeleton verification failed:\n${JSON.stringify(result, null, 2)}`);
  }

  await page.screenshot({ path: screenshotPath });
  process.stdout.write(`Walking skeleton verified:\n${JSON.stringify(result, null, 2)}\n`);
} finally {
  await browser.close();
}

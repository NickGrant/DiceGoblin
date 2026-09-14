import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const FRONTEND_URL = process.env.RUN_FRONTEND_URL ?? 'http://localhost:5173';
const API_URL = process.env.RUN_API_URL ?? 'http://localhost:8082';
const TIMEOUT = 120_000;

async function api(page, path, options = {}) {
  return page.evaluate(async ({ base, path, options }) => {
    const response = await fetch(`${base}${path}`, { method: options.method ?? 'GET', credentials: 'include',
      headers: { Accept: 'application/json', ...(options.csrf ? { 'X-CSRF-Token': options.csrf } : {}),
        ...(options.key ? { 'Idempotency-Key': options.key } : {}), ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
      ...(options.body ? { body: JSON.stringify(options.body) } : {}) });
    return { status: response.status, body: await response.json() };
  }, { base: API_URL, path, options });
}

async function screen(page, name) {
  await page.waitForSelector(`.game-host__mount[data-game-screen="${name}"]`, { timeout: TIMEOUT });
}

async function runMap(page) {
  await page.waitForSelector('.game-host__mount[data-run-map-ready="true"]', { state: 'attached', timeout: TIMEOUT });
}

async function click(page, x, y) {
  const canvas = page.locator('.game-host__mount canvas'); const bounds = await canvas.boundingBox();
  assert(bounds); await canvas.click({ position: { x: x * bounds.width / 1600, y: y * bounds.height / 900 }, force: true });
}

const browser = await chromium.launch();
try {
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
  const page = await context.newPage(); page.setDefaultTimeout(TIMEOUT);
  await page.route('**/runtime-config.js', (route) => route.fulfill({ status: 200, contentType: 'application/javascript',
    body: `window.__DICE_GOBLIN_CONFIG__={apiBaseUrl:'${API_URL}',enableDevPanel:true};` }));
  const requests = [];
  page.on('request', (request) => { const url = new URL(request.url()); if (url.origin === API_URL) requests.push([request.method(), url.pathname]); });
  await page.goto(`${FRONTEND_URL}/login`, { waitUntil: 'load' });
  const suffix = Date.now();
  const registered = await api(page, '/api/v1/auth/local/register', { method: 'POST', body: {
    email: `run-lifecycle-${suffix}@example.test`, password: 'Lifecycle-pass-2026!', display_name: 'Run Lifecycle Goblin',
  } });
  assert.equal(registered.status, 201, `registration failed: ${JSON.stringify(registered.body)}`);
  const authenticatedBootstrap = await api(page, '/api/v1/game/bootstrap');
  assert.equal(authenticatedBootstrap.status, 200, `authenticated bootstrap failed: ${JSON.stringify(authenticatedBootstrap.body)}`);
  await page.goto(`${FRONTEND_URL}/game`, { waitUntil: 'load' });
  await screen(page, 'camp');
  let bootstrap = (await api(page, '/api/v1/game/bootstrap')).body.data;
  const fixture = await api(page, '/api/v1/debug/fixtures/warband', { method: 'POST', csrf: bootstrap.session.csrf_token });
  assert.equal(fixture.status, 200);
  await page.reload({ waitUntil: 'load' }); await screen(page, 'camp');
  bootstrap = (await api(page, '/api/v1/game/bootstrap')).body.data;
  const beforeEnergy = bootstrap.player.energy.current; const beforeRevision = bootstrap.player.player_revision;
  const canvasBefore = await page.evaluate(() => { window.__runCanvas = document.querySelector('.game-host__mount canvas'); return !!window.__runCanvas; });
  assert.equal(canvasBefore, true);
  const startResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/runs' && response.request().method() === 'POST');
  await click(page, 800, 400); const start = await startResponse; const started = (await start.json()).data;
  assert.equal(start.status(), 200); await screen(page, 'run'); await runMap(page);
  assert.equal(started.energy.current, beforeEnergy - 10); assert.equal(started.player_revision, beforeRevision + 1);
  const runId = started.run.id;
  assert.equal(await page.evaluate(() => window.__runCanvas === document.querySelector('.game-host__mount canvas')), true);
  await click(page, 220, 800); await screen(page, 'camp');
  assert.equal(await page.evaluate(() => window.__runCanvas === document.querySelector('.game-host__mount canvas')), true);
  const postsBeforeResume = requests.filter(([method, path]) => method === 'POST' && path === '/api/v1/runs').length;
  await click(page, 800, 400); await screen(page, 'run'); await runMap(page);
  assert.equal(requests.filter(([method, path]) => method === 'POST' && path === '/api/v1/runs').length, postsBeforeResume);
  await page.reload({ waitUntil: 'load' }); await screen(page, 'run'); await runMap(page);
  const current = await api(page, '/api/v1/runs/current');
  assert.equal(current.body.data.run.id, runId); assert.equal(current.body.data.player_revision, started.player_revision);
  assert.equal(current.body.data.run.status, 'active'); assert.equal(current.body.data.run.nodes.length, 5);
  await click(page, 1380, 800);
  await page.waitForSelector('.game-host__mount[data-run-abandon-confirmation="true"]', { state: 'attached' });
  await click(page, 605, 580);
  await page.waitForSelector('.game-host__mount[data-run-abandon-confirmation="false"]', { state: 'attached' });
  assert.equal((await api(page, '/api/v1/runs/current')).body.data.run.id, runId);
  await click(page, 1380, 800);
  const abandonResponse = page.waitForResponse((response) => new URL(response.url()).pathname === `/api/v1/runs/${runId}/abandon`
    && response.request().method() === 'POST');
  await click(page, 995, 580);
  const abandon = await abandonResponse; const abandoned = (await abandon.json()).data;
  assert.equal(abandon.status(), 200); assert.equal(abandoned.run.id, runId); assert.equal(abandoned.run.status, 'abandoned');
  assert.equal(abandoned.player_revision, started.player_revision + 1); await screen(page, 'camp');
  const afterAbandon = (await api(page, '/api/v1/game/bootstrap')).body.data;
  assert.equal(afterAbandon.active_run, null); assert.equal(afterAbandon.player.energy.current, started.energy.current);
  assert.equal((await api(page, '/api/v1/runs/current')).body.data.run, null);
  console.log(JSON.stringify({ runId, beforeEnergy, afterEnergy: started.energy.current,
    startRevision: started.player_revision, abandonRevision: abandoned.player_revision,
    startPosts: postsBeforeResume, reloadScene: 'RunScene', abandonStatus: abandoned.run.status }));
} finally {
  await browser.close();
}

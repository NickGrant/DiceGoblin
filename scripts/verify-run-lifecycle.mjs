import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const FRONTEND_URL = process.env.RUN_FRONTEND_URL ?? 'http://localhost:5173';
const API_URL = process.env.RUN_API_URL ?? 'http://localhost:8082';
const TIMEOUT = 120_000;

async function api(page, path, options = {}) {
  return page.evaluate(async ({ base, path, options }) => {
    const response = await fetch(`${base}${path}`, { method: options.method ?? 'GET', credentials: 'include',
      headers: { Accept: 'application/json', 'X-Closure-Probe': '1', ...(options.csrf ? { 'X-CSRF-Token': options.csrf } : {}),
        ...(options.key ? { 'Idempotency-Key': options.key } : {}), ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
      ...(options.body ? { body: JSON.stringify(options.body) } : {}) });
    return { status: response.status, body: await response.json() };
  }, { base: API_URL, path, options });
}

function stage(name, details = {}) {
  console.log(JSON.stringify({ stage: name, ...details }));
}

async function configuredPage(browser, requests) {
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
  const page = await context.newPage();
  page.setDefaultTimeout(TIMEOUT);
  await page.route('**/runtime-config.js', (route) => route.fulfill({ status: 200, contentType: 'application/javascript',
    body: `window.__DICE_GOBLIN_CONFIG__={apiBaseUrl:'${API_URL}',enableDevPanel:true};` }));
  page.on('request', (request) => {
    const url = new URL(request.url());
    requests.push({ method: request.method(), origin: url.origin, path: url.pathname,
      probe: request.headers()['x-closure-probe'] === '1', body: request.postData(),
      idempotencyKey: request.headers()['idempotency-key'] ?? null });
  });
  return { context, page };
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
  const requests = [];
  const { context, page } = await configuredPage(browser, requests);
  stage('browser-ready');
  await page.goto(`${FRONTEND_URL}/login`, { waitUntil: 'load' });
  const suffix = Date.now();
  const registered = await api(page, '/api/v1/auth/local/register', { method: 'POST', body: {
    email: `run-lifecycle-${suffix}@example.test`, password: 'Lifecycle-pass-2026!', display_name: 'Run Lifecycle Goblin',
  } });
  assert.equal(registered.status, 201, `registration failed: ${JSON.stringify(registered.body)}`);
  stage('player-a-registered');
  const authenticatedBootstrap = await api(page, '/api/v1/game/bootstrap');
  assert.equal(authenticatedBootstrap.status, 200, `authenticated bootstrap failed: ${JSON.stringify(authenticatedBootstrap.body)}`);
  assert.equal(authenticatedBootstrap.body.data.active_run, null);
  assert.equal(authenticatedBootstrap.body.data.active_squad, null);
  const csrf = authenticatedBootstrap.body.data.session.csrf_token;
  await page.goto(`${FRONTEND_URL}/game`, { waitUntil: 'load' });
  await screen(page, 'camp');
  stage('fresh-camp');
  const fixture = await api(page, '/api/v1/debug/fixtures/warband', { method: 'POST', csrf });
  assert.equal(fixture.status, 200);
  stage('fixture-provisioned');
  await page.reload({ waitUntil: 'load' }); await screen(page, 'camp');
  stage('fixture-camp-reloaded');
  const beforeEnergy = authenticatedBootstrap.body.data.player.energy.current;
  const beforeRevision = fixture.body.data.fixture.player_revision;
  const appRequests = () => requests.filter((request) => !request.probe);
  const countApp = (method, path) => appRequests().filter((request) => request.method === method && request.path === path).length;
  const transitionBootstrapCount = countApp('GET', '/api/v1/game/bootstrap');
  const transitionContentCount = appRequests().filter((request) => request.path === '/game-content.json').length;
  const canvasBefore = await page.evaluate(() => { window.__runCanvas = document.querySelector('.game-host__mount canvas'); return !!window.__runCanvas; });
  assert.equal(canvasBefore, true);
  const startResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/runs' && response.request().method() === 'POST');
  stage('starting-run');
  await click(page, 800, 400); const start = await startResponse; const started = (await start.json()).data;
  assert.equal(start.status(), 200); await screen(page, 'run'); await runMap(page);
  assert.equal(started.energy.current, beforeEnergy - 10); assert.equal(started.player_revision, beforeRevision + 1);
  assert.equal(started.run.squad_id, fixture.body.data.fixture.active_squad_id);
  const runId = started.run.id;
  const startRequests = appRequests().filter((request) => request.method === 'POST' && request.path === '/api/v1/runs');
  assert.equal(startRequests.length, 1);
  assert.deepEqual(JSON.parse(startRequests[0].body), { region_id: 'region.the_farm' });
  assert.match(startRequests[0].idempotencyKey ?? '', /^[A-Za-z0-9._:-]{8,128}$/);
  stage('run-started', { runId, beforeEnergy, afterEnergy: started.energy.current,
    beforeRevision, startRevision: started.player_revision });
  assert.equal(await page.evaluate(() => window.__runCanvas === document.querySelector('.game-host__mount canvas')), true);
  await click(page, 220, 800); await screen(page, 'camp');
  assert.equal(await page.evaluate(() => window.__runCanvas === document.querySelector('.game-host__mount canvas')), true);
  assert.equal(countApp('POST', `/api/v1/runs/${runId}/abandon`), 0);
  const postsBeforeResume = countApp('POST', '/api/v1/runs');
  await click(page, 800, 400); await screen(page, 'run'); await runMap(page);
  assert.equal(countApp('POST', '/api/v1/runs'), postsBeforeResume);
  assert.equal(countApp('GET', '/api/v1/game/bootstrap'), transitionBootstrapCount);
  assert.equal(appRequests().filter((request) => request.path === '/game-content.json').length, transitionContentCount);
  stage('return-resume-same-runtime');
  await page.reload({ waitUntil: 'load' }); await screen(page, 'run'); await runMap(page);
  const current = await api(page, '/api/v1/runs/current');
  assert.equal(current.body.data.run.id, runId); assert.equal(current.body.data.player_revision, started.player_revision);
  assert.equal(current.body.data.run.status, 'active'); assert.equal(current.body.data.run.nodes.length, 5);
  const persistedMap = JSON.stringify({ nodes: current.body.data.run.nodes.map(({ id, node_index, node_type_id, status, position }) =>
    ({ id, node_index, node_type_id, status, position })), edges: current.body.data.run.edges });
  stage('reload-resumed-persisted-map', { runId, persistedMap });

  const requestsB = [];
  const { context: contextB, page: pageB } = await configuredPage(browser, requestsB);
  await pageB.goto(`${FRONTEND_URL}/login`, { waitUntil: 'load' });
  const registeredB = await api(pageB, '/api/v1/auth/local/register', { method: 'POST', body: {
    email: `run-lifecycle-b-${suffix}@example.test`, password: 'Lifecycle-pass-2026!', display_name: 'Other Goblin',
  } });
  assert.equal(registeredB.status, 201);
  const bootstrapB = (await api(pageB, '/api/v1/game/bootstrap')).body.data;
  const csrfB = bootstrapB.session.csrf_token;
  const fixtureB = await api(pageB, '/api/v1/debug/fixtures/warband', { method: 'POST', csrf: csrfB });
  assert.equal(fixtureB.status, 200);
  const startB = await api(pageB, '/api/v1/runs', { method: 'POST', csrf: csrfB,
    key: `closure-b-${suffix}`, body: { region_id: 'region.the_farm' } });
  assert.equal(startB.status, 200);
  assert.equal(startB.body.data.run.squad_id, fixtureB.body.data.fixture.active_squad_id);
  const runIdB = startB.body.data.run.id;
  assert.notEqual(runIdB, runId);
  const foreignAbandon = await api(page, `/api/v1/runs/${runIdB}/abandon`, { method: 'POST', csrf });
  const missingAbandon = await api(page, '/api/v1/runs/999999999999/abandon', { method: 'POST', csrf });
  assert.equal(foreignAbandon.status, 404); assert.equal(missingAbandon.status, 404);
  assert.deepEqual(foreignAbandon.body, missingAbandon.body);
  const foreignRename = await api(page, `/api/v1/units/${fixtureB.body.data.fixture.unit_ids.bruiser}/name`, {
    method: 'PATCH', csrf, body: { name: 'Intrusion' } });
  const missingRename = await api(page, '/api/v1/units/999999999999/name', {
    method: 'PATCH', csrf, body: { name: 'Intrusion' } });
  assert.equal(foreignRename.status, 404); assert.equal(missingRename.status, 404);
  assert.deepEqual(foreignRename.body, missingRename.body);
  assert.equal((await api(page, '/api/v1/runs/current')).body.data.run.id, runId);
  assert.equal((await api(pageB, '/api/v1/runs/current')).body.data.run.id, runIdB);
  stage('cross-player-nondisclosure', { runIdA: runId, runIdB });
  await click(page, 1380, 800);
  await page.waitForSelector('.game-host__mount[data-run-abandon-confirmation="true"]', { state: 'attached' });
  await click(page, 605, 580);
  await page.waitForSelector('.game-host__mount[data-run-abandon-confirmation="false"]', { state: 'attached' });
  assert.equal((await api(page, '/api/v1/runs/current')).body.data.run.id, runId);
  stage('abandon-cancel-preserved');
  await click(page, 1380, 800);
  const abandonResponse = page.waitForResponse((response) => new URL(response.url()).pathname === `/api/v1/runs/${runId}/abandon`
    && response.request().method() === 'POST');
  await click(page, 995, 580);
  const abandon = await abandonResponse; const abandoned = (await abandon.json()).data;
  assert.equal(abandon.status(), 200); assert.equal(abandoned.run.id, runId); assert.equal(abandoned.run.status, 'abandoned');
  assert.equal(abandoned.player_revision, started.player_revision + 1); await screen(page, 'camp');
  const abandonRequests = appRequests().filter((request) => request.method === 'POST' && request.path === `/api/v1/runs/${runId}/abandon`);
  assert.equal(abandonRequests.length, 1); assert.equal(abandonRequests[0].body, null); assert.equal(abandonRequests[0].idempotencyKey, null);
  const appBootstrapBeforeProbe = countApp('GET', '/api/v1/game/bootstrap');
  const afterAbandon = (await api(page, '/api/v1/game/bootstrap')).body.data;
  assert.equal(afterAbandon.active_run, null); assert.equal(afterAbandon.player.energy.current, started.energy.current);
  assert.equal((await api(page, '/api/v1/runs/current')).body.data.run, null);
  assert.equal(countApp('GET', '/api/v1/game/bootstrap'), appBootstrapBeforeProbe);
  const forbidden = appRequests().filter((request) => request.path === '/api/v1/profile' || request.path.startsWith('/api/v1/teams')
    || /\/api\/v1\/runs\/[^/]+\/(exit|nodes\/[^/]+\/(resolve|chaos|dialogue|rest)|units\/)/.test(request.path));
  assert.deepEqual(forbidden, []);
  const apiPaths = [...new Set(appRequests().filter((request) => request.origin === API_URL)
    .map((request) => `${request.method} ${request.path}`))].sort();
  stage('network-assertions', { apiPaths, bootstrapRequests: countApp('GET', '/api/v1/game/bootstrap'),
    contentRequests: appRequests().filter((request) => request.path === '/game-content.json').length });
  console.log(JSON.stringify({ result: 'passed', runId, secondPlayerRunId: runIdB, beforeEnergy, afterEnergy: started.energy.current,
    startRevision: started.player_revision, abandonRevision: abandoned.player_revision,
    startPosts: postsBeforeResume, reloadScene: 'RunScene', abandonStatus: abandoned.run.status,
    currentAfterAbandon: null }));
  await contextB.close();
  await context.close();
} finally {
  await Promise.race([browser.close(), new Promise((resolve) => setTimeout(resolve, 5_000))]);
}

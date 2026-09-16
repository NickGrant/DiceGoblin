import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const FRONTEND_URL = process.env.COMBAT_FRONTEND_URL ?? 'http://localhost:5173';
const API_URL = process.env.COMBAT_API_URL ?? 'http://localhost:8082';
const TIMEOUT = 120_000;

async function api(page, path, options = {}) {
  return page.evaluate(async ({ base, path, options }) => {
    const response = await fetch(`${base}${path}`, { method: options.method ?? 'GET', credentials: 'include',
      headers: { Accept: 'application/json', 'X-Combat-Probe': '1', ...(options.csrf ? { 'X-CSRF-Token': options.csrf } : {}),
        ...(options.key ? { 'Idempotency-Key': options.key } : {}), ...(options.body ? { 'Content-Type': 'application/json' } : {}) },
      ...(options.body ? { body: JSON.stringify(options.body) } : {}) });
    return { status: response.status, body: await response.json() };
  }, { base: API_URL, path, options });
}

async function click(page, x, y) {
  const canvas = page.locator('.game-host__mount canvas'); const bounds = await canvas.boundingBox();
  assert(bounds); await canvas.click({ position: { x: x * bounds.width / 1600, y: y * bounds.height / 900 }, force: true });
}

const browser = await chromium.launch();
try {
  const requests = [];
  const context = await browser.newContext({ viewport: { width: 1600, height: 900 } });
  const page = await context.newPage(); page.setDefaultTimeout(TIMEOUT);
  await page.route('**/runtime-config.js', (route) => route.fulfill({ status: 200, contentType: 'application/javascript',
    body: `window.__DICE_GOBLIN_CONFIG__={apiBaseUrl:'${API_URL}',enableDevPanel:true};` }));
  page.on('request', (request) => { const url = new URL(request.url()); requests.push({ method: request.method(), path: url.pathname,
    probe: request.headers()['x-combat-probe'] === '1', key: request.headers()['idempotency-key'] ?? null }); });
  const appRequests = () => requests.filter((request) => !request.probe);
  const count = (method, path) => appRequests().filter((request) => request.method === method && request.path === path).length;

  await page.goto(`${FRONTEND_URL}/login`, { waitUntil: 'load' });
  const suffix = Date.now();
  const registration = await api(page, '/api/v1/auth/local/register', { method: 'POST', body: {
    email: `combat-playback-${suffix}@example.test`, password: 'Combat-pass-2026!', display_name: 'Combat Playback Goblin',
  } });
  assert.equal(registration.status, 201, JSON.stringify(registration.body));
  const bootstrap = (await api(page, '/api/v1/game/bootstrap')).body.data;
  const fixture = await api(page, '/api/v1/debug/fixtures/warband', { method: 'POST', csrf: bootstrap.session.csrf_token });
  assert.equal(fixture.status, 200, JSON.stringify(fixture.body));

  await page.goto(`${FRONTEND_URL}/game`, { waitUntil: 'load' });
  await page.waitForSelector('.game-host__mount[data-game-screen="camp"]');
  console.log(JSON.stringify({ stage: 'camp-ready' }));
  const bootstrapBefore = count('GET', '/api/v1/game/bootstrap');
  const contentBefore = count('GET', '/game-content.json');
  const canvasBefore = await page.evaluate(() => { window.__combatCanvas = document.querySelector('.game-host__mount canvas'); return !!window.__combatCanvas; });
  assert.equal(canvasBefore, true);
  const startResponse = page.waitForResponse((response) => new URL(response.url()).pathname === '/api/v1/runs' && response.request().method() === 'POST');
  await click(page, 800, 400); const started = (await (await startResponse).json()).data;
  console.log(JSON.stringify({ stage: 'run-started', runId: started.run.id }));
  const startedCurrent = await api(page, '/api/v1/runs/current');
  assert.equal(startedCurrent.status, 200, `current run failed after start: ${JSON.stringify(startedCurrent.body)}`);
  assert.equal(startedCurrent.body.data.run.id, started.run.id);
  await page.waitForSelector('.game-host__mount[data-game-screen="run"]');
  await page.waitForSelector('.game-host__mount[data-run-map-ready="true"]', { state: 'attached' });
  console.log(JSON.stringify({ stage: 'run-map-ready' }));
  await click(page, 225, 418);
  const resolvePath = `/api/v1/runs/${started.run.id}/nodes/`;
  const resolveResponse = page.waitForResponse((response) => {
    const request = response.request(); const path = new URL(response.url()).pathname;
    return request.method() === 'POST' && path.startsWith(resolvePath) && path.endsWith('/resolve');
  });
  await click(page, 800, 799);
  const response = await resolveResponse; assert.equal(response.status(), 200);
  const resolved = (await response.json()).data; const battleId = resolved.battle.id;
  console.log(JSON.stringify({ stage: 'combat-resolved', battleId, outcome: resolved.battle.outcome }));
  const exactResolvePath = `/api/v1/runs/${started.run.id}/nodes/${resolved.node.id}/resolve`;
  await page.waitForSelector('.game-host__mount[data-game-screen="battle"]');
  await page.waitForSelector('.game-host__mount[data-battle-playback="complete"]', { timeout: TIMEOUT });
  console.log(JSON.stringify({ stage: 'playback-complete' }));
  assert.equal(count('POST', exactResolvePath), 1);
  assert.equal(count('GET', `/api/v1/battles/${battleId}/playback`), 1);
  assert.match(appRequests().find((request) => request.method === 'POST' && request.path === exactResolvePath)?.key ?? '', /^combat-node:/);
  const resolveIndex = appRequests().findIndex((request) => request.method === 'POST' && request.path === exactResolvePath);
  assert.deepEqual(appRequests().slice(resolveIndex + 1).filter((request) => request.path.startsWith('/api/'))
    .map((request) => `${request.method} ${request.path}`), [`GET /api/v1/battles/${battleId}/playback`]);
  assert.equal(await page.evaluate(() => window.__combatCanvas === document.querySelector('.game-host__mount canvas')), true);
  assert.equal(count('GET', '/api/v1/game/bootstrap'), bootstrapBefore);
  assert.equal(count('GET', '/game-content.json'), contentBefore);
  const finalized = await api(page, '/api/v1/runs/current');
  if (finalized.body.data.run) {
    const node = finalized.body.data.run.nodes.find((candidate) => candidate.id === resolved.node.id);
    assert.equal(node.status, 'completed'); assert.equal(node.battle_id, battleId);
  } else assert.equal(resolved.run.status, 'failed');

  await page.reload({ waitUntil: 'load' });
  await page.waitForSelector('.game-host__mount[data-game-screen="battle"]');
  await page.waitForSelector('.game-host__mount[data-battle-playback="complete"]', { timeout: TIMEOUT });
  assert.equal(count('POST', exactResolvePath), 1);
  assert.equal(count('GET', `/api/v1/battles/${battleId}/playback`), 2);
  const playbackBeforeContinue = count('GET', `/api/v1/battles/${battleId}/playback`);
  const currentRunBeforeContinue = count('GET', '/api/v1/runs/current');
  const bootstrapBeforeContinue = count('GET', '/api/v1/game/bootstrap');
  const warbandBeforeContinue = ['/api/v1/units', '/api/v1/dice', '/api/v1/squads']
    .map((path) => count('GET', path));
  await page.evaluate(() => { window.__combatCanvas = document.querySelector('.game-host__mount canvas'); });
  const continueResponse = page.waitForResponse((candidate) => {
    const request = candidate.request();
    return request.method() === 'GET' && new URL(candidate.url()).pathname === '/api/v1/runs/current'
      && request.headers()['x-combat-probe'] !== '1';
  });
  await click(page, 800, 845);
  const reconciledResponse = await continueResponse; assert.equal(reconciledResponse.status(), 200);
  const reconciled = (await reconciledResponse.json()).data;
  await page.waitForSelector('.game-host__mount[data-game-screen="run"]');
  await page.waitForSelector('.game-host__mount[data-run-map-ready="true"]', { state: 'attached' });
  assert(reconciled.run, 'victory Continue must retain an active current run');
  const reconciledNode = reconciled.run.nodes.find((candidate) => candidate.id === resolved.node.id);
  assert.equal(reconciledNode.status, 'completed'); assert.equal(reconciledNode.battle_id, battleId);
  const availableLoot = reconciled.run.nodes.find((candidate) => candidate.node_type_id === 'run_node_type.loot');
  assert.equal(availableLoot?.status, 'available');
  for (const node of reconciled.run.nodes.filter((candidate) => ['run_node_type.rest', 'run_node_type.boss', 'run_node_type.exit'].includes(candidate.node_type_id)))
    assert.equal(node.status, 'locked');
  for (const [unitId, terminalHp] of Object.entries(resolved.terminal_player_hp))
    assert.equal(reconciled.run.units.find((candidate) => candidate.unit_id === unitId)?.current_hp, terminalHp);
  assert.equal(count('GET', '/api/v1/runs/current'), currentRunBeforeContinue + 1);
  assert.equal(count('GET', `/api/v1/battles/${battleId}/playback`), playbackBeforeContinue);
  assert.equal(count('POST', exactResolvePath), 1); assert.equal(count('GET', '/api/v1/game/bootstrap'), bootstrapBeforeContinue);
  assert.deepEqual(['/api/v1/units', '/api/v1/dice', '/api/v1/squads'].map((path) => count('GET', path)), warbandBeforeContinue);
  assert.equal(await page.evaluate(() => sessionStorage.getItem('dice-goblins:battle-presentation:v1')), null);
  assert.equal(await page.evaluate(() => window.__combatCanvas === document.querySelector('.game-host__mount canvas')), true);
  console.log(JSON.stringify({ stage: 'continue-reconciled', currentRunGets: 1, scene: 'RunScene' }));

  await page.reload({ waitUntil: 'load' });
  await page.waitForSelector('.game-host__mount[data-game-screen="run"]');
  await page.waitForSelector('.game-host__mount[data-run-map-ready="true"]', { state: 'attached' });
  assert.equal(count('POST', exactResolvePath), 1);
  assert.equal(count('GET', `/api/v1/battles/${battleId}/playback`), playbackBeforeContinue);
  console.log(JSON.stringify({ result: 'passed', runId: started.run.id, nodeId: resolved.node.id, battleId,
    outcome: resolved.battle.outcome, resolvePosts: 1, playbackGets: 2, continueCurrentRunGets: 1,
    markerCleared: true, continueScene: 'RunScene', reloadScene: 'RunScene' }));
  await context.close();
} finally {
  await browser.close();
}

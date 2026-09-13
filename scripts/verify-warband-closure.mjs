import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

// Run against a fixture-enabled backend connected to an isolated vNext database.
// The verifier creates two accounts and mutates only records owned by those accounts.
const FRONTEND_URL = process.env.WARBAND_FRONTEND_URL ?? 'http://localhost:5173';
const API_URL = process.env.WARBAND_API_URL ?? 'http://localhost:8082';
const OUTPUT_PATH = process.env.WARBAND_REPORT_PATH
  ?? path.resolve('artifacts/verification/warband-closure-browser.json');
const VIEWPORT = { width: 1600, height: 900 };
const BROWSER_TIMEOUT_MS = 180_000;

function apiPath(url) {
  const parsed = new URL(url);
  return parsed.origin === API_URL ? parsed.pathname : null;
}

function countRequests(requests, pathname, method = 'GET') {
  return requests.filter((entry) => entry.path === pathname && entry.method === method).length;
}

function captureRequests(page, requests) {
  page.on('request', (request) => {
    const requestPath = apiPath(request.url());
    const parsed = new URL(request.url());
    if (requestPath || (parsed.origin === FRONTEND_URL && requestPath === null)) {
      requests.push({
        method: request.method(),
        url: request.url(),
        path: requestPath ?? parsed.pathname,
        headers: request.headers(),
        resourceType: request.resourceType(),
      });
    }
  });
}

async function configureApi(page) {
  await page.route('**/runtime-config.js', (route) => route.fulfill({
    status: 200,
    contentType: 'application/javascript',
    body: `window.__DICE_GOBLIN_CONFIG__ = { apiBaseUrl: '${API_URL}', enableDevPanel: true };`,
  }));
}

async function browserApi(page, pathname, options = {}) {
  return page.evaluate(async ({ apiUrl, requestPath, requestOptions }) => {
    const response = await fetch(`${apiUrl}${requestPath}`, {
      method: requestOptions.method ?? 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        ...(requestOptions.csrf ? { 'X-CSRF-Token': requestOptions.csrf } : {}),
        ...(requestOptions.idempotencyKey ? { 'Idempotency-Key': requestOptions.idempotencyKey } : {}),
        ...(requestOptions.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
      },
      ...(requestOptions.body !== undefined ? { body: JSON.stringify(requestOptions.body) } : {}),
    });
    return { status: response.status, body: await response.json() };
  }, { apiUrl: API_URL, requestPath: pathname, requestOptions: options });
}

async function register(page, suffix, displayName) {
  await page.goto(`${FRONTEND_URL}/login`, { waitUntil: 'load' });
  const registerTab = page.getByRole('tab', { name: 'Register', exact: true });
  try {
    await registerTab.waitFor({ state: 'visible' });
  } catch {
    throw new Error(`Registration tab was not rendered at ${page.url()}: ${(await page.locator('body').innerText()).slice(0, 1000)}`);
  }
  await registerTab.click();
  await page.getByLabel('Display Name').fill(displayName);
  await page.getByLabel('Email or Username').fill(`warband-closure-${suffix}@example.test`);
  await page.getByLabel('Password').fill('Closure-pass-2026!');
  const bootstrapResponse = page.waitForResponse((response) =>
    apiPath(response.url()) === '/api/v1/game/bootstrap' && response.request().method() === 'GET');
  await page.getByRole('button', { name: 'Claim Warband', exact: true }).click();
  const response = await bootstrapResponse;
  assert.equal(response.status(), 200, 'registration must reach the real game bootstrap');
  await page.waitForURL('**/game');
  await waitForScreen(page, 'camp');
  return (await response.json()).data;
}

async function waitForScreen(page, screen) {
  await page.waitForSelector(`.game-host__mount[data-game-screen="${screen}"]`, { timeout: BROWSER_TIMEOUT_MS });
  await page.waitForSelector('.game-host__mount canvas', { timeout: BROWSER_TIMEOUT_MS });
  if (screen === 'warband') {
    await page.waitForSelector('.game-host__mount[data-warband-ready="true"]', { timeout: BROWSER_TIMEOUT_MS });
  } else if (screen === 'squad-editor') {
    await page.waitForSelector('.game-host__mount[data-squad-editor-ready="true"]', { timeout: BROWSER_TIMEOUT_MS });
  } else if (screen === 'unit-configuration') {
    await page.waitForSelector('.game-host__mount[data-unit-configuration-ready="true"]', { timeout: BROWSER_TIMEOUT_MS });
  }
}

async function clickLogical(page, x, y) {
  const canvas = page.locator('.game-host__mount canvas');
  const bounds = await canvas.boundingBox();
  assert(bounds, 'Phaser canvas must have a rendered bounding box');
  await canvas.click({
    position: {
      x: (x / VIEWPORT.width) * bounds.width,
      y: (y / VIEWPORT.height) * bounds.height,
    },
    force: true,
  });
}

async function openWarband(page) {
  await clickLogical(page, 1212, 219);
  await waitForScreen(page, 'warband');
}

async function returnToCamp(page) {
  await clickLogical(page, 122, 79);
  await waitForScreen(page, 'camp');
}

async function selectSquadRow(page, index, count = 3) {
  const contentY = 344;
  const contentHeight = 512;
  const gap = 11;
  const rowHeight = (contentHeight - 36 - gap * Math.max(0, count - 1)) / count;
  await clickLogical(page, 220, contentY + 18 + index * (rowHeight + gap) + rowHeight / 2);
}

async function waitForMutation(page, predicate, action) {
  const pending = page.waitForResponse((response) => predicate(response), { timeout: BROWSER_TIMEOUT_MS });
  await action();
  const response = await pending;
  const body = await response.json();
  return { response, body };
}

async function runPrimaryFlow(browser) {
  const context = await browser.newContext({ viewport: VIEWPORT });
  const page = await context.newPage();
  page.setDefaultTimeout(BROWSER_TIMEOUT_MS);
  const requests = [];
  const pageErrors = [];
  captureRequests(page, requests);
  page.on('pageerror', (error) => pageErrors.push(error.message));
  await configureApi(page);

  const suffix = `${Date.now()}-a`;
  const initial = await register(page, suffix, 'Closure Commander');
  console.log('Primary account registered and Camp bootstrap loaded.');
  assert.equal(initial.player.player_revision, 1);
  assert.equal(initial.active_squad, null);
  assert.equal(countRequests(requests, '/api/v1/units'), 0, 'Camp startup must not eagerly load units');
  assert.equal(countRequests(requests, '/api/v1/dice'), 0, 'Camp startup must not eagerly load dice');
  assert.equal(countRequests(requests, '/api/v1/squads'), 0, 'Camp startup must not eagerly load squads');

  const emptyCollections = {};
  for (const domain of ['units', 'dice', 'squads']) {
    const result = await browserApi(page, `/api/v1/${domain}`);
    assert.equal(result.status, 200);
    assert.deepEqual(result.body.data[domain], []);
    emptyCollections[domain] = result.body.data[domain].length;
  }

  const emptySquadPayload = { name: 'First Empty Squad', formation: Array(9).fill(null) };
  const firstCreate = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: 'closure:first-squad:0001', body: emptySquadPayload,
  });
  assert.equal(firstCreate.status, 200);
  assert.equal(firstCreate.body.data.squad.is_active, true);
  assert.equal(firstCreate.body.data.player_revision, 2);
  const firstReplay = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: 'closure:first-squad:0001', body: emptySquadPayload,
  });
  assert.equal(firstReplay.status, 200);
  assert.deepEqual(firstReplay.body, firstCreate.body);
  const firstConflict = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: 'closure:first-squad:0001', body: { ...emptySquadPayload, name: 'Conflicting Squad' },
  });
  assert.equal(firstConflict.status, 409);

  const fixtureOne = await browserApi(page, '/api/v1/debug/fixtures/warband', {
    method: 'POST', csrf: initial.session.csrf_token,
  });
  assert.equal(fixtureOne.status, 200);
  assert.equal(fixtureOne.body.data.fixture.player_revision, 3);
  const fixtureTwo = await browserApi(page, '/api/v1/debug/fixtures/warband', {
    method: 'POST', csrf: initial.session.csrf_token,
  });
  assert.equal(fixtureTwo.status, 200);
  assert.equal(fixtureTwo.body.data.fixture.player_revision, 4);
  assert.notDeepEqual(fixtureTwo.body.data.fixture.unit_ids, fixtureOne.body.data.fixture.unit_ids);
  const fixture = fixtureTwo.body.data.fixture;
  console.log('Controlled Warband fixture replaced twice transactionally.');

  requests.length = 0;
  await page.reload({ waitUntil: 'load' });
  await waitForScreen(page, 'camp');
  assert.equal(countRequests(requests, '/api/v1/game/bootstrap'), 1);
  assert.equal(countRequests(requests, '/api/v1/units'), 0);
  assert.equal(countRequests(requests, '/api/v1/dice'), 0);
  assert.equal(countRequests(requests, '/api/v1/squads'), 0);
  await page.evaluate(() => { window.__closureCanvas = document.querySelector('.game-host__mount canvas'); });

  await openWarband(page);
  assert.equal(countRequests(requests, '/api/v1/units'), 1);
  assert.equal(countRequests(requests, '/api/v1/dice'), 1);
  assert.equal(countRequests(requests, '/api/v1/squads'), 1);
  const freshCounts = {
    bootstrap: countRequests(requests, '/api/v1/game/bootstrap'),
    content: requests.filter((request) => request.path === '/game-content.json').length,
    units: countRequests(requests, '/api/v1/units'),
    dice: countRequests(requests, '/api/v1/dice'),
    squads: countRequests(requests, '/api/v1/squads'),
  };
  await returnToCamp(page);
  await openWarband(page);
  assert.deepEqual({
    bootstrap: countRequests(requests, '/api/v1/game/bootstrap'),
    content: requests.filter((request) => request.path === '/game-content.json').length,
    units: countRequests(requests, '/api/v1/units'),
    dice: countRequests(requests, '/api/v1/dice'),
    squads: countRequests(requests, '/api/v1/squads'),
  }, freshCounts, 'fresh-cache navigation must suppress bootstrap/content/collection requests');
  console.log('Camp/Warband lazy loading and fresh-cache suppression verified.');

  await clickLogical(page, 1154, 378);
  await waitForScreen(page, 'squad-editor');
  await page.locator('input[data-squad-name-input="true"]').fill('Closure Squad');
  for (const [rosterY, cell] of [[328, [168, 355]], [412, [392, 497]], [496, [616, 639]]]) {
    await clickLogical(page, 900, rosterY);
    await clickLogical(page, cell[0], cell[1]);
  }
  const createMutation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === '/api/v1/squads' && response.request().method() === 'POST',
    () => clickLogical(page, 339, 822),
  );
  assert.equal(createMutation.response.status(), 200);
  await waitForScreen(page, 'warband');
  const created = createMutation.body.data;
  assert.equal(created.player_revision, 5);
  assert.equal(created.squad.name, 'Closure Squad');
  assert.equal(created.squad.formation.length, 9);
  const createHeaders = createMutation.response.request().headers();
  assert.equal(createHeaders['x-csrf-token'], initial.session.csrf_token);
  assert.match(createHeaders['idempotency-key'], /^squad:create:/);
  const createdPayload = createMutation.response.request().postDataJSON();
  const replay = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: createHeaders['idempotency-key'], body: createdPayload,
  });
  assert.equal(replay.status, 200);
  assert.deepEqual(replay.body, createMutation.body);
  const conflict = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: createHeaders['idempotency-key'], body: { ...createdPayload, name: 'Closure Conflict' },
  });
  assert.equal(conflict.status, 409);

  await selectSquadRow(page, 2);
  await clickLogical(page, 1266, 378);
  await waitForScreen(page, 'squad-editor');
  await page.locator('input[data-squad-name-input="true"]').fill('Closure Vanguard');
  await clickLogical(page, 900, 580);
  await clickLogical(page, 616, 355);
  const updateMutation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === `/api/v1/squads/${created.squad.id}` && response.request().method() === 'PUT',
    () => clickLogical(page, 339, 822),
  );
  assert.equal(updateMutation.response.status(), 200);
  assert.equal(updateMutation.body.data.player_revision, 6);
  assert.equal(updateMutation.body.data.squad.name, 'Closure Vanguard');
  assert.equal(updateMutation.body.data.squad.formation.length, 9);
  await waitForScreen(page, 'warband');

  await selectSquadRow(page, 2);
  const activateMutation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === `/api/v1/squads/${created.squad.id}/activate`,
    () => clickLogical(page, 1378, 378),
  );
  assert.equal(activateMutation.response.status(), 200);
  assert.equal(activateMutation.body.data.player_revision, 7);
  await waitForScreen(page, 'warband');
  const activateNoop = await browserApi(page, `/api/v1/squads/${created.squad.id}/activate`, {
    method: 'POST', csrf: initial.session.csrf_token,
  });
  assert.equal(activateNoop.status, 200);
  assert.equal(activateNoop.body.data.player_revision, 7);

  const forbiddenDeleteRequest = page.waitForResponse((response) =>
    apiPath(response.url()) === `/api/v1/squads/${created.squad.id}` && response.request().method() === 'DELETE');
  await clickLogical(page, 1490, 378);
  await waitForScreen(page, 'squad-editor');
  await clickLogical(page, 948, 517);
  const forbiddenDelete = await forbiddenDeleteRequest;
  assert.equal(forbiddenDelete.status(), 409);
  await clickLogical(page, 137, 822);
  await waitForScreen(page, 'warband');

  await selectSquadRow(page, 0);
  const replacementActivation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === `/api/v1/squads/${fixture.squad_ids.raiders}/activate`,
    () => clickLogical(page, 1378, 378),
  );
  assert.equal(replacementActivation.response.status(), 200);
  assert.equal(replacementActivation.body.data.player_revision, 8);
  await waitForScreen(page, 'warband');
  await selectSquadRow(page, 2);
  const allowedDeleteRequest = page.waitForResponse((response) =>
    apiPath(response.url()) === `/api/v1/squads/${created.squad.id}` && response.request().method() === 'DELETE');
  await clickLogical(page, 1490, 378);
  await waitForScreen(page, 'squad-editor');
  await clickLogical(page, 948, 517);
  const allowedDelete = await allowedDeleteRequest;
  assert.equal(allowedDelete.status(), 200);
  const allowedDeleteBody = await allowedDelete.json();
  assert.equal(allowedDeleteBody.data.player_revision, 9);
  await waitForScreen(page, 'warband');
  console.log('Squad create/edit/activate/delete/idempotency flow verified.');

  await clickLogical(page, 290, 297);
  const detailResponsePromise = page.waitForResponse((response) =>
    apiPath(response.url()) === `/api/v1/units/${fixture.unit_ids.bruiser}` && response.request().method() === 'GET');
  await clickLogical(page, 300, 398);
  const detailResponse = await detailResponsePromise;
  assert.equal(detailResponse.status(), 200);
  await waitForScreen(page, 'unit-configuration');
  assert.equal(countRequests(requests, `/api/v1/units/${fixture.unit_ids.bruiser}`), 1);

  const nameInput = page.locator('input[data-unit-name-input="true"]');
  await nameInput.fill('Closure Grub');
  const renameMutation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === `/api/v1/units/${fixture.unit_ids.bruiser}/name`,
    () => clickLogical(page, 339, 827),
  );
  assert.equal(renameMutation.response.status(), 200);
  assert.equal(renameMutation.body.data.player_revision, 10);
  assert.equal(renameMutation.body.data.unit.display_name, 'Closure Grub');
  assert.equal(renameMutation.response.request().headers()['x-csrf-token'], initial.session.csrf_token);

  await clickLogical(page, 1430, 285);
  await clickLogical(page, 578, 385);
  await clickLogical(page, 1475, 335);
  await clickLogical(page, 680, 220);
  await clickLogical(page, 578, 545);
  await clickLogical(page, 1475, 438);
  const loadoutMutation = await waitForMutation(
    page,
    (response) => apiPath(response.url()) === `/api/v1/units/${fixture.unit_ids.bruiser}/loadout`,
    () => clickLogical(page, 556, 827),
  );
  assert.equal(loadoutMutation.response.status(), 200);
  assert.equal(loadoutMutation.body.data.player_revision, 11);
  assert.deepEqual(
    loadoutMutation.body.data.unit.ability_loadout.map((entry) => entry.ability_id),
    ['ability.heavy_strike', 'ability.basic_attack_melee'],
  );
  assert.deepEqual(
    loadoutMutation.body.data.unit.dice_bindings.map((entry) => entry.dice_instance_id),
    [fixture.dice_ids.bruiser_basic, fixture.dice_ids.bruiser_heavy],
  );
  assert.equal(loadoutMutation.response.request().headers()['x-csrf-token'], initial.session.csrf_token);
  console.log('Unit rename, ordering, same-unit die movement, and atomic save verified.');

  await clickLogical(page, 122, 74);
  await waitForScreen(page, 'warband');
  await clickLogical(page, 300, 398);
  await waitForScreen(page, 'unit-configuration');
  assert.equal(await page.locator('input[data-unit-name-input="true"]').inputValue(), 'Closure Grub');
  assert.equal(countRequests(requests, `/api/v1/units/${fixture.unit_ids.bruiser}`), 1,
    'fresh per-unit detail cache must suppress a duplicate detail request');
  await clickLogical(page, 122, 74);
  await waitForScreen(page, 'warband');
  assert.equal(await page.evaluate(() => window.__closureCanvas === document.querySelector('.game-host__mount canvas')), true,
    'Camp, Warband, squad editor, and unit configuration must share one Phaser canvas');
  assert.equal(new URL(page.url()).pathname, '/game');

  const countsBeforeReload = {
    bootstrap: countRequests(requests, '/api/v1/game/bootstrap'),
    content: requests.filter((request) => request.path === '/game-content.json').length,
    units: countRequests(requests, '/api/v1/units'),
    dice: countRequests(requests, '/api/v1/dice'),
    squads: countRequests(requests, '/api/v1/squads'),
    detail: countRequests(requests, `/api/v1/units/${fixture.unit_ids.bruiser}`),
  };

  const persistedBootstrapResponse = page.waitForResponse((response) =>
    apiPath(response.url()) === '/api/v1/game/bootstrap' && response.request().method() === 'GET');
  await page.reload({ waitUntil: 'load' });
  const persistedBootstrap = await (await persistedBootstrapResponse).json();
  await waitForScreen(page, 'camp');
  assert.equal(persistedBootstrap.data.player.player_revision, 11);
  await openWarband(page);
  await clickLogical(page, 290, 297);
  const persistedDetailResponse = page.waitForResponse((response) =>
    apiPath(response.url()) === `/api/v1/units/${fixture.unit_ids.bruiser}` && response.request().method() === 'GET');
  await clickLogical(page, 300, 398);
  const persistedDetail = await (await persistedDetailResponse).json();
  await waitForScreen(page, 'unit-configuration');
  assert.equal(await page.locator('input[data-unit-name-input="true"]').inputValue(), 'Closure Grub');
  assert.equal(persistedDetail.data.unit.display_name, 'Closure Grub');
  assert.deepEqual(
    persistedDetail.data.unit.ability_loadout.map((entry) => entry.ability_id),
    ['ability.heavy_strike', 'ability.basic_attack_melee'],
  );
  const persistedBindings = Object.fromEntries(persistedDetail.data.unit.dice_bindings.map((entry) => [
    `${entry.ability_id}:${entry.slot_index}`, entry.dice_instance_id,
  ]));
  assert.equal(persistedBindings['ability.heavy_strike:0'], fixture.dice_ids.bruiser_basic);
  assert.equal(persistedBindings['ability.basic_attack_melee:0'], fixture.dice_ids.bruiser_heavy);
  console.log('Deliberate reload re-read persisted unit configuration.');

  const forbiddenPaths = requests.filter((request) => request.path === '/api/v1/profile' || request.path.startsWith('/teams'));
  assert.deepEqual(forbiddenPaths, []);
  for (const request of requests.filter((entry) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(entry.method)
    && entry.path.startsWith('/api/v1/'))) {
    assert.ok(request.headers['x-csrf-token'], `mutation ${request.method} ${request.path} must carry CSRF`);
  }
  assert.deepEqual(pageErrors, []);

  return {
    context,
    page,
    requests,
    fixture,
    csrfToken: initial.session.csrf_token,
    persistedDetail: persistedDetail.data.unit,
    evidence: {
      initialRevision: initial.player.player_revision,
      fixtureRevisions: [fixtureOne.body.data.fixture.player_revision, fixtureTwo.body.data.fixture.player_revision],
      mutationRevisions: {
        create: created.player_revision,
        update: updateMutation.body.data.player_revision,
        activate: activateMutation.body.data.player_revision,
        activeNoop: activateNoop.body.data.player_revision,
        replacementActivation: replacementActivation.body.data.player_revision,
        delete: allowedDeleteBody.data.player_revision,
        rename: renameMutation.body.data.player_revision,
        loadout: loadoutMutation.body.data.player_revision,
      },
      emptyCollections,
      freshCounts,
      countsBeforeReload,
      countsAfterReload: {
        bootstrap: countRequests(requests, '/api/v1/game/bootstrap'),
        content: requests.filter((request) => request.path === '/game-content.json').length,
        units: countRequests(requests, '/api/v1/units'),
        dice: countRequests(requests, '/api/v1/dice'),
        squads: countRequests(requests, '/api/v1/squads'),
        detail: countRequests(requests, `/api/v1/units/${fixture.unit_ids.bruiser}`),
      },
      createHeaders: {
        csrf: Boolean(createHeaders['x-csrf-token']),
        idempotencyKey: createHeaders['idempotency-key'],
      },
      finalRevision: persistedBootstrap.data.player.player_revision,
      finalSquadIds: [fixture.squad_ids.raiders, fixture.squad_ids.brawlers],
    },
  };
}

async function runCrossPlayerFlow(browser, primary) {
  const context = await browser.newContext({ viewport: VIEWPORT });
  const page = await context.newPage();
  page.setDefaultTimeout(BROWSER_TIMEOUT_MS);
  const requests = [];
  captureRequests(page, requests);
  await configureApi(page);
  const initial = await register(page, `${Date.now()}-b`, 'Adversarial Commander');
  const fixtureResponse = await browserApi(page, '/api/v1/debug/fixtures/warband', {
    method: 'POST', csrf: initial.session.csrf_token,
  });
  assert.equal(fixtureResponse.status, 200);
  const fixture = fixtureResponse.body.data.fixture;
  console.log('Second authenticated player fixture loaded for adversarial HTTP checks.');

  const foreignDetail = await browserApi(page, `/api/v1/units/${primary.fixture.unit_ids.bruiser}`);
  const missingDetail = await browserApi(page, '/api/v1/units/999999999999');
  assert.equal(foreignDetail.status, 404);
  assert.equal(missingDetail.status, 404);
  assert.deepEqual(foreignDetail.body, missingDetail.body, 'foreign and missing unit detail must be indistinguishable');

  const foreignFormation = await browserApi(page, '/api/v1/squads', {
    method: 'POST', csrf: initial.session.csrf_token,
    idempotencyKey: 'closure:cross-owner:0001',
    body: { name: 'Foreign Unit Attempt', formation: [primary.fixture.unit_ids.bruiser, ...Array(8).fill(null)] },
  });
  assert.equal(foreignFormation.status, 422);

  const foreignSquadPayload = { name: 'Foreign Squad Attempt', formation: Array(9).fill(null) };
  const foreignSquadResults = {};
  for (const [name, pathname, method, body] of [
    ['update', `/api/v1/squads/${primary.fixture.squad_ids.raiders}`, 'PUT', foreignSquadPayload],
    ['activate', `/api/v1/squads/${primary.fixture.squad_ids.raiders}/activate`, 'POST', undefined],
    ['delete', `/api/v1/squads/${primary.fixture.squad_ids.raiders}`, 'DELETE', undefined],
  ]) {
    const result = await browserApi(page, pathname, { method, csrf: initial.session.csrf_token, body });
    assert.equal(result.status, 404);
    foreignSquadResults[name] = result.status;
  }

  const ownDetail = await browserApi(page, `/api/v1/units/${fixture.unit_ids.bruiser}`);
  assert.equal(ownDetail.status, 200);
  const unit = ownDetail.body.data.unit;
  const bindings = new Map(unit.dice_bindings.map((binding) => [
    `${binding.ability_id}:${binding.slot_index}`, binding.dice_instance_id,
  ]));
  const crossOwnerLoadout = {
    abilities: unit.ability_loadout.map((entry, abilityIndex) => ({
      ability_id: entry.ability_id,
      dice_instance_ids: Array.from({ length: entry.ability_id === 'ability.sleep_dart' ? 2 : 1 }, (_, slotIndex) =>
        abilityIndex === 0 && slotIndex === 0
          ? primary.fixture.dice_ids.bruiser_basic
          : bindings.get(`${entry.ability_id}:${slotIndex}`)),
    })),
  };
  const foreignDie = await browserApi(page, `/api/v1/units/${fixture.unit_ids.bruiser}/loadout`, {
    method: 'PUT', csrf: initial.session.csrf_token, body: crossOwnerLoadout,
  });
  assert.equal(foreignDie.status, 422);

  const revision = await browserApi(page, '/api/v1/game/bootstrap');
  assert.equal(revision.status, 200);
  assert.equal(revision.body.data.player.player_revision, fixture.player_revision,
    'failed cross-player attempts must not increment player_revision');
  await context.close();
  return {
    foreignDetailStatus: foreignDetail.status,
    missingDetailStatus: missingDetail.status,
    foreignFormationStatus: foreignFormation.status,
    foreignSquadResults,
    foreignDieStatus: foreignDie.status,
    revisionAfterFailures: revision.body.data.player.player_revision,
  };
}

const browser = await chromium.launch();
try {
  const primary = await runPrimaryFlow(browser);
  const crossPlayer = await runCrossPlayerFlow(browser, primary);
  const report = {
    frontendUrl: FRONTEND_URL,
    apiUrl: API_URL,
    viewport: VIEWPORT,
    primary: primary.evidence,
    persistedUnit: primary.persistedDetail,
    crossPlayer,
    forbiddenPrototypeRequests: primary.requests.filter((request) =>
      request.path === '/api/v1/profile' || request.path.startsWith('/teams')),
  };
  await mkdir(path.dirname(OUTPUT_PATH), { recursive: true });
  await writeFile(OUTPUT_PATH, `${JSON.stringify(report, null, 2)}\n`, 'utf8');
  await primary.context.close();
  console.log(`Warband closure browser verification passed. Evidence: ${OUTPUT_PATH}`);
} finally {
  await browser.close();
}

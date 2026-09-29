import { mkdir, readFile } from "node:fs/promises";
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
    activeRun: false,
    progressionState: 'default',
    safeInsets: '',
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
      case "--active-run":
        options.activeRun = true;
        break;
      case "--progression-state":
        options.progressionState = next ?? 'default';
        index += 1;
        break;
      case "--safe-insets":
        options.safeInsets = next ?? '';
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
  --active-run              Preview Warband/squad/unit with a coherent active Farm run
  --progression-state <s>   default | owned | terminal | locked | unaffordable | below-level
  --safe-insets <t,r,b,l>   Inject debug-only CSS safe insets in pixels
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
  const scene = options.scene.trim().toLowerCase();
  await page.route('https://fonts.googleapis.com/**', (route) => route.fulfill({
    status: 200,
    contentType: 'text/css',
    body: '',
  }));
  const battleScenes = ['battle-early', 'battle-mid', 'battle-complete', 'battle-compact', 'battle-wide', 'battle-portrait', 'battle-defeat',
    'battle-result-victory', 'battle-result-defeat', 'battle-result-stalemate', 'battle-result-compact', 'battle-result-wide', 'battle-result-error', 'battle-result-portrait'];
  const runScenes = ['run', 'run-abandon', 'run-portrait', 'run-combat-available',
    'run-loot-available', 'run-loot-result', 'run-rest-result', 'run-supplies'];
  if (!['camp', 'camp-portrait', 'warband', 'shop', 'inventory', 'academy', 'unit-promotion', 'squad-editor', 'unit-configuration', ...runScenes, ...battleScenes].includes(scene)) return;

  if (battleScenes.includes(scene)) {
    await page.addInitScript(({ accountId }) => sessionStorage.setItem('dice-goblins:battle-presentation:v1', JSON.stringify({
      version: 1, accountId, battleId: '601', runId: '401', runNodeId: '501',
    })), { accountId: options.userId });
  }

  const projection = JSON.parse(await readFile(path.resolve(process.cwd(), 'frontend/public/game-content.json'), 'utf8'));
  projection.content.items = {
    ...projection.content.items,
    'item.capture.ore': { id: 'item.capture.ore', display_name: 'Raw Scrap', description: 'Useful material with no direct action.', category: 'material', rarity: 'common', icon_key: 'capture_ore', stackable: true },
  };
  const revision = projection.revision;
  const fixtureChaos = options.progressionState === 'unaffordable' ? 4 : 17;
  const fixtureLevel = options.progressionState === 'below-level' ? 2 : 5;
  const fixtureXp = options.progressionState === 'below-level' ? 74 : 185;
  await page.route('**/game-content.json', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify(projection),
  }));
  const unitRows = [
    ['101', 'Ashback', options.progressionState === 'terminal' && scene === 'unit-promotion' ? 'unit_type.juggernaut' : 'unit_type.bruiser', 'kin.goblin', fixtureLevel],
    ['102', 'Bogwort', 'unit_type.guardian', 'kin.pig', 4],
    ['103', 'Stitch', 'unit_type.marksman', 'kin.goblin', 4],
    ['104', 'Knuckles', 'unit_type.bannerbearer', 'kin.goblin', 3],
    ['105', 'Murk', 'unit_type.saboteur', 'kin.pig', 3],
    ['106', 'Rattle', 'unit_type.enforcer', 'kin.goblin', 6],
    ['107', 'Splint', 'unit_type.trapper', 'kin.goblin', 5],
    ['108', 'Nib', 'unit_type.mascot', 'kin.pig', 2],
  ].map(([id, display_name, unit_type_id, kin_id, level]) => ({
    id, display_name, unit_type_id, kin_id, level, xp: id === '101' ? fixtureXp : Number(level) * 37, lifecycle_status: 'active',
  }));
  const activeFormation = ['101', '102', null, '103', '104', null, '105', null, null];
  await page.route('**/api/v1/game/bootstrap', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({
      ok: true,
      data: {
        account: { id: options.userId, display_name: options.displayName, role: 'user' },
        player: {
          teeth: 1234,
          raw_chaos: fixtureChaos,
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
        progression: {
          unlock_ids: ['unlock.region.mountains'],
          available_region_ids: ['region.the_farm', 'region.mountains'],
        },
        active_squad: {
          id: '301', name: 'Bogbreakers', is_active: true, formation: activeFormation,
          units: unitRows.filter((unit) => activeFormation.includes(unit.id)),
        },
        active_run: options.activeRun || (scene === 'unit-promotion' && options.progressionState === 'locked') || runScenes.includes(scene)
          || (battleScenes.includes(scene) && !['battle-defeat', 'battle-result-defeat', 'battle-result-stalemate'].includes(scene))
          ? { id: '401', region_id: 'region.the_farm', squad_id: '301', status: 'active' }
          : null,
      },
    }),
  }));
  await page.route('**/api/v1/items', (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: { items: [
      { item_id: 'item.capture.ore', quantity: 7 },
      { item_id: 'item.field_poultice', quantity: 3 },
      { item_id: 'item.spark_tonic', quantity: 2 },
    ] } }),
  }));
  await page.route('**/api/v1/shop', (route) => route.fulfill({
    status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: {
      teeth: 7, player_revision: 3, offers: [
        { offer_id: 'shop_offer.cardboard_d4', price: { currency_id: 'teeth', amount: 4 }, available: true, can_afford: true },
        { offer_id: 'shop_offer.cardboard_d6', price: { currency_id: 'teeth', amount: 6 }, available: true, can_afford: true },
        { offer_id: 'shop_offer.cardboard_d8', price: { currency_id: 'teeth', amount: 8 }, available: true, can_afford: false },
        { offer_id: 'shop_offer.field_poultice', price: { currency_id: 'teeth', amount: 4 }, available: true, can_afford: true },
        { offer_id: 'shop_offer.goblin_bruiser', price: { currency_id: 'teeth', amount: 8 }, available: false, can_afford: false },
        { offer_id: 'shop_offer.spark_tonic', price: { currency_id: 'teeth', amount: 4 }, available: true, can_afford: true },
      ],
    } }),
  }));
  if (scene === 'academy') {
    const definitions = JSON.parse(await readFile(path.resolve(process.cwd(), 'backend/content/academy_upgrades/catalog.json'), 'utf8')).definitions;
    const catalog = definitions.map((upgrade) => ({ upgrade_id: upgrade.id, price: upgrade.price,
      owned: upgrade.id === 'academy_upgrade.guardian',
      available: upgrade.prerequisite_unlock_ids.length === 0 && upgrade.id !== 'academy_upgrade.guardian' }));
    await page.route('**/api/v1/academy', (route) => route.fulfill({ status: 200, contentType: 'application/json',
      body: JSON.stringify({ ok: true, data: { raw_chaos: fixtureChaos, player_revision: 3,
        upgrades: catalog.sort((a, b) => a.upgrade_id.localeCompare(b.upgrade_id)) } }) }));
    return;
  }
  if (battleScenes.includes(scene)) {
    const defeat = ['battle-defeat', 'battle-result-defeat'].includes(scene);
    const stalemate = scene === 'battle-result-stalemate';
    const outcome = defeat ? 'defeat' : stalemate ? 'stalemate' : 'victory';
    const participants = [
      { combatant_key: 'ashback', side: 'player', unit_id: '101', unit_type_id: 'unit_type.bruiser', enemy_unit_type_id: null,
        display_name: 'Ashback', art_key: 'goblin_bruiser', position: { x: 1, y: 1 }, initial_hp: 24, max_hp: 24,
        terminal_hp: defeat ? 0 : stalemate ? 6 : 13, is_defeated: defeat, terminal_statuses: [] },
      { combatant_key: 'mudwrestler', side: 'enemy', unit_id: null, unit_type_id: null, enemy_unit_type_id: 'enemy_unit_type.mudwrestler',
        display_name: 'Mudwrestler', art_key: 'enemy_mudwrestler', position: { x: 2, y: 1 }, initial_hp: 18, max_hp: 18,
        terminal_hp: defeat ? 7 : stalemate ? 4 : 0, is_defeated: !defeat && !stalemate, terminal_statuses: stalemate ? [] : [{
          id: 'shield_set', source_key: 'mudwrestler', expires_round: 2,
          params: { stacks: 2, defense_flat_per_stack: 1 }, forced_target_key: null,
        }] },
      { combatant_key: 'mudslinger', side: 'enemy', unit_id: null, unit_type_id: null, enemy_unit_type_id: 'enemy_unit_type.mudslinger',
        display_name: 'Mudslinger', art_key: 'enemy_mudslinger', position: { x: 0, y: 1 }, initial_hp: 12, max_hp: 12,
        terminal_hp: defeat ? 8 : stalemate ? 3 : 0, is_defeated: !defeat && !stalemate, terminal_statuses: [] },
    ];
    const actor = defeat ? 'mudwrestler' : 'ashback'; const target = defeat ? 'ashback' : 'mudwrestler';
    const hpBefore = defeat ? 24 : 18; const hpAfter = defeat ? 0 : 0;
    const decisiveEvents = [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['ashback', 'mudslinger', 'mudwrestler'] } },
      { sequence: 1, type: 'round_started', round: 1, tick: 1, facts: {} },
      { sequence: 2, type: 'status_applied', round: 1, tick: 1, facts: { target_key: 'mudwrestler', status_id: 'shield_set',
        source_key: 'mudwrestler', expires_round: 2, params: { stacks: 1, defense_flat_per_stack: 1 }, forced_target_key: null } },
      { sequence: 3, type: 'status_removed', round: 1, tick: 1,
        facts: { target_key: 'mudwrestler', status_id: 'shield_set', reason: 'replaced' } },
      { sequence: 4, type: 'status_applied', round: 1, tick: 1, facts: { target_key: 'mudwrestler', status_id: 'shield_set',
        source_key: 'mudwrestler', expires_round: 2, params: { stacks: 2, defense_flat_per_stack: 1 }, forced_target_key: null } },
      { sequence: 5, type: 'action_started', round: 1, tick: 1, facts: { actor_key: actor, ability_id: defeat ? 'ability.wrestle' : 'ability.heavy_strike', target_key: target, target_reason: 'front_preference' } },
      { sequence: 6, type: 'dice_rolled', round: 1, tick: 1, facts: { actor_key: actor, ability_id: defeat ? 'ability.wrestle' : 'ability.heavy_strike', slot: 0, die_key: 'die_0', sides: 6, initial_roll: 6, extra_roll: null, roll_total: 6 } },
      { sequence: 7, type: 'hit_resolved', round: 1, tick: 1, facts: { actor_key: actor, target_key: target, result: 'critical', chance_percent: 90, check_roll: 4 } },
      { sequence: 8, type: 'damage_dealt', round: 1, tick: 1, facts: { actor_key: actor, target_key: target, amount: hpBefore, hp_before: hpBefore, hp_after: hpAfter, attack_component: 12, roll_total: 6, target_defense: 2, conditional_multiplier: 1, position_multiplier: 1 } },
      { sequence: 9, type: 'death', round: 1, tick: 1, facts: { combatant_key: target } },
      { sequence: 10, type: 'battle_ended', round: 1, tick: 1, facts: { outcome } },
    ];
    const events = stalemate ? [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['ashback', 'mudslinger', 'mudwrestler'] } },
      { sequence: 1, type: 'round_started', round: 100, tick: 100, facts: {} },
      { sequence: 2, type: 'battle_ended', round: 100, tick: 100, facts: { outcome } },
    ] : decisiveEvents;
    await page.route('**/api/v1/battles/601/playback', (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({
      ok: true, data: { battle: { id: '601', run_id: '401', run_node_id: '501', engine_version: 1, playback_version: 1,
        outcome, ending_round: stalemate ? 100 : 1, ending_tick: stalemate ? 100 : 1, participants, events }, player_revision: 4 },
    }) }));
    if (scene === 'battle-result-error') await page.route('**/api/v1/runs/current', (route) => route.fulfill({
      status: 503, contentType: 'application/json', body: JSON.stringify({ ok: false, error: { code: 'server_error' } }),
    }));
    return;
  }
  if (runScenes.includes(scene)) {
    let resolvedStage = scene === 'run-rest-result' ? 'loot' : 'combat';
    const runResponse = () => {
      const combatAvailable = scene === 'run-combat-available';
      const lootCompleted = resolvedStage === 'loot' || resolvedStage === 'rest';
      const restCompleted = resolvedStage === 'rest';
      return { ok: true, data: {
        run: { id: '401', region_id: 'region.the_farm', squad_id: '301', status: 'active', created_at: '2026-09-13T12:00:00Z',
          nodes: [
            { id: '501', node_index: 0, node_type_id: 'run_node_type.combat', status: combatAvailable ? 'available' : 'completed', completed_at: combatAvailable ? null : '2026-09-13T12:02:00Z', battle_id: combatAvailable ? null : '601', position: { column: 0, row: 1 } },
            { id: '502', node_index: 1, node_type_id: 'run_node_type.loot', status: combatAvailable ? 'locked' : lootCompleted ? 'completed' : 'available', completed_at: lootCompleted ? '2026-09-13T12:03:00Z' : null, battle_id: null, position: { column: 1, row: 1 } },
            { id: '503', node_index: 2, node_type_id: 'run_node_type.rest', status: lootCompleted ? restCompleted ? 'completed' : 'available' : 'locked', completed_at: restCompleted ? '2026-09-13T12:04:00Z' : null, battle_id: null, position: { column: 2, row: 1 } },
            { id: '504', node_index: 3, node_type_id: 'run_node_type.boss', status: restCompleted ? 'available' : 'locked', completed_at: null, battle_id: null, position: { column: 3, row: 1 } },
            { id: '505', node_index: 4, node_type_id: 'run_node_type.exit', status: 'locked', completed_at: null, battle_id: null, position: { column: 4, row: 1 } },
          ],
          edges: [
            { from_node_id: '501', to_node_id: '502' }, { from_node_id: '502', to_node_id: '503' },
            { from_node_id: '503', to_node_id: '504' }, { from_node_id: '504', to_node_id: '505' },
          ], units: activeFormation.filter(Boolean).map((unit_id, index) => ({ unit_id, current_hp: restCompleted ? 24 + index : index === 0 ? 0 : 12 })) },
        player_revision: resolvedStage === 'combat' ? 3 : resolvedStage === 'loot' ? 4 : 5,
      } };
    };
    await page.route('**/api/v1/runs/current', (route) => route.fulfill({
      status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: {
        ...runResponse().data,
      } }),
    }));
    await page.route('**/api/v1/runs/401/nodes/*/resolve', (route) => {
      const loot = route.request().url().includes('/nodes/502/');
      resolvedStage = loot ? 'loot' : 'rest';
      const common = { node: { id: loot ? '502' : '503', status: 'completed', completed_at: loot ? '2026-09-13T12:03:00Z' : '2026-09-13T12:04:00Z' },
        newly_available_node_ids: [loot ? '503' : '504'], run: { id: '401', status: 'active', ended_at: null }, player_revision: loot ? 4 : 5 };
      const data = loot ? { resolution_type: 'loot', wallet: { teeth: 1242 },
        granted_rewards: [{ reward_type: 'currency', currency_id: 'teeth', amount: 8 }], ...common }
        : { resolution_type: 'rest', healing: activeFormation.filter(Boolean).map((unit_id, index) => ({
          unit_id, hp_before: index === 0 ? 0 : 12, hp_after: 24 + index, max_hp: 24 + index,
        })), ...common };
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data }) });
    });
    return;
  }
  if (['shop', 'inventory'].includes(scene)) return;
  if (!['warband', 'squad-editor', 'unit-configuration', 'unit-promotion'].includes(scene)) return;
  await page.route('**/api/v1/units', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { units: unitRows } }),
  }));
  await page.route('**/api/v1/dice', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { dice: (options.activeRun ? [
      { id: '201', size: 8, profile_id: 'dice_profile.bone_executioner', lifecycle_status: 'active', bindings: [{ unit_id: '101', ability_id: 'ability.heavy_strike', slot_index: 0 }] },
      { id: '203', size: 6, profile_id: 'dice_profile.wood_precise', lifecycle_status: 'active', bindings: [{ unit_id: '101', ability_id: 'ability.basic_attack_melee', slot_index: 0 }] },
      { id: '204', size: 4, profile_id: 'dice_profile.cardboard_guarding', lifecycle_status: 'active', bindings: [] },
    ] : [
      { id: '204', size: 4, profile_id: 'dice_profile.cardboard_guarding', lifecycle_status: 'active', bindings: [] },
      { id: '201', size: 8, profile_id: 'dice_profile.bone_executioner', lifecycle_status: 'active', bindings: [{ unit_id: '101', ability_id: 'ability.heavy_strike', slot_index: 0 }] },
      { id: '203', size: 6, profile_id: 'dice_profile.wood_precise', lifecycle_status: 'active', bindings: [{ unit_id: '101', ability_id: 'ability.basic_attack_melee', slot_index: 0 }] },
      { id: '202', size: 6, profile_id: 'dice_profile.wood_precise', lifecycle_status: 'active', bindings: [{ unit_id: '103', ability_id: 'ability.aimed_shot', slot_index: 0 }] },
    ]) } }),
  }));
  await page.route('**/api/v1/squads', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { squads: [
      { id: '301', name: 'Bogbreakers', is_active: true, formation: activeFormation },
      { id: '302', name: 'Night Scavengers', is_active: false, formation: ['106', null, null, null, '107', null, null, null, '108'] },
    ] } }),
  }));
  await page.route('**/api/v1/units/101', (route) => route.fulfill({
    status: 200, contentType: 'application/json',
    body: JSON.stringify({ ok: true, data: { unit: {
      id: '101', display_name: 'Ashback', unit_type_id: unitRows[0].unit_type_id, kin_id: 'kin.goblin',
      level: fixtureLevel, xp: fixtureXp, xp_to_next_level: 300, lifecycle_status: 'active',
      promotion_history: options.progressionState === 'terminal' && scene === 'unit-promotion' ? [
        { from_unit_type_id: 'unit_type.bruiser', to_unit_type_id: 'unit_type.enforcer', promoted_at: '2026-09-01T00:00:00Z' },
        { from_unit_type_id: 'unit_type.enforcer', to_unit_type_id: 'unit_type.juggernaut', promoted_at: '2026-09-02T00:00:00Z' },
      ] : [],
      owned_ability_ids: ['ability.basic_attack_melee', 'ability.heavy_strike', 'ability.thick_hide', 'ability.menacing_follow_through'],
      ability_loadout: [
        { ability_id: 'ability.heavy_strike', equip_order: 0 },
        { ability_id: 'ability.basic_attack_melee', equip_order: 1 },
      ],
      dice_bindings: [
        { ability_id: 'ability.heavy_strike', slot_index: 0, dice_instance_id: '201' },
        { ability_id: 'ability.basic_attack_melee', slot_index: 0, dice_instance_id: '203' },
      ],
    } } }),
  }));
  if (scene === 'unit-promotion') {
    const definitions = JSON.parse(await readFile(path.resolve(process.cwd(), 'backend/content/unit_promotions/catalog.json'), 'utf8')).definitions;
    const unitTypeId = unitRows[0].unit_type_id;
    const locked = options.activeRun || options.progressionState === 'locked';
    const optionsRows = definitions.filter((promotion) => promotion.from_unit_type_id === unitTypeId)
      .map((promotion) => ({ promotion_id: promotion.id, target_unit_type_id: promotion.to_unit_type_id,
        required_level: promotion.required_level, price: promotion.price, level_met: fixtureLevel >= promotion.required_level,
        can_afford: fixtureChaos >= promotion.price.amount, available: fixtureLevel >= promotion.required_level && !locked,
        new_ability_ids: projection.content.unit_types[promotion.to_unit_type_id].ability_ids
          .filter((id) => !['ability.basic_attack_melee', 'ability.heavy_strike', 'ability.thick_hide', 'ability.menacing_follow_through'].includes(id)) }))
      .sort((a, b) => a.promotion_id.localeCompare(b.promotion_id));
    await page.route('**/api/v1/units/101/promotion-options', (route) => route.fulfill({
      status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, data: {
        unit_id: '101', unit_type_id: unitTypeId, level: fixtureLevel, xp: fixtureXp, xp_to_next_level: 300,
        raw_chaos: fixtureChaos, player_revision: 3, configuration_locked: locked, options: optionsRows,
      } }),
    }));
  }
}

function createCaptureUrl(options) {
  const baseUrl = options.baseUrl || `http://${options.host}:${options.port}/`;
  const url = new URL(baseUrl);
  url.searchParams.set("debugScene", options.scene);
  url.searchParams.set("debugAuth", options.auth);
  url.searchParams.set("debugDisplayName", options.displayName);
  url.searchParams.set("debugUserId", options.userId);
  url.searchParams.set("debugSceneData", options.progressionState === 'owned' && options.scene === 'academy'
    ? JSON.stringify({ page: 1 }) : options.sceneData);
  url.searchParams.set("debugSettleMs", `${options.settleMs}`);
  if (options.safeInsets) url.searchParams.set('debugSafeInsets', options.safeInsets);
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
      const portraitResult = options.scene.trim().toLowerCase() === 'battle-result-portrait';
      const context = await browser.newContext({
        viewport: portraitResult ? { width: 844, height: 390 } : { width: options.width, height: options.height },
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
        if (['camp', 'camp-portrait', 'warband', 'shop', 'inventory', 'academy', 'unit-promotion', 'squad-editor', 'unit-configuration', 'run', 'run-abandon', 'run-portrait', 'run-combat-available',
          'run-loot-available', 'run-loot-result', 'run-rest-result', 'run-supplies',
          'battle-early', 'battle-mid', 'battle-complete', 'battle-compact', 'battle-wide', 'battle-portrait', 'battle-defeat',
          'battle-result-victory', 'battle-result-defeat', 'battle-result-stalemate', 'battle-result-compact', 'battle-result-wide', 'battle-result-error', 'battle-result-portrait'].includes(options.scene.trim().toLowerCase())) {
          await page.waitForSelector('.game-host__mount canvas', { timeout: options.timeoutMs });
          const requestedGameScreen = options.scene.trim().toLowerCase();
          const gameScreen = ['warband', 'shop', 'inventory', 'academy', 'unit-promotion', 'squad-editor', 'unit-configuration'].includes(requestedGameScreen)
            ? requestedGameScreen : requestedGameScreen.startsWith('battle-') ? 'battle'
              : ['run', 'run-abandon', 'run-portrait', 'run-combat-available', 'run-loot-available', 'run-loot-result', 'run-rest-result', 'run-supplies'].includes(requestedGameScreen) ? 'run' : 'camp';
          await page.waitForSelector(`[data-game-screen="${gameScreen}"]`, { timeout: options.timeoutMs });
          if (gameScreen === 'warband') {
            await page.waitForSelector('[data-warband-ready="true"]', { timeout: options.timeoutMs });
          }
          if (gameScreen === 'squad-editor') {
            await page.waitForSelector('[data-squad-editor-ready="true"]', { timeout: options.timeoutMs });
          }
          if (gameScreen === 'unit-configuration') {
            await page.waitForSelector('[data-unit-configuration-ready="true"]', { timeout: options.timeoutMs });
          }
          if (gameScreen === 'academy') {
            await page.waitForSelector('[data-academy-ready="true"]', { timeout: options.timeoutMs });
          }
          if (gameScreen === 'unit-promotion') {
            await page.waitForSelector('[data-unit-promotion-ready="true"]', { timeout: options.timeoutMs });
          }
          if (gameScreen === 'run') {
            await page.waitForSelector('[data-run-map-ready="true"]', { state: 'attached', timeout: options.timeoutMs });
          }
          if (gameScreen === 'battle' && ['battle-complete', 'battle-defeat', 'battle-result-victory', 'battle-result-defeat', 'battle-result-stalemate',
            'battle-result-compact', 'battle-result-wide', 'battle-result-error', 'battle-result-portrait'].includes(requestedGameScreen)) {
            await page.waitForSelector('[data-battle-playback="complete"]', { timeout: options.timeoutMs });
          }
          if (requestedGameScreen === 'battle-result-error') {
            const canvas = page.locator('.game-host__mount canvas'); const box = await canvas.boundingBox();
            if (!box) throw new Error('Battle result canvas was unavailable.');
            await canvas.click({ position: { x: box.width / 2, y: box.height * 845 / 900 }, force: true });
            await page.waitForSelector('[data-battle-continue="error"]', { timeout: options.timeoutMs });
          }
          if (requestedGameScreen === 'battle-result-portrait') {
            await page.setViewportSize({ width: options.width, height: options.height });
            await page.waitForSelector('[data-game-orientation-gate="active"]', { timeout: options.timeoutMs });
          }
          if (requestedGameScreen === 'run-abandon') {
            await page.waitForSelector('[data-run-abandon-confirmation="true"]', { state: 'attached', timeout: options.timeoutMs });
          }
          if (requestedGameScreen === 'run-combat-available') {
            const canvas = page.locator('.game-host__mount canvas'); const box = await canvas.boundingBox();
            if (!box) throw new Error('Run canvas was unavailable.');
            await canvas.click({ position: { x: box.width * 225 / 1600, y: box.height * 418 / 900 }, force: true });
          }
          if (['run-loot-available', 'run-loot-result', 'run-rest-result'].includes(requestedGameScreen)) {
            const canvas = page.locator('.game-host__mount canvas'); const box = await canvas.boundingBox();
            if (!box) throw new Error('Run canvas was unavailable.');
            const nodeX = requestedGameScreen === 'run-rest-result' ? 800 : options.width <= 900 ? 562 : 512;
            await canvas.click({ position: { x: box.width * nodeX / 1600, y: box.height * 418 / 900 }, force: true });
            if (requestedGameScreen.endsWith('-result')) {
              await canvas.click({ position: { x: box.width / 2, y: box.height * 800 / 900 }, force: true });
              await page.waitForSelector(`[data-run-node-result="${requestedGameScreen === 'run-loot-result' ? 'loot' : 'rest'}"]`, { timeout: options.timeoutMs });
              await page.waitForSelector('[data-run-node-sync="succeeded"]', { timeout: options.timeoutMs });
            }
          }
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
              warbandReady: host?.getAttribute('data-warband-ready'),
            };
          });
          console.log(`Runtime metrics: ${JSON.stringify(runtimeMetrics)}`);
        }
      } catch (error) {
        const debugState = await readDebugState(page);
        const gameHostState = await page.evaluate(() => {
          const host = document.querySelector('.game-host__mount');
          return host ? Object.fromEntries(Array.from(host.attributes).map((attribute) => [attribute.name, attribute.value])) : null;
        }).catch(() => null);
        const diagnostics = [
          `Capture URL: ${captureUrl}`,
          `Debug state: ${JSON.stringify(debugState)}`,
          `Game host state: ${JSON.stringify(gameHostState)}`,
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

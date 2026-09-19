import { RunNodeResolutionContractError, parseRunNodeResolutionEnvelope } from './run-node-resolution-contracts';

describe('run node resolution contracts', () => {
  function envelope(): any { return { ok: true, data: {
    resolution_type: 'combat',
    battle: { id: '81', outcome: 'victory', engine_version: 1, playback_version: 1, ending_round: 3, ending_tick: 41 },
    node: { id: '10', status: 'completed', completed_at: '2026-09-16T12:00:00Z' }, newly_available_node_ids: ['11'],
    terminal_player_hp: { '21': 12, '22': 0 }, run: { id: '41', status: 'active', ended_at: null }, player_revision: 8,
  } }; }

  it('parses the exact Package 4 response without deriving combat state', () => {
    const parsed = parseRunNodeResolutionEnvelope(envelope());
    expect(parsed.resolutionType).toBe('combat');
    if (parsed.resolutionType !== 'combat') throw new Error('Expected combat result.');
    expect(parsed.battle).toEqual(jasmine.objectContaining({ id: '81', outcome: 'victory', endingTick: 41 }));
    expect(parsed.terminalPlayerHp).toEqual({ '21': 12, '22': 0 });
  });

  it('parses strict player-safe Loot and Rest variants without private authored facts', () => {
    const loot = parseRunNodeResolutionEnvelope({ ok: true, data: { resolution_type: 'loot',
      wallet: { teeth: 18 }, granted_rewards: [{ reward_type: 'currency', currency_id: 'teeth', amount: 8 }],
      node: { id: '11', status: 'completed', completed_at: '2026-09-17T12:00:00Z' }, newly_available_node_ids: ['12'],
      run: { id: '41', status: 'active', ended_at: null }, player_revision: 9 } });
    expect(loot.resolutionType).toBe('loot');
    if (loot.resolutionType !== 'loot') throw new Error('Expected Loot result.');
    expect(loot.wallet.teeth).toBe(18); expect(loot.grantedRewards[0].amount).toBe(8);

    const rest = parseRunNodeResolutionEnvelope({ ok: true, data: { resolution_type: 'rest',
      healing: [{ unit_id: '21', hp_before: 0, hp_after: 26, max_hp: 26 }],
      node: { id: '12', status: 'completed', completed_at: '2026-09-17T12:01:00Z' }, newly_available_node_ids: ['13'],
      run: { id: '41', status: 'active', ended_at: null }, player_revision: 10 } });
    expect(rest.resolutionType).toBe('rest');
    if (rest.resolutionType !== 'rest') throw new Error('Expected Rest result.');
    expect(rest.healing).toEqual([{ unitId: '21', hpBefore: 0, hpAfter: 26, maxHp: 26 }]);
  });

  it('parses strict player-safe Boss progression and rejects private or incoherent rewards', () => {
    const boss = { ok: true, data: { ...envelope().data, resolution_type: 'boss', rewards: {
      unit_xp: [{ unit_id: '21', amount: 16, level_before: 1, xp_before: 90, level_after: 2, xp_after: 6 }],
      mountains: { region_id: 'region.mountains', outcome: 'granted' },
    } } };
    const parsed = parseRunNodeResolutionEnvelope(boss);
    expect(parsed.resolutionType).toBe('boss');
    if (parsed.resolutionType !== 'boss' || !parsed.rewards) throw new Error('Expected Boss rewards.');
    expect(parsed.rewards.unitXp[0]).toEqual(jasmine.objectContaining({ unitId: '21', amount: 16, levelAfter: 2, xpAfter: 6 }));
    expect(parsed.rewards.mountains).toEqual({ regionId: 'region.mountains', outcome: 'granted' });
    const leaked = structuredClone(boss); leaked.data.rewards.event_id = 'event.farm_boss_completed';
    expect(() => parseRunNodeResolutionEnvelope(leaked)).toThrowError(RunNodeResolutionContractError);
    const wrongAmount = structuredClone(boss); wrongAmount.data.rewards.unit_xp[0].amount = 15;
    expect(() => parseRunNodeResolutionEnvelope(wrongAmount)).toThrowError(RunNodeResolutionContractError);
    const failedWithRewards = structuredClone(boss); failedWithRewards.data.battle.outcome = 'defeat';
    failedWithRewards.data.run = { id: '41', status: 'failed', ended_at: '2026-09-16T12:00:00Z' };
    expect(() => parseRunNodeResolutionEnvelope(failedWithRewards)).toThrowError(RunNodeResolutionContractError);
  });

  it('rejects private reward facts and incoherent Rest transitions', () => {
    expect(() => parseRunNodeResolutionEnvelope({ ok: true, data: { resolution_type: 'loot',
      wallet: { teeth: 18 }, granted_rewards: [{ reward_type: 'currency', currency_id: 'teeth', amount: 8, roll: 1 }],
      node: { id: '11', status: 'completed', completed_at: '2026-09-17T12:00:00Z' }, newly_available_node_ids: ['12'],
      run: { id: '41', status: 'active', ended_at: null }, player_revision: 9 } })).toThrowError(RunNodeResolutionContractError);
    expect(() => parseRunNodeResolutionEnvelope({ ok: true, data: { resolution_type: 'rest',
      healing: [{ unit_id: '21', hp_before: 20, hp_after: 19, max_hp: 26 }],
      node: { id: '12', status: 'completed', completed_at: '2026-09-17T12:01:00Z' }, newly_available_node_ids: ['13'],
      run: { id: '41', status: 'active', ended_at: null }, player_revision: 10 } })).toThrowError(RunNodeResolutionContractError);
  });

  it('rejects field, identity, version, outcome, HP, lifecycle, completion, revision, and duplicate incoherence', () => {
    const mutations = [
      (v: any) => v.data.extra = true, (v: any) => v.data.battle.id = '081',
      (v: any) => v.data.battle.engine_version = 2, (v: any) => v.data.battle.outcome = 'draw',
      (v: any) => v.data.node.status = 'available', (v: any) => v.data.node.completed_at = 'bad',
      (v: any) => v.data.newly_available_node_ids.push('11'), (v: any) => v.data.terminal_player_hp['21'] = -1,
      (v: any) => v.data.terminal_player_hp['bad'] = 1,
      (v: any) => { v.data.run.status = 'active'; v.data.run.ended_at = '2026-09-16T12:00:00Z'; },
      (v: any) => { v.data.run.status = 'failed'; v.data.run.ended_at = null; }, (v: any) => v.data.player_revision = -1,
    ];
    for (const mutate of mutations) { const value = envelope(); mutate(value); expect(() => parseRunNodeResolutionEnvelope(value)).toThrowError(RunNodeResolutionContractError); }
  });
});

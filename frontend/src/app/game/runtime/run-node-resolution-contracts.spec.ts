import { RunNodeResolutionContractError, parseRunNodeResolutionEnvelope } from './run-node-resolution-contracts';

describe('run node resolution contracts', () => {
  function envelope(): any { return { ok: true, data: {
    battle: { id: '81', outcome: 'victory', engine_version: 1, playback_version: 1, ending_round: 3, ending_tick: 41 },
    node: { id: '10', status: 'completed', completed_at: '2026-09-16T12:00:00Z' }, newly_available_node_ids: ['11'],
    terminal_player_hp: { '21': 12, '22': 0 }, run: { id: '41', status: 'active', ended_at: null }, player_revision: 8,
  } }; }

  it('parses the exact Package 4 response without deriving combat state', () => {
    const parsed = parseRunNodeResolutionEnvelope(envelope());
    expect(parsed.battle).toEqual(jasmine.objectContaining({ id: '81', outcome: 'victory', endingTick: 41 }));
    expect(parsed.terminalPlayerHp).toEqual({ '21': 12, '22': 0 });
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

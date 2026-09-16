import { BattlePlaybackContractError, parseBattlePlaybackEnvelope } from './battle-playback-contracts';

describe('battle playback contracts', () => {
  function envelope(): any {
    return { ok: true, data: { battle: {
      id: '81', run_id: '71', run_node_id: '72', engine_version: 1, playback_version: 1,
      outcome: 'victory', ending_round: 1, ending_tick: 1,
      participants: [
        { combatant_key: 'player_bruiser', side: 'player', unit_id: '11', unit_type_id: 'unit_type.bruiser',
          enemy_unit_type_id: null, display_name: 'Bash', art_key: 'unit.bruiser', position: { x: 1, y: 1 },
          initial_hp: 20, max_hp: 20, terminal_hp: 12, is_defeated: false, terminal_statuses: [] },
        { combatant_key: 'mudwrestler', side: 'enemy', unit_id: null, unit_type_id: null,
          enemy_unit_type_id: 'enemy_unit_type.mudwrestler', display_name: 'Mudwrestler', art_key: 'enemy.mudwrestler',
          position: { x: 2, y: 1 }, initial_hp: 12, max_hp: 12, terminal_hp: 0, is_defeated: true, terminal_statuses: [] },
      ],
      events: [
        { sequence: 0, type: 'battle_started', round: 0, tick: 0,
          facts: { combatant_keys: ['mudwrestler', 'player_bruiser'] } },
        { sequence: 1, type: 'round_started', round: 1, tick: 1, facts: {} },
        { sequence: 2, type: 'action_started', round: 1, tick: 1,
          facts: { actor_key: 'player_bruiser', ability_id: 'ability.basic_attack_melee', target_key: 'mudwrestler', target_reason: 'front_preference' } },
        { sequence: 3, type: 'damage_dealt', round: 1, tick: 1,
          facts: { actor_key: 'player_bruiser', target_key: 'mudwrestler', amount: 12, hp_before: 12, hp_after: 0,
            attack_component: 7, roll_total: 5, target_defense: 0, conditional_multiplier: 1, position_multiplier: 1 } },
        { sequence: 4, type: 'death', round: 1, tick: 1, facts: { combatant_key: 'mudwrestler' } },
        { sequence: 5, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } },
      ],
    }, player_revision: 9 } };
  }

  it('accepts the version-1 presentation payload and preserves semantic event order and facts', () => {
    const parsed = parseBattlePlaybackEnvelope(envelope());
    expect(parsed.battle.id).toBe('81');
    expect(parsed.battle.participants[0].displayName).toBe('Bash');
    expect(parsed.battle.events.map((event) => event.type)).toEqual(['battle_started', 'round_started', 'action_started', 'damage_dealt', 'death', 'battle_ended']);
    expect(parsed.battle.events[3].facts).toEqual(envelope().data.battle.events[3].facts);
  });

  it('rejects fields, IDs, versions, identity, position, HP, and terminal outcome incoherence', () => {
    const mutations = [
      (v: any) => v.data.battle.seed = 'secret',
      (v: any) => v.data.battle.id = '01',
      (v: any) => v.data.battle.playback_version = 2,
      (v: any) => v.data.battle.outcome = 'draw',
      (v: any) => v.data.battle.participants[1].side = 'player',
      (v: any) => v.data.battle.participants[1].combatant_key = 'player_bruiser',
      (v: any) => v.data.battle.participants[0].position.x = 3,
      (v: any) => v.data.battle.participants[0].initial_hp = 21,
      (v: any) => v.data.battle.participants[1].is_defeated = false,
      (v: any) => v.data.battle.participants[1].terminal_hp = 1,
    ];
    for (const mutate of mutations) {
      const candidate = envelope(); mutate(candidate);
      expect(() => parseBattlePlaybackEnvelope(candidate)).toThrowError(BattlePlaybackContractError);
    }
  });

  it('rejects malformed sequence, event shapes/references, ordering, and battle-end facts', () => {
    const mutations = [
      (v: any) => v.data.battle.events[3].sequence = 9,
      (v: any) => v.data.battle.events[3].facts.extra = true,
      (v: any) => v.data.battle.events[2].facts.actor_key = 'unknown',
      (v: any) => v.data.battle.events[3].tick = 0,
      (v: any) => v.data.battle.events[5].tick = 2,
      (v: any) => v.data.battle.events[5].facts.outcome = 'defeat',
      (v: any) => v.data.battle.events.push({ sequence: 6, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } }),
    ];
    for (const mutate of mutations) {
      const candidate = envelope(); mutate(candidate);
      expect(() => parseBattlePlaybackEnvelope(candidate)).toThrowError(BattlePlaybackContractError);
    }
  });
});

import { BattlePlaybackController } from './battle-playback-controller';
import { BattlePlaybackResult } from './battle-playback-contracts';

describe('BattlePlaybackController', () => {
  function result(): BattlePlaybackResult { return { battle: {
    id: '81', runId: '41', runNodeId: '10', engineVersion: 1, playbackVersion: 1, outcome: 'victory', endingRound: 1, endingTick: 1,
    participants: [
      { combatantKey: 'hero', side: 'player', unitId: '21', unitTypeId: 'unit_type.bruiser', enemyUnitTypeId: null,
        displayName: 'Historical Bash', artKey: 'unit.bruiser', position: { x: 1, y: 1 }, initialHp: 20, maxHp: 20,
        terminalHp: 7, isDefeated: false, terminalStatuses: [] },
      { combatantKey: 'mud', side: 'enemy', unitId: null, unitTypeId: null, enemyUnitTypeId: 'enemy_unit_type.mudwrestler',
        displayName: 'Mudwrestler', artKey: 'enemy.mudwrestler', position: { x: 2, y: 1 }, initialHp: 12, maxHp: 12,
        terminalHp: 0, isDefeated: true, terminalStatuses: [] },
    ], events: [
      { sequence: 0, type: 'battle_started', round: 0, tick: 0, facts: { combatant_keys: ['hero', 'mud'] } },
      { sequence: 1, type: 'action_started', round: 1, tick: 1, facts: { actor_key: 'mud', ability_id: 'ability.mud_sling', target_key: 'hero', target_reason: 'recorded' } },
      { sequence: 2, type: 'dice_rolled', round: 1, tick: 1, facts: { actor_key: 'mud', ability_id: 'ability.mud_sling', slot: 0, die_key: 'd', sides: 6, initial_roll: 4, extra_roll: null, roll_total: 4 } },
      { sequence: 3, type: 'hit_resolved', round: 1, tick: 1, facts: { actor_key: 'mud', target_key: 'hero', result: 'critical', chance_percent: 1, check_roll: 99 } },
      { sequence: 4, type: 'damage_dealt', round: 1, tick: 1, facts: { actor_key: 'mud', target_key: 'hero', amount: 999, hp_before: 20, hp_after: 7, attack_component: 0, roll_total: 4, target_defense: 999, conditional_multiplier: 1, position_multiplier: 1 } },
      { sequence: 5, type: 'status_applied', round: 1, tick: 1, facts: { target_key: 'hero', status_id: 'sleep', source_key: 'mud', expires_round: 2, params: {}, forced_target_key: null } },
      { sequence: 6, type: 'status_removed', round: 1, tick: 1, facts: { target_key: 'hero', status_id: 'sleep', reason: 'damage' } },
      { sequence: 7, type: 'death', round: 1, tick: 1, facts: { combatant_key: 'mud' } },
      { sequence: 8, type: 'battle_ended', round: 1, tick: 1, facts: { outcome: 'victory' } },
    ],
  }, playerRevision: 8 }; }

  it('consumes every persisted event in exact sequence and applies only recorded presentation facts', () => {
    const controller = new BattlePlaybackController(result());
    while (controller.snapshot.state === 'playing') controller.advance();
    expect(controller.snapshot.consumedSequences).toEqual([0, 1, 2, 3, 4, 5, 6, 7, 8]);
    const hero = controller.snapshot.participants.find((unit) => unit.combatantKey === 'hero')!;
    const mud = controller.snapshot.participants.find((unit) => unit.combatantKey === 'mud')!;
    expect(hero.currentHp).toBe(7); expect(hero.statuses.size).toBe(0); expect(mud.defeated).toBeTrue();
    expect(controller.snapshot.dice).toBe('d6: 4'); expect(controller.snapshot.hit).toBe('CRITICAL');
    expect(controller.snapshot.outcome).toBe('victory'); expect(controller.snapshot.state).toBe('complete');
  });

  it('pauses without consuming hidden events and resumes at the same sequence', () => {
    const controller = new BattlePlaybackController(result()); controller.advance(); controller.pause();
    expect(controller.advance()).toBeNull(); expect(controller.snapshot.nextSequence).toBe(1);
    controller.resume(); expect(controller.advance()?.sequence).toBe(1);
  });
});

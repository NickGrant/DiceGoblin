import { TestBed } from '@angular/core/testing';
import { ResolveNodeData, UnitRecord } from '../../models/api.models';
import { BattlePlaybackAdapterService } from './battle-playback-adapter.service';

describe('BattlePlaybackAdapterService', () => {
  const playerUnits: UnitRecord[] = [
    { id: 'u1', name: 'Ashback', unit_type_slug: 'goblin_bruiser', unit_type_name: 'Bruiser', level: 2, current_hp: 20, max_hp: 20 },
    { id: 'u2', name: 'Bogwort', unit_type_slug: 'goblin_bannerbearer', unit_type_name: 'Bannerbearer', level: 2, current_hp: 13, max_hp: 18 },
  ];
  const result: ResolveNodeData = {
    node: { id: 'n1', status: 'cleared' },
    battle: {
      battle_id: 'b1',
      outcome: 'victory',
      rounds: 2,
      ticks: 12,
      status: 'completed',
      reward_preview: {
        node_type: 'boss',
        xp_total: 14,
        currency_soft: 5,
        new_unit_labels: ['Warcaller'],
        new_dice_labels: ['bone d6'],
      },
      log: {
        events: [
          {
            type: 'action',
            round: 1,
            tick: 4,
            side: 'player',
            actor_unit_instance_id: 'u1',
            target_enemy_slug: 'goblin_raider',
            ability_id: 'heavy_strike',
            slot_traces: [
              {
                slot_index: 0,
                dice_instance_id: 'd1',
                sides: 8,
                rolls: [{ roll: 6, sides: 8 }],
                empty_slot: false,
              },
            ],
            ability_outcome: '7 damage dealt',
            actor_hp_after: 20,
            actor_max_hp: 20,
            target_hp_after: 3,
            target_max_hp: 10,
          },
          {
            type: 'action',
            round: 2,
            tick: 11,
            side: 'enemy',
            actor_enemy_slug: 'toad_shaman',
            target_unit_instance_id: 'u1',
            ability_id: 'sleep_hex',
            slot_traces: [],
            ability_outcome: 'sleep applied for 1 round and bleeding applied',
            actor_hp_after: 8,
            actor_max_hp: 12,
            target_hp_after: 8,
            target_max_hp: 20,
          },
        ],
        meta: {
          node_type: 'boss',
        },
      },
    },
    next: { unlocked_node_ids: ['n2'] },
  };

  beforeEach(() => {
    TestBed.configureTestingModule({});
  });

  it('creates a deterministic battle playback snapshot from the backend battle log', () => {
    const service = TestBed.inject(BattlePlaybackAdapterService);

    const snapshot = service.createSnapshot({
      runId: 'run-1',
      nodeId: 'n1',
      regionTheme: 'mountain',
      result,
      playerUnits,
      diceInventory: [{ id: 'd1', rarity: 'rare', sides: 8 }],
      abilityNames: new Map([
        ['heavy_strike', 'Heavy Strike'],
        ['sleep_hex', 'Sleep Hex'],
      ]),
    });

    expect(snapshot).not.toBeNull();
    expect(snapshot?.metadata).toEqual(
      jasmine.objectContaining({
        runId: 'run-1',
        nodeId: 'n1',
        battleId: 'b1',
        nodeType: 'boss',
        battleResult: 'victory',
        regionTheme: 'mountain',
      }),
    );
    expect(snapshot?.presentation.musicIntent).toBe('music.battle.boss');
    expect(snapshot?.participants.player.map((entry) => entry.name)).toEqual(['Ashback']);
    expect(snapshot?.participants.enemy.map((entry) => entry.name)).toEqual(['Goblin Raider', 'Toad Shaman']);
    expect(snapshot?.timeline).toEqual([
      jasmine.objectContaining({
        type: 'action',
        round: 1,
        tick: 4,
        side: 'player',
        abilityId: 'heavy_strike',
        abilityName: 'Heavy Strike',
        diceSummary: 'S1: rare d8 -> 6',
        resultSummary: '7 damage dealt',
        actor: jasmine.objectContaining({ participantId: 'u1', name: 'Ashback' }),
        target: jasmine.objectContaining({ participantId: 'enemy:goblin_raider', name: 'Goblin Raider' }),
      }),
      jasmine.objectContaining({
        type: 'action',
        round: 2,
        tick: 11,
        side: 'enemy',
        abilityId: 'sleep_hex',
        abilityName: 'Sleep Hex',
        diceSummary: 'No dice',
        actor: jasmine.objectContaining({ participantId: 'enemy:toad_shaman', name: 'Toad Shaman' }),
        target: jasmine.objectContaining({ participantId: 'u1', name: 'Ashback' }),
      }),
    ]);
    expect(snapshot?.timeline[1]?.resultSegments.some((segment) => segment.tooltip?.includes('Sleep prevents a unit'))).toBeTrue();
    expect(snapshot?.timeline[1]?.resultSegments.some((segment) => segment.tooltip?.includes('damage received'))).toBeTrue();
    expect(snapshot?.rewards).toEqual({
      nodeType: 'boss',
      xpTotal: 14,
      currencySoft: 5,
      newUnitLabels: ['Warcaller'],
      newDiceLabels: ['bone d6'],
    });
  });
});

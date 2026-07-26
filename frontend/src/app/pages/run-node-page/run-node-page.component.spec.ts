import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunNodePageComponent } from './run-node-page.component';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: {
      run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
      map: {
        nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'combat', status: 'available' }],
        edges: [],
      },
    },
  });
  resolveNode = jasmine.createSpy('resolveNode').and.resolveTo({
    ok: true,
    data: {
      node: { id: 'n1', status: 'cleared' },
      battle: {
        battle_id: 'b1',
        outcome: 'victory',
        rounds: 2,
        ticks: 12,
        status: 'completed',
        reward_preview: null,
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
              affix_outcome: 'explode triggered',
              actor_hp_after: 20,
              actor_max_hp: 20,
              target_hp_after: 3,
              target_max_hp: 10,
            },
            {
              type: 'action',
              round: 2,
              tick: 10,
              side: 'player',
              actor_unit_instance_id: 'u1',
              target_unit_instance_id: 'u2',
              ability_id: 'bolster_ally',
              slot_traces: [
                {
                  slot_index: 0,
                  dice_instance_id: 'd2',
                  sides: 4,
                  rolls: [{ roll: 2, sides: 4 }],
                  empty_slot: false,
                },
              ],
              ability_outcome: 'bolstered applied for 2 rounds',
              affix_outcome: null,
              actor_hp_after: 20,
              actor_max_hp: 20,
              target_hp_after: 13,
              target_max_hp: 18,
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
              affix_outcome: null,
              actor_hp_after: 8,
              actor_max_hp: 12,
              target_hp_after: 8,
              target_max_hp: 20,
            },
          ],
        },
      },
      next: { unlocked_node_ids: ['n2'] },
    },
  });
  generateChaosEncounter = jasmine.createSpy('generateChaosEncounter').and.resolveTo({
    ok: true,
    data: {
      chaos_result: {
        id: 'chaos-1',
        status: 'generated',
        seed: 1234,
        reels: [
          { reel_index: 0, reel: 'enemy_family', symbol: 'kobolds', label: 'Kobolds', weight: 30, risk: 2, effect: 'Trap-ready kobold pressure.' },
          { reel_index: 1, reel: 'encounter_shape', symbol: 'ambush', label: 'Ambush', weight: 20, risk: 3, effect: 'A dangerous opening position.' },
          { reel_index: 2, reel: 'rule_reward', symbol: 'guaranteed_loot', label: 'Guaranteed Loot', weight: 30, risk: 1, effect: 'Victory promises extra loot.' },
        ],
        reward_multiplier: 1.9,
        manipulation: { available: true, rerolled_reel_index: null, remaining: 1 },
        summary: {
          title: 'Kobolds + Ambush + Guaranteed Loot',
          effect: 'Trap-ready kobold pressure. A dangerous opening position. Victory promises extra loot.',
        },
      },
      run: { id: 'run-1', status: 'active' },
      node: { id: 'n1', node_type: 'chaos', status: 'available' },
    },
  });
  rerollChaosEncounter = jasmine.createSpy('rerollChaosEncounter').and.resolveTo({
    ok: true,
    data: {
      chaos_result: {
        id: 'chaos-1',
        status: 'manipulated',
        seed: 5678,
        reels: [
          { reel_index: 0, reel: 'enemy_family', symbol: 'frogmen', label: 'Frogmen', weight: 25, risk: 2, effect: 'Swamp attrition pressure.' },
          { reel_index: 1, reel: 'encounter_shape', symbol: 'ambush', label: 'Ambush', weight: 20, risk: 3, effect: 'A dangerous opening position.' },
          { reel_index: 2, reel: 'rule_reward', symbol: 'guaranteed_loot', label: 'Guaranteed Loot', weight: 30, risk: 1, effect: 'Victory promises extra loot.' },
        ],
        reward_multiplier: 1.9,
        manipulation: { available: false, rerolled_reel_index: 0, remaining: 0 },
        summary: {
          title: 'Frogmen + Ambush + Guaranteed Loot',
          effect: 'Swamp attrition pressure. A dangerous opening position. Victory promises extra loot.',
        },
      },
      run: { id: 'run-1', status: 'active' },
      node: { id: 'n1', node_type: 'chaos', status: 'available' },
    },
  });
  finalizeChaosEncounter = jasmine.createSpy('finalizeChaosEncounter').and.resolveTo({
    ok: true,
    data: {
      chaos_result: {
        id: 'chaos-1',
        status: 'confirmed',
        seed: 5678,
        reels: [
          { reel_index: 0, reel: 'enemy_family', symbol: 'frogmen', label: 'Frogmen', weight: 25, risk: 2, effect: 'Swamp attrition pressure.' },
          { reel_index: 1, reel: 'encounter_shape', symbol: 'ambush', label: 'Ambush', weight: 20, risk: 3, effect: 'A dangerous opening position.' },
          { reel_index: 2, reel: 'rule_reward', symbol: 'raw_chaos_spark', label: 'Raw Chaos Spark', weight: 20, risk: 2, effect: 'Victory can feed later chaos systems.' },
        ],
        reward_multiplier: 2.05,
        manipulation: { available: false, rerolled_reel_index: 0, remaining: 0 },
        summary: {
          title: 'Frogmen + Ambush + Raw Chaos Spark',
          effect: 'Swamp attrition pressure. A dangerous opening position. Victory can feed later chaos systems.',
        },
        finalized_rewards: {
          currency: { soft: 32, raw_chaos: 5 },
          reward_multiplier: 2.05,
          labels: ['32 Teeth', '5 Raw Chaos'],
        },
        finalized_at: '2026-07-25 12:00:00',
      },
      run: { id: 'run-1', status: 'active' },
      node: { id: 'n1', node_type: 'chaos', status: 'available' },
      completion: {
        title: 'Chaos Settled',
        message: 'Frogmen + Ambush + Raw Chaos Spark is locked in. The fight is ready.',
      },
      rewards: {
        currency: { soft: 32, raw_chaos: 5 },
        reward_multiplier: 2.05,
        labels: ['32 Teeth', '5 Raw Chaos'],
      },
      next: { unlocked_node_ids: [] },
    },
  });
  claimBattleRewards = jasmine.createSpy('claimBattleRewards').and.resolveTo({
    ok: true,
    data: { run_resolution: { status: 'active' } },
  });
}

class SessionServiceStub {
  readonly units = jasmine.createSpy('units').and.returnValue([
    { id: 'u1', name: 'Ashback', unit_type_name: 'Bruiser', level: 2, tier: 1, max_hp: 20, current_hp: 20, locked: true },
    { id: 'u2', name: 'Bogwort', unit_type_name: 'Bannerbearer', level: 2, tier: 1, max_hp: 18, current_hp: 13, locked: true },
  ]);
  readonly dice = jasmine.createSpy('dice').and.returnValue([
    { id: 'd1', rarity: 'rare', sides: 8 },
    { id: 'd2', rarity: 'uncommon', sides: 4 },
  ]);
  readonly profileData = jasmine.createSpy('profileData').and.returnValue({
    regions: [
      { id: 'region-1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-01T00:00:00Z' },
    ],
    region_unlocks: [{ region_id: 'region-1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' }],
  });
  readonly activeSquad = jasmine.createSpy('activeSquad').and.returnValue({
    id: 'team-1',
    name: 'Bogbreakers',
    is_active: true,
    unit_ids: ['u1', 'u2'],
    formation: [{ cell: 'A1', unit_instance_id: 'u1' }],
  });
  readonly session = jasmine.createSpy('session').and.returnValue({
    displayName: 'Commander',
  });
}

class AbilityCatalogServiceStub {
  readonly error = jasmine.createSpy('error').and.returnValue(null);
  readonly abilityMap = jasmine
    .createSpy('abilityMap')
    .and.returnValue(new Map([
      ['heavy_strike', { display_name: 'Heavy Strike' }],
      ['bolster_ally', { display_name: 'Bolster Ally' }],
      ['sleep_hex', { display_name: 'Sleep Hex' }],
    ]));
  load = jasmine.createSpy('load').and.resolveTo();
}

describe('RunNodePageComponent', () => {
  it('loads the active run id on startup', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    expect(fixture.componentInstance.runId()).toBe('run-1');
  });

  it('auto-resolves combat nodes on first load', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();

    const runService = TestBed.inject(RunService) as unknown as RunServiceStub;
    expect(runService.resolveNode).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(fixture.componentInstance.result()?.battle.battle_id).toBe('b1');
    expect(fixture.componentInstance.pageTitle()).toBe('BATTLE!');
    expect(fixture.componentInstance.pageSubtitle()).toBe(
      'Several goblins have volunteered to be an educational example.',
    );
  });

  it('uses an empty subtitle while a battle node is still loading', async () => {
    const deferredResolve = new Promise<any>(() => {});
    const runService = new RunServiceStub();
    runService.resolveNode.and.returnValue(deferredResolve);

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    fixture.detectChanges();
    await Promise.resolve();

    expect(fixture.componentInstance.pageTitle()).toBe('BATTLE!');
    expect(fixture.componentInstance.pageSubtitle()).toBe('');
  });

  it('routes back to the map after claiming non-terminal rewards', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    await fixture.componentInstance.claimRewards();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });

  it('redirects loot nodes to the loot node screen', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'loot', status: 'available' }],
          edges: [],
        },
      },
    });

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigate').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();

    expect(router.navigate).toHaveBeenCalledWith(['/run/loot', 'n1']);
    expect(runService.resolveNode).not.toHaveBeenCalled();
  });

  it('keeps shrine encounters on the node screen until manually resolved', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'mountains', region_theme: 'mountain' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'shrine', status: 'available' }],
          edges: [],
        },
      },
    });
    runService.resolveNode.and.resolveTo({
      ok: true,
      data: {
        node: { id: 'n1', status: 'completed' },
        battle: {
          battle_id: 'b-shrine',
          outcome: 'victory',
          rounds: 0,
          ticks: 0,
          status: 'completed',
          reward_preview: { node_type: 'shrine', xp_total: 0, currency_soft: 6, new_unit_labels: [], new_dice_labels: [], units: [], dice: [] },
          log: {
            meta: { node_type: 'shrine' },
            events: [{ type: 'node_effect', round: 0, tick: 0, node_type: 'shrine', message: 'shrine_favor_granted' }],
          },
        },
        next: { unlocked_node_ids: [] },
      },
    });

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(runService.resolveNode).not.toHaveBeenCalled();
    expect(fixture.componentInstance.pageTitle()).toBe('Shrine Encounter');
    expect(fixture.nativeElement.textContent).toContain('Approach Shrine');

    await fixture.componentInstance.resolveNode();
    fixture.detectChanges();

    expect(runService.resolveNode).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(fixture.componentInstance.pageSubtitle()).toBe(
      'The encounter is settled. Review the result, then claim what the run earned.',
    );
    expect(fixture.nativeElement.textContent).toContain('Shrine Node');
    expect(fixture.nativeElement.textContent).toContain('Shrine Result');
    expect(fixture.nativeElement.textContent).toContain('Shrine Favor Granted');
    expect(fixture.nativeElement.textContent).toContain('The path opened without a fight.');
    expect(fixture.nativeElement.textContent).toContain('The favor is ready to claim.');
    expect(fixture.nativeElement.textContent).toContain('Claim Favor');
    expect(fixture.nativeElement.querySelector('.node-result-layout')).not.toBeNull();
    expect((fixture.nativeElement.querySelector('.node-result-layout__art') as HTMLImageElement)?.getAttribute('src')).toBe(
      '/assets/ui/node-art/shrines/good_a.png',
    );
  });

  it('uses the non-combat reward layout for hazard results', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'swamps', region_theme: 'swamp' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'hazard', status: 'available' }],
          edges: [],
        },
      },
    });
    runService.resolveNode.and.resolveTo({
      ok: true,
      data: {
        node: { id: 'n1', status: 'completed' },
        battle: {
          battle_id: 'b-hazard',
          outcome: 'victory',
          rounds: 0,
          ticks: 0,
          status: 'completed',
          reward_preview: { node_type: 'hazard', xp_total: 0, currency_soft: 0, new_unit_labels: [], new_dice_labels: [], units: [], dice: [] },
          log: {
            meta: { node_type: 'hazard' },
            events: [{ type: 'node_effect', round: 0, tick: 0, node_type: 'hazard', message: 'hazard_avoided' }],
          },
        },
        next: { unlocked_node_ids: ['n2'] },
      },
    });

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    await fixture.componentInstance.resolveNode();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Hazard Node');
    expect(fixture.nativeElement.textContent).toContain('Hazard Result');
    expect(fixture.nativeElement.textContent).toContain('Continue Path');
    expect(fixture.nativeElement.textContent).toContain('Hazard Avoided');
    expect(fixture.nativeElement.textContent).toContain('The route is clear enough to continue.');
    expect(fixture.nativeElement.querySelector('.node-result-layout')).not.toBeNull();
  });

  it('shows generated chaos reels and resolves battle playback after finalization', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'swamps', region_theme: 'swamp' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'chaos', status: 'available' }],
          edges: [],
        },
      },
    });

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(runService.resolveNode).not.toHaveBeenCalled();
    expect(runService.generateChaosEncounter).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(fixture.componentInstance.pageTitle()).toBe('Chaos Encounter');
    expect(fixture.nativeElement.textContent).toContain('Kobolds + Ambush + Guaranteed Loot');
    expect(fixture.nativeElement.textContent).toContain('1.9x');
    expect(fixture.nativeElement.textContent).toContain('Reroll Reel');

    await fixture.componentInstance.rerollChaosReel(0);
    fixture.detectChanges();

    expect(runService.rerollChaosEncounter).toHaveBeenCalledOnceWith('run-1', 'n1', 0);
    expect(fixture.nativeElement.textContent).toContain('Frogmen + Ambush + Guaranteed Loot');
    expect(fixture.nativeElement.textContent).toContain('The reroll is spent.');

    await fixture.componentInstance.finalizeChaosEncounter();
    fixture.detectChanges();

    expect(runService.finalizeChaosEncounter).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(runService.resolveNode).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(fixture.componentInstance.chaosIsFinalized()).toBeTrue();
    expect(fixture.nativeElement.textContent).toContain('VICTORY');
    expect(fixture.nativeElement.textContent).toContain('Claim Rewards');
    expect(fixture.nativeElement.textContent).toContain('Acted Out');
    expect(fixture.nativeElement.querySelector('.battle-scene__viewport')).not.toBeNull();
  });

  it('formats battle action log details for the node screen', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.componentInstance.actionLog()).toEqual([
      jasmine.objectContaining({
        round: 1,
        tick: 4,
        actorName: 'Ashback',
        abilityName: 'Heavy Strike',
        targetName: 'Goblin Raider',
      }),
      jasmine.objectContaining({
        round: 2,
        tick: 10,
        actorName: 'Ashback',
        abilityName: 'Bolster Ally',
        targetName: 'Bogwort',
      }),
      jasmine.objectContaining({
        round: 2,
        tick: 11,
        actorName: 'Toad Shaman',
        abilityName: 'Sleep Hex',
        targetName: 'Ashback',
      }),
    ]);

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Claim Rewards');
    expect(host.textContent).toContain('VICTORY');
    expect(host.textContent).toContain('Acted Out');
    expect(host.textContent).toContain('Log View');
    expect(host.textContent).toContain('Ashback');
    expect(host.textContent).toContain('Heavy Strike');
    expect(host.textContent).toContain('Goblin Raider');
    expect(host.textContent).toContain('7 damage dealt');
    expect(host.querySelectorAll('.battle-playback__unit').length).toBeGreaterThan(0);
    expect(host.querySelector('.battle-scene__viewport')).not.toBeNull();
    expect(host.querySelector('.battle-hud__timeline .dg-proto-progress')).toBeNull();
    expect(fixture.componentInstance.battleSceneBackgroundImage()).toBe("url('/assets/ui/biome/farm.png')");
    expect((host.querySelector('.battle-scene') as HTMLElement).style.getPropertyValue('--battle-scene-background-image')).toBe(
      "url('/assets/ui/biome/farm.png')",
    );

    fixture.componentInstance.actionTransitioning.set(true);
    fixture.detectChanges();

    expect(host.querySelector('.battle-scene__flash')?.classList).toContain('is-transitioning');

    fixture.componentInstance.setBattleView('log');
    fixture.detectChanges();

    expect(host.textContent).toContain('Battle Log');
    expect(host.querySelector('.battle-scene__viewport')).toBeNull();
    expect(host.textContent).toContain('Bolster Ally');
    expect(host.textContent).toContain('Bogwort');
    expect(host.textContent).toContain('sleep applied for 1 round and bleeding applied');
    expect(host.textContent).not.toContain('explode triggered');

    const conditionChip = host.querySelector('.battle-log__condition[title*="defensive buff"]');
    expect(conditionChip?.textContent).toContain('bolstered');

    const bleedChip = host.querySelector('.battle-log__condition[title*="increases damage received"]');
    expect(bleedChip?.textContent).toContain('bleeding');
    expect(host.querySelector('button[dgcommandbtn], button[dgCommandBtn]')).not.toBeNull();
  });

  it('animates only the participant taking the current combat action', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance as any;
    component.combatAnimationFrameIndex.set(2);

    const actor = component.playerPlaybackParticipants().find((entry: any) => entry.participantId === 'u1');
    const idleAlly = component.playerPlaybackParticipants().find((entry: any) => entry.participantId === 'u2');

    expect(actor.isActor).toBeTrue();
    expect(idleAlly.isActor).toBeFalse();
    expect(component.combatSpriteUrl(actor)).toBe('/assets/ui/units/animated/goblin/base/bruiser/frame_2.png');
    expect(component.combatSpriteUrl(idleAlly)).toBe('/assets/ui/units/animated/goblin/base/bannerbearer/frame_0.png');
  });

});

import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunNodePageComponent } from './run-node-page.component';
import { DialogueScript } from '../../core/dialogue/dialogue.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

const FARM_BOSS_DIALOGUE: DialogueScript = {
  id: 'farm-boss-intro',
  backgroundUrl: '/assets/ui/biome/farm.png',
  startStepId: 'intro',
  speakers: [
    { id: 'mudking', side: 'right', name: 'Mudking', portraitUrl: '/assets/ui/units/pig_mudking.png', party: 'enemy', role: 'npc' },
    { id: 'player', side: 'left', name: 'Ashback', portraitUrl: '/assets/ui/units/goblin_bruiser.png', party: 'player', role: 'player' },
  ],
  steps: [
    { id: 'intro', speakerId: 'mudking', text: 'Are you here to fight me', nextStepId: 'answer', choices: [], enterEffect: null },
    {
      id: 'answer',
      speakerId: 'player',
      text: 'How do you answer?',
      nextStepId: null,
      enterEffect: null,
      choices: [{ id: 'yes', label: 'yes', nextStepId: 'yes-response' }],
    },
    { id: 'yes-response', speakerId: 'mudking', text: 'Good!', nextStepId: null, choices: [], enterEffect: null },
  ],
};

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

class DialogueServiceStub {
  getDialogue = jasmine.createSpy('getDialogue').and.resolveTo(null);
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
        { provide: DialogueService, useClass: DialogueServiceStub },
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
        { provide: DialogueService, useClass: DialogueServiceStub },
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
        { provide: DialogueService, useClass: DialogueServiceStub },
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
        { provide: DialogueService, useClass: DialogueServiceStub },
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
        { provide: DialogueService, useClass: DialogueServiceStub },
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

  it('formats battle action log details for the node screen', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        { provide: DialogueService, useClass: DialogueServiceStub },
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

    fixture.componentInstance.setBattleView('log');
    fixture.detectChanges();

    expect(host.textContent).toContain('Battle Log');
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

  it('shows dialogue before resolving the farm boss node', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
        ok: true,
        data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'boss', status: 'available' }],
          edges: [],
        },
      },
    });
    const dialogueService = new DialogueServiceStub();
    dialogueService.getDialogue.and.resolveTo(FARM_BOSS_DIALOGUE);

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        { provide: DialogueService, useValue: dialogueService },
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
    expect(dialogueService.getDialogue).toHaveBeenCalledWith(
      jasmine.objectContaining({
        scene: 'run-node',
        nodeType: 'boss',
        regionSlug: 'the_farm',
        tags: ['farm'],
        playerName: 'Commander',
        playerPortraitUrl: '/assets/dialogue/portraits/goblin/base_frame_0.png',
      }),
    );
    expect(fixture.componentInstance.dialogue()?.id).toBe('farm-boss-intro');

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Are you here to fight me');

    const continueSurface = host.querySelector('.dialogue-stage') as HTMLElement;
    continueSurface.click();
    fixture.detectChanges();

    const choiceButton = host.querySelector('.dialogue-stage__choice') as HTMLButtonElement;
    choiceButton.click();
    await fixture.whenStable();
    fixture.detectChanges();

    continueSurface.click();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(runService.resolveNode).toHaveBeenCalledOnceWith('run-1', 'n1');
    expect(fixture.componentInstance.result()?.battle.battle_id).toBe('b1');
  });

  it('passes the shop-unlocked tag for farm boss dialogue after the Tooth Collector is freed', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'boss', status: 'available' }],
          edges: [],
        },
      },
    });
    const dialogueService = new DialogueServiceStub();
    const sessionService = new SessionServiceStub();
    sessionService.profileData.and.returnValue({
      feature_unlocks: ['shop'],
      regions: [
        { id: 'region-1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
      ],
      region_unlocks: [{ region_id: 'region-1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' }],
    });

    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        { provide: SessionService, useValue: sessionService },
        { provide: AbilityCatalogService, useClass: AbilityCatalogServiceStub },
        { provide: DialogueService, useValue: dialogueService },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunNodePageComponent);
    await fixture.whenStable();

    expect(dialogueService.getDialogue).toHaveBeenCalledWith(
      jasmine.objectContaining({
        scene: 'run-node',
        nodeType: 'boss',
        regionSlug: 'the_farm',
        tags: ['farm', 'shop-unlocked'],
      }),
    );
  });
});

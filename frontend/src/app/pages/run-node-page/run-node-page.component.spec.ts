import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunNodePageComponent } from './run-node-page.component';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: { run: { run_id: 'run-1' } },
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
              target_hp_after: 3,
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
              target_hp_after: 13,
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
    { id: 'u1', name: 'Ashback' },
    { id: 'u2', name: 'Bogwort' },
  ]);
  readonly dice = jasmine.createSpy('dice').and.returnValue([
    { id: 'd1', rarity: 'rare', sides: 8 },
    { id: 'd2', rarity: 'uncommon', sides: 4 },
  ]);
}

class AbilityCatalogServiceStub {
  readonly error = jasmine.createSpy('error').and.returnValue(null);
  readonly abilityMap = jasmine
    .createSpy('abilityMap')
    .and.returnValue(new Map([
      ['heavy_strike', { display_name: 'Heavy Strike' }],
      ['bolster_ally', { display_name: 'Bolster Ally' }],
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
    await fixture.componentInstance.resolveNode();
    await fixture.componentInstance.claimRewards();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
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
    await fixture.componentInstance.resolveNode();
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
    ]);

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Battle Log');
    expect(host.textContent).toContain('Ashback');
    expect(host.textContent).toContain('Heavy Strike');
    expect(host.textContent).toContain('rare d8');
    expect(host.textContent).toContain('Goblin Raider');
    expect(host.textContent).toContain('7 damage dealt');
    expect(host.textContent).toContain('Bolster Ally');
    expect(host.textContent).toContain('Bogwort');
    expect(host.textContent).toContain('bolstered applied for 2 rounds');
  });
});

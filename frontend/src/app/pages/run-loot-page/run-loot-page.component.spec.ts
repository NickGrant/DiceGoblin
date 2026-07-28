import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunLootPageComponent } from './run-loot-page.component';
import { RunService } from '../../core/services/run/run.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: {
      run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
      map: {
        nodes: [{ id: '8', run_id: 'run-1', node_index: 0, node_type: 'loot', status: 'available', meta: { node_quality_tier: 'great' } }],
        edges: [],
      },
    },
  });
  resolveNode = jasmine.createSpy('resolveNode').and.resolveTo({
    ok: true,
    data: {
      node: { id: 'n1', status: 'cleared' },
      battle: {
        battle_id: 'b2',
        outcome: 'victory',
        rounds: 0,
        ticks: 0,
        status: 'completed',
        reward_preview: {
          node_type: 'loot',
          xp_total: 0,
          currency_soft: 5,
          new_unit_labels: ['Warcaller'],
          new_dice_labels: ['bone d6'],
          units: [
            {
              unit_instance_id: 'u2',
              name: 'Brindle',
              unit_type_slug: 'support_banner_t2',
              unit_type_name: 'Warcaller',
              splice_variant_slug: 'rat_splice',
              splice_variant_name: 'Rat-Spliced',
              tier: 2,
              level: 1,
              total_attack: 4,
              total_defense: 5,
              total_precision: 5,
              total_resolve: 7,
              max_hp: 22,
            },
          ],
          dice: [
            {
              dice_instance_id: 'd2',
              label: 'bone d6',
              rarity: 'rare',
              material: 'bone',
              sides: 6,
              affixes: [
                {
                  affix_definition_id: 'affix-guard',
                  affix_slug: 'guard',
                  name: 'Guard',
                  rarity: 'rare',
                  kind: 'triggered',
                  description: 'Adds defense.',
                  value: 1,
                },
              ],
            },
          ],
        },
        log: {
          meta: { node_type: 'loot' },
          events: [],
        },
      },
      next: { unlocked_node_ids: ['n2', 'n3'] },
    },
  });
  claimBattleRewards = jasmine.createSpy('claimBattleRewards').and.resolveTo({
    ok: true,
    data: { run_resolution: { status: 'active' } },
  });
}

describe('RunLootPageComponent', () => {
  it('shows a treasure-focused reward summary for loot nodes', async () => {
    await TestBed.configureTestingModule({
      imports: [RunLootPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: '8' }) } } },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunLootPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.componentInstance.lootRewards()).toEqual({
      teeth: 5,
      dice: [
        {
          dice_instance_id: 'd2',
          label: 'bone d6',
          rarity: 'rare',
          material: 'bone',
          sides: 6,
          affixes: [
            {
              affix_definition_id: 'affix-guard',
              affix_slug: 'guard',
              name: 'Guard',
              rarity: 'rare',
              kind: 'triggered',
              description: 'Adds defense.',
              value: 1,
            },
          ],
        },
      ],
      units: [
        {
          unit_instance_id: 'u2',
          name: 'Brindle',
          unit_type_slug: 'support_banner_t2',
          unit_type_name: 'Warcaller',
          splice_variant_slug: 'rat_splice',
          splice_variant_name: 'Rat-Spliced',
          tier: 2,
          level: 1,
          total_attack: 4,
          total_defense: 5,
          total_precision: 5,
          total_resolve: 7,
          max_hp: 22,
        },
      ],
    });
    expect(fixture.componentInstance.pageTitle()).toBe('A respectable acquisition of wealth');
    expect(fixture.componentInstance.pageSubtitle()).toBe(
      'No heroism required, just strong knees and stronger pockets.',
    );

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Claim Treasure');
    expect(host.textContent).toContain('Treasure Found');
    expect(host.textContent).toContain('teeth');
    expect(host.textContent).toContain('Brindle');
    expect(host.textContent).toContain('Warcaller');
    expect(host.textContent).toContain('Rat Kin');
    expect(host.textContent).toContain('PRC');
    expect(host.textContent).toContain('RES');
    expect(host.textContent).toContain('Guard');
    expect(host.textContent).not.toContain('Battle Log');
    expect(host.textContent).not.toContain('bone d6');
    expect(host.querySelector('.loot-scene__main')).not.toBeNull();
    expect(host.querySelector('.loot-scene__rail')).not.toBeNull();
    expect(host.querySelector('.loot-scene__summary')?.textContent).toContain('5');
    expect(host.querySelector('.loot-scene__summary')?.textContent).toContain('teeth');
    expect(host.querySelector('.loot-scene__summary')?.textContent).toContain('dice');
    expect(host.querySelector('.loot-scene__summary')?.textContent).toContain('units');
    expect((host.querySelector('.loot-scene__art') as HTMLImageElement)?.getAttribute('src')).toBe('/assets/ui/node-art/loot/great_b.png');

    const diceImage = host.querySelector('.loot-card--dice img') as HTMLImageElement;
    expect(diceImage?.getAttribute('src')).toBe('/assets/ui/dice/bone_d6.png');
    expect(diceImage?.getAttribute('alt')).toBe('bone d6');
  });

  it('routes back to the map after claiming non-terminal rewards', async () => {
    await TestBed.configureTestingModule({
      imports: [RunLootPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunLootPageComponent);
    await fixture.whenStable();
    await fixture.componentInstance.claimRewards();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });

  it('redirects non-loot nodes back to the combat node screen', async () => {
    const runService = new RunServiceStub();
    runService.getCurrentRun.and.resolveTo({
      ok: true,
      data: {
        run: { run_id: 'run-1', region_id: 'region-1', region_slug: 'the_farm', region_theme: 'farm' },
        map: {
          nodes: [{ id: 'n1', run_id: 'run-1', node_index: 0, node_type: 'combat', status: 'available' }],
          edges: [],
        },
      },
    });

    await TestBed.configureTestingModule({
      imports: [RunLootPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useValue: runService },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigate').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunLootPageComponent);
    await fixture.whenStable();

    expect(router.navigate).toHaveBeenCalledWith(['/run/node', 'n1']);
    expect(runService.resolveNode).not.toHaveBeenCalled();
  });
});

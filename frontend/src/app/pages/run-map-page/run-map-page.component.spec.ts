import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RunMapPageComponent } from './run-map-page.component';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: {
      run: { run_id: 'run-1', region_id: '1', region_slug: 'the_farm', region_theme: 'farm', status: 'active' },
      map: {
        nodes: [
          { id: 'n1', node_index: 0, node_type: 'combat', status: 'available' },
          { id: '8', node_index: 1, node_type: 'exit', status: 'locked' },
        ],
        edges: [],
      },
      run_unit_state: [
        { unit_instance_id: 'u1', current_hp: 6, is_defeated: false, status_effects: [] },
        { unit_instance_id: 'u2', current_hp: 0, is_defeated: true, status_effects: [] },
      ],
      active_run_effects: [
        {
          id: 'battle-1-shrine',
          node_id: 'n3',
          node_type: 'shrine',
          label: 'Shrine Favor Granted',
          detail: 'Bone Whisper grants 7 teeth.',
          persistence: 'immediate',
          source: 'Shrine',
        },
      ],
    },
  });
  abandonRun = jasmine.createSpy('abandonRun').and.resolveTo({ ok: true });
  exitRun = jasmine.createSpy('exitRun').and.resolveTo({ ok: true });
  healRunUnit = jasmine.createSpy('healRunUnit').and.resolveTo({
    ok: true,
    data: {
      run_id: 'run-1',
      unit_instance_id: 'u1',
      item: { item_slug: 'field_poultice', quantity: 0, spent_quantity: 1 },
      healing: { amount: 4, hp_before: 6, hp_after: 10, max_hp: 10, is_defeated: false },
    },
  });
}

class SessionServiceStub {
  readonly profileData = () =>
    ({
      active_run: { run_id: 'run-1', region_id: '1', region_slug: 'the_farm', region_theme: 'farm' },
      items: [
        {
          item_id: 'i1',
          item_slug: 'field_poultice',
          name: 'Field Poultice',
          description: 'Patches up one unit.',
          category: 'consumable',
          quantity: 1,
          rarity: 'common',
          source_region_slug: null,
          source_region_name: null,
          source_family_slug: null,
          icon_key: 'item_field_poultice',
          lore_key: 'healing_consumable',
          is_visible_before_discovery: true,
          is_spendable: true,
          is_primary_progression: false,
          meta: { effect: { type: 'heal_run_unit_hp', amount: 10 } },
        },
      ],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-01T00:00:00Z' },
      ],
      region_unlocks: [{ region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' }],
    }) as any;
  readonly units = () => [
    { id: 'u1', name: 'Fang', unit_type_name: 'Bruiser', level: 3, tier: 2, max_hp: 10 },
    { id: 'u2', name: 'Moss', unit_type_name: 'Guardian', level: 4, tier: 1, max_hp: 12 },
  ] as any[];
  readonly activeSquad = () =>
    ({
      id: 's1',
      name: 'Alpha',
      is_active: true,
      unit_ids: ['u1', 'u2'],
      formation: [
        { cell: 'A1', unit_instance_id: 'u1' },
        { cell: 'B2', unit_instance_id: 'u2' },
      ],
    }) as any;
}

describe('RunMapPageComponent', () => {
  afterEach(() => {
    delete window.__DICE_GOBLIN_CONFIG__;
  });

  it('loads current run data and can continue', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.componentInstance.run()?.run_id).toBe('run-1');
    expect(fixture.componentInstance.loading()).toBeFalse();
    expect(fixture.componentInstance.pageTitle()).toBe('Continue Run - The Farm');
    expect(fixture.componentInstance.iconForNodeType('combat')).toContain('icon_encounter_combat.png');
    expect(fixture.componentInstance.iconForNodeType('hazard')).toContain('icon_encounter_locked.png');
    expect(fixture.componentInstance.iconForNode({
      id: '12',
      run_id: 'run-1',
      node_index: 2,
      node_type: 'shrine',
      status: 'locked',
      meta: { node_quality_tier: 'great' },
    })).toContain('/assets/ui/node-art/shrines/great_b.png');
    expect(fixture.componentInstance.iconForNodeType('exit')).toContain('icon_home.png');
    expect(fixture.componentInstance.mapBackgroundUrl()).toBe('/assets/ui/biome/farm.png');
    expect(fixture.componentInstance.nodeX(fixture.componentInstance.nodes()[1]!)).toBe(260);
    expect(fixture.componentInstance.mapWidth()).toBeGreaterThan(
      fixture.componentInstance.nodeX(fixture.componentInstance.nodes()[1]!) + 34,
    );
    expect(fixture.componentInstance.formationGrid().length).toBe(9);
    expect(fixture.componentInstance.formationGrid().find((cell) => cell.cell === 'A1')?.entry?.currentHp).toBe(6);
    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Continue Run - The Farm');
    expect(host.textContent).toContain('Fang');
    expect(host.textContent).toContain('Run Supplies');
    expect(host.textContent).toContain('Field Poultice (1)');
    expect(host.textContent).toContain('Lv 3');
    expect(host.textContent).toContain('Run Effects');
    expect(host.textContent).toContain('Shrine Favor Granted');
    expect(host.textContent).toContain('Bone Whisper grants 7 teeth.');
    expect(host.querySelector('.run-unit-grid .unit-thumbnail')).not.toBeNull();
    expect(host.querySelector('.run-unit-grid .unit-thumbnail__hp')).not.toBeNull();
    expect(host.querySelector('.run-unit-grid .unit-bar')).toBeNull();
    expect(host.querySelector('.run-unit-grid a')?.getAttribute('href')).toContain('/warband/units/u1');
    expect(host.querySelector('.run-map__node-halo')).not.toBeNull();
    expect(host.querySelector('[data-node-type="combat"] .run-map__node-disc')).not.toBeNull();
  });

  it('uses healing consumables from the run supplies panel', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const runService = TestBed.inject(RunService) as unknown as RunServiceStub;
    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    await fixture.componentInstance.healUnit('u1', 'field_poultice');
    fixture.detectChanges();

    expect(runService.healRunUnit).toHaveBeenCalledWith('run-1', 'u1', 'field_poultice');
    expect(fixture.componentInstance.runUnits()[0].currentHp).toBe(10);
    expect(fixture.nativeElement.textContent).toContain('Fang healed to 10/10.');
  });

  it('sizes the map from rendered node positions instead of a separate node-index guess', async () => {
    class WideRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: { run_id: 'run-2', region_id: '1', region_slug: 'the_farm', region_theme: 'farm', status: 'active' },
          map: {
            nodes: [
              { id: 'n1', node_index: 0, node_type: 'combat', status: 'cleared', meta: { col: 0, row: 1 } },
              { id: 'n2', node_index: 9, node_type: 'exit', status: 'available', meta: { col: 7, row: 1 } },
            ],
            edges: [],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: WideRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const exitNode = component.nodes().find((node) => node.id === 'n2');
    expect(exitNode).toBeTruthy();
    expect(component.nodeX(exitNode!)).toBe(1100);
    expect(component.mapWidth()).toBe(1254);
  });

  it('fans branch and merge paths apart so overlapping routes stay readable', async () => {
    class BranchingRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: { run_id: 'run-3', region_id: '2', region_slug: 'mountains', region_theme: 'mountain', status: 'active' },
          map: {
            nodes: [
              { id: 'n1', run_id: 'run-3', node_index: 0, node_type: 'combat', status: 'available', meta: { col: 0, row: 1 } },
              { id: 'n2', run_id: 'run-3', node_index: 1, node_type: 'loot', status: 'locked', meta: { col: 1, row: 0 } },
              { id: 'n3', run_id: 'run-3', node_index: 2, node_type: 'rest', status: 'locked', meta: { col: 1, row: 2 } },
              { id: 'n4', run_id: 'run-3', node_index: 3, node_type: 'boss', status: 'locked', meta: { col: 2, row: 1 } },
              { id: 'n5', run_id: 'run-3', node_index: 4, node_type: 'exit', status: 'locked', meta: { col: 3, row: 1 } },
            ],
            edges: [
              { edge_id: 'e1', run_id: 'run-3', from_node_id: 'n1', to_node_id: 'n2' },
              { edge_id: 'e2', run_id: 'run-3', from_node_id: 'n1', to_node_id: 'n3' },
              { edge_id: 'e3', run_id: 'run-3', from_node_id: 'n2', to_node_id: 'n4' },
              { edge_id: 'e4', run_id: 'run-3', from_node_id: 'n3', to_node_id: 'n4' },
              { edge_id: 'e5', run_id: 'run-3', from_node_id: 'n4', to_node_id: 'n5' },
            ],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: BranchingRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const renderedEdges = component.renderedEdges();

    expect(renderedEdges.find((edge) => edge.edgeId === 'e1')?.path).toBe('M 120 222 C 169 213, 211 90, 260 90');
    expect(renderedEdges.find((edge) => edge.edgeId === 'e2')?.path).toBe('M 120 222 C 169 231, 211 354, 260 354');
    expect(renderedEdges.find((edge) => edge.edgeId === 'e3')?.path).toBe('M 260 90 C 309 90, 351 213, 400 222');
    expect(renderedEdges.find((edge) => edge.edgeId === 'e4')?.path).toBe('M 260 354 C 309 354, 351 231, 400 222');
    expect(renderedEdges.find((edge) => edge.edgeId === 'e5')?.path).toBe('M 400 222 L 540 222');
  });

  it('renders stored connector waypoints as edge segments', async () => {
    class WaypointRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: { run_id: 'run-waypoints', region_id: '2', region_slug: 'mountains', region_theme: 'mountain', status: 'active' },
          map: {
            nodes: [
              { id: 'n1', run_id: 'run-waypoints', node_index: 0, node_type: 'combat', status: 'cleared', meta: { col: 0, row: 1 } },
              { id: 'n2', run_id: 'run-waypoints', node_index: 1, node_type: 'loot', status: 'available', meta: { col: 2, row: 1 } },
            ],
            edges: [
              {
                edge_id: 'waypoint-edge',
                run_id: 'run-waypoints',
                from_node_id: 'n1',
                to_node_id: 'n2',
                meta: { through: [{ x: 1, y: 0 }] },
              },
            ],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: WaypointRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const edge = fixture.componentInstance.renderedEdges().find((candidate) => candidate.edgeId === 'waypoint-edge');
    expect(edge?.path).toBe('M 120 222 L 260 90 L 400 222');
  });

  it('colors run-map edges by directed progression instead of either endpoint availability', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    const component = fixture.componentInstance;
    component.runData.set({
      run: { run_id: 'run-directed', region_id: '2', region_slug: 'mountains', region_theme: 'mountain', status: 'active' },
      map: {
        nodes: [
          { id: 'cleared', run_id: 'run-directed', node_index: 0, node_type: 'combat', status: 'cleared', meta: { col: 0, row: 1 } },
          { id: 'locked-parent', run_id: 'run-directed', node_index: 1, node_type: 'combat', status: 'locked', meta: { col: 0, row: 2 } },
          { id: 'available-rejoin', run_id: 'run-directed', node_index: 2, node_type: 'rest', status: 'available', meta: { col: 1, row: 1 } },
          { id: 'cleared-rejoin', run_id: 'run-directed', node_index: 3, node_type: 'rest', status: 'cleared', meta: { col: 2, row: 1 } },
        ],
        edges: [],
      },
      run_unit_state: [],
    } as any);

    expect(component.edgeState({ run_id: 'run-directed', from_node_id: 'cleared', to_node_id: 'available-rejoin' })).toBe('available');
    expect(component.edgeState({ run_id: 'run-directed', from_node_id: 'locked-parent', to_node_id: 'available-rejoin' })).toBe('locked');
    expect(component.edgeState({ run_id: 'run-directed', from_node_id: 'cleared', to_node_id: 'cleared-rejoin' })).toBe('cleared');
  });

  it('pushes long-jump side lanes farther from the center row', async () => {
    class LongJumpRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: { run_id: 'run-4', region_id: '2', region_slug: 'mountains', region_theme: 'mountain', status: 'active' },
          map: {
            nodes: [
              { id: 'n1', run_id: 'run-4', node_index: 0, node_type: 'combat', status: 'available', meta: { col: 0, row: 1 } },
              { id: 'n2', run_id: 'run-4', node_index: 1, node_type: 'loot', status: 'locked', meta: { col: 1, row: 0 } },
              { id: 'n3', run_id: 'run-4', node_index: 2, node_type: 'boss', status: 'locked', meta: { col: 4, row: 1 } },
              { id: 'n4', run_id: 'run-4', node_index: 3, node_type: 'exit', status: 'locked', meta: { col: 5, row: 1 } },
            ],
            edges: [
              { edge_id: 'e1', run_id: 'run-4', from_node_id: 'n1', to_node_id: 'n2' },
              { edge_id: 'e2', run_id: 'run-4', from_node_id: 'n2', to_node_id: 'n3' },
              { edge_id: 'e3', run_id: 'run-4', from_node_id: 'n3', to_node_id: 'n4' },
            ],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: LongJumpRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const topNode = component.nodes().find((node) => node.id === 'n2');
    const longJumpEdge = component.renderedEdges().find((edge) => edge.edgeId === 'e2');

    expect(topNode).toBeTruthy();
    expect(component.nodeY(topNode!)).toBe(54);
    expect(longJumpEdge?.path).toBe('M 260 54 C 344 54, 596 222, 680 222');
  });

  it('shows pattern generation metadata when a generated run includes provenance', async () => {
    window.__DICE_GOBLIN_CONFIG__ = { enableDevPanel: true };

    class PatternRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: {
            run_id: 'run-5',
            region_id: '2',
            region_slug: 'mountains',
            region_theme: 'mountain',
            status: 'active',
            generator_version: 'pattern-v1',
            generation_profile_version: 1,
            pattern_catalog_hash: 'abcdef1234567890',
            generation_attempt: 0,
            generation_summary: {
              node_count: 5,
              branch_count: 1,
              spine_depth: 3,
              boss_path: { start_to_boss: 2, boss_to_exit: 1 },
            },
          },
          map: {
            nodes: [
              {
                id: 'n1',
                run_id: 'run-5',
                node_index: 0,
                node_type: 'combat',
                status: 'available',
                meta: {
                  col: 0,
                  row: 1,
                  generation: {
                    path_role: 'spine',
                    depth: 0,
                    pattern_key: 'shared_start_single@1',
                  },
                },
              },
              {
                id: 'n2',
                run_id: 'run-5',
                node_index: 1,
                node_type: 'boss',
                status: 'locked',
                meta: {
                  col: 1,
                  row: 1,
                  generation: {
                    path_role: 'spine',
                    depth: 2,
                    pattern_key: 'shared_boss_exit_terminal@1',
                  },
                },
              },
            ],
            edges: [{ edge_id: 'e1', run_id: 'run-5', from_node_id: 'n1', to_node_id: 'n2' }],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: PatternRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Generation');
    expect(host.textContent).toContain('pattern-v1');
    expect(host.textContent).toContain('2 to boss, 1 to exit');
    expect(host.textContent).toContain('Combat · spine · depth 0');
    expect(host.textContent).toContain('shared_boss_exit_terminal@1');
    expect(host.querySelector('.run-map__pattern-label')?.textContent?.trim()).toBe('0');
    expect(host.querySelectorAll('.run-map__debug-grid-line').length).toBeGreaterThan(0);
    expect(host.textContent).toContain('x0');
    expect(host.textContent).toContain('y1');
  });

  it('uses generation coordinates without showing debug metadata when dev panel is disabled', async () => {
    class FixedRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: {
            run_id: 'run-fixed',
            region_id: '1',
            region_slug: 'the_farm',
            region_theme: 'farm',
            status: 'active',
            generator_version: 'fixed-v1',
            generation_profile_version: 1,
            pattern_catalog_hash: 'fixedhash',
            generation_attempt: 0,
            generation_summary: {
              generator_version: 'fixed-v1',
              node_count: 2,
              occupied_columns: 6,
            },
          },
          map: {
            nodes: [
              {
                id: 'n1',
                run_id: 'run-fixed',
                node_index: 0,
                node_type: 'combat',
                status: 'available',
                meta: {
                  col: 0,
                  row: 1,
                  generation: {
                    generator_version: 'fixed-v1',
                    x: 4,
                    y: 2,
                    depth: 4,
                    path_role: 'spine',
                    pattern_key: 'the_farm_fixed@1',
                  },
                },
              },
            ],
            edges: [],
          },
          run_unit_state: [],
        },
      });
    }

    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: FixedRunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const node = component.nodes()[0]!;
    expect(component.nodeX(node)).toBe(680);
    expect(component.nodeY(node)).toBe(354);
    expect(component.patternDebugRows()).toEqual([]);
    expect(component.patternNodeRows()).toEqual([]);
    expect(component.patternNodeDepthLabel(node)).toBeNull();
    expect(fixture.nativeElement.textContent).not.toContain('Generation');
    expect(fixture.nativeElement.querySelector('.run-map__pattern-label')).toBeNull();
    expect(fixture.nativeElement.querySelector('.run-map__debug-grid')).toBeNull();
  });

  it('navigates to summary after abandoning a run', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();

    await fixture.componentInstance.abandonRun();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/summary');
  });

  it('opens available loot nodes on the loot route', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigate').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();

    await fixture.componentInstance.openNode({
      id: 'loot-1',
      run_id: 'run-1',
      node_index: 1,
      node_type: 'loot',
      status: 'available',
    });

    expect(router.navigate).toHaveBeenCalledWith(['/run/loot', 'loot-1']);
  });
});

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
          { id: 'n2', node_index: 1, node_type: 'exit', status: 'locked' },
        ],
        edges: [],
      },
      run_unit_state: [
        { unit_instance_id: 'u1', current_hp: 6, is_defeated: false, status_effects: [] },
        { unit_instance_id: 'u2', current_hp: 0, is_defeated: true, status_effects: [] },
      ],
    },
  });
  abandonRun = jasmine.createSpy('abandonRun').and.resolveTo({ ok: true });
  exitRun = jasmine.createSpy('exitRun').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly profileData = () =>
    ({
      active_run: { run_id: 'run-1', region_id: '1', region_slug: 'the_farm', region_theme: 'farm' },
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
    expect(fixture.componentInstance.iconForNodeType('shrine')).toContain('/assets/ui/node-art/shrines/good_a.png');
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
    expect(host.textContent).toContain('Lv 3');
    expect(host.querySelector('.run-unit-grid .unit-thumbnail')).not.toBeNull();
    expect(host.querySelector('.run-unit-grid .unit-thumbnail__hp')).not.toBeNull();
    expect(host.querySelector('.run-unit-grid .unit-bar')).toBeNull();
    expect(host.querySelector('.run-unit-grid a')?.getAttribute('href')).toContain('/warband/units/u1');
    expect(host.querySelector('.run-map__node-halo')).not.toBeNull();
    expect(host.querySelector('[data-node-type="combat"] .run-map__node-disc')).not.toBeNull();
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

import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RunMapPageComponent } from './run-map-page.component';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: {
      run: { run_id: 'run-1', region_id: '1', status: 'active' },
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
      active_run: { run_id: 'run-1', region_id: '1' },
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
    expect(fixture.componentInstance.iconForNodeType('combat')).toContain('icon_encounter_combat.png');
    expect(fixture.componentInstance.iconForNodeType('exit')).toContain('icon_home.png');
    expect(fixture.componentInstance.mapBackgroundUrl()).toBe('/assets/ui/biome/farm.png');
    expect(fixture.componentInstance.nodeX(fixture.componentInstance.nodes()[1]!)).toBe(260);
    expect(fixture.componentInstance.mapWidth()).toBeGreaterThan(
      fixture.componentInstance.nodeX(fixture.componentInstance.nodes()[1]!) + 34,
    );
    expect(fixture.componentInstance.formationGrid().length).toBe(9);
    expect(fixture.componentInstance.formationGrid().find((cell) => cell.cell === 'A1')?.entry?.currentHp).toBe(6);
    const host: HTMLElement = fixture.nativeElement;
    expect(host.textContent).toContain('Bruiser');
    expect(host.textContent).toContain('Level 3');
    expect(host.textContent).toContain('II');
    expect(host.textContent).not.toContain('A1');
    expect(host.textContent).not.toContain('HP 6/10');
  });

  it('sizes the map from rendered node positions instead of a separate node-index guess', async () => {
    class WideRunServiceStub extends RunServiceStub {
      override getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
        ok: true,
        data: {
          run: { run_id: 'run-2', region_id: '1', status: 'active' },
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
});

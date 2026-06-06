import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { RunRestPageComponent } from './run-rest-page.component';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: { run: { run_id: 'run-1' } },
  });
  openRest = jasmine.createSpy('openRest').and.resolveTo({
    ok: true,
    data: {
      run_id: 'run-1',
      node_id: 'n1',
      status: 'open',
      run_unit_state: [
        { unit_instance_id: 'u1', current_hp: 6, is_defeated: false, status_effects: [] },
        { unit_instance_id: 'u2', current_hp: 0, is_defeated: true, status_effects: [] },
      ],
    },
  });
  finalizeRest = jasmine.createSpy('finalizeRest').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly units = signal([
    { id: 'u1', name: 'Fang', unit_type_name: 'Bruiser', max_hp: 10 },
    { id: 'u2', name: 'Moss', unit_type_name: 'Guardian', max_hp: 12 },
  ] as any[]);
  readonly activeSquad = signal({
    id: 's1',
    name: 'Alpha',
    is_active: true,
    unit_ids: ['u1', 'u2'],
    formation: [
      { cell: 'A1', unit_instance_id: 'u1' },
      { cell: 'B2', unit_instance_id: 'u2' },
    ],
  } as any);
}

describe('RunRestPageComponent', () => {
  async function createComponent() {
    await TestBed.configureTestingModule({
      imports: [RunRestPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunRestPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture;
  }

  it('bootstraps rest recovery data on startup', async () => {
    const fixture = await createComponent();

    expect(fixture.componentInstance.runId()).toBe('run-1');
    expect(fixture.componentInstance.loading()).toBeFalse();
    expect(fixture.componentInstance.restingUnits()[0].unit?.name).toBe('Fang');
    expect(fixture.componentInstance.restingUnits()[0].currentHp).toBe(6);
    expect(fixture.componentInstance.restingUnits()[1].defeated).toBeTrue();
    expect(fixture.componentInstance.formationGrid().length).toBe(9);
    expect(fixture.componentInstance.formationGrid().find((cell) => cell.cell === 'A2')?.entry).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('HP 6/10');
    expect(fixture.nativeElement.textContent).toContain('Defeated');
    expect(fixture.nativeElement.textContent).toContain('Empty');
  });

  it('navigates back to the map after resting', async () => {
    const fixture = await createComponent();
    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);

    await fixture.componentInstance.finalizeRest();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });
});

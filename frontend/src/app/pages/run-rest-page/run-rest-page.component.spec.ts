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
      run_unit_state: [{ unit_instance_id: 'u1', hp: 6, is_defeated: false, status_effects: [] }],
    },
  });
  finalizeRest = jasmine.createSpy('finalizeRest').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly units = signal([{ id: 'u1', name: 'Fang', unit_type_name: 'Bruiser' }] as any[]);
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
    expect(fixture.nativeElement.textContent).toContain('Current HP 6');
  });

  it('navigates back to the map after resting', async () => {
    const fixture = await createComponent();
    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);

    await fixture.componentInstance.finalizeRest();

    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });
});

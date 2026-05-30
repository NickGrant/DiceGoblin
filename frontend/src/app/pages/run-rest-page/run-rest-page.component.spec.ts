import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RestService } from '../../core/services/rest/rest.service';
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
    data: { unit_ids: ['u1'], formation: [] },
  });
  updateRestState = jasmine.createSpy('updateRestState').and.resolveTo({ ok: true, data: { unit_ids: ['u1'], formation: [] } });
  finalizeRest = jasmine.createSpy('finalizeRest').and.resolveTo({ ok: true });
}

class RestServiceStub {
  purchaseStoreItem = jasmine.createSpy('purchaseStoreItem').and.resolveTo({ ok: true });
}

class SessionServiceStub {
  readonly units = signal([{ id: 'u1', name: 'Fang' }] as any[]);
  refreshProfile = jasmine.createSpy('refreshProfile').and.resolveTo();
}

describe('RunRestPageComponent', () => {
  it('bootstraps rest data on startup', async () => {
    await TestBed.configureTestingModule({
      imports: [RunRestPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: RestService, useClass: RestServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunRestPageComponent);
    await fixture.whenStable();
    expect(fixture.componentInstance.runId()).toBe('run-1');
    expect(fixture.componentInstance.loading()).toBeFalse();
  });

  it('navigates back to the map after finalizing rest', async () => {
    await TestBed.configureTestingModule({
      imports: [RunRestPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: RestService, useClass: RestServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ nodeId: 'n1' }) } },
        },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RunRestPageComponent);
    await fixture.whenStable();

    await fixture.componentInstance.finalizeRest();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });
});

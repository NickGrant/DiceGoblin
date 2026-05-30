import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter, Router } from '@angular/router';
import { RunNodePageComponent } from './run-node-page.component';
import { RunService } from '../../core/services/run/run.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: { run: { run_id: 'run-1' } },
  });
  resolveNode = jasmine.createSpy('resolveNode').and.resolveTo({
    ok: true,
    data: { battle: { battle_id: 'b1' } },
  });
  claimBattleRewards = jasmine.createSpy('claimBattleRewards').and.resolveTo({
    ok: true,
    data: { run_resolution: { status: 'active' } },
  });
}

describe('RunNodePageComponent', () => {
  it('loads the active run id on startup', async () => {
    await TestBed.configureTestingModule({
      imports: [RunNodePageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
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
});

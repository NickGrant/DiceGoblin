import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RunMapPageComponent } from './run-map-page.component';
import { RunService } from '../../core/services/run/run.service';

class RunServiceStub {
  getCurrentRun = jasmine.createSpy('getCurrentRun').and.resolveTo({
    ok: true,
    data: {
      run: { run_id: 'run-1' },
      map: { nodes: [], edges: [] },
    },
  });
  abandonRun = jasmine.createSpy('abandonRun').and.resolveTo({ ok: true });
  exitRun = jasmine.createSpy('exitRun').and.resolveTo({ ok: true });
}

describe('RunMapPageComponent', () => {
  it('loads current run data and can continue', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunMapPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.componentInstance.run()?.run_id).toBe('run-1');
    expect(fixture.componentInstance.loading()).toBeFalse();
  });

  it('navigates to summary after abandoning a run', async () => {
    await TestBed.configureTestingModule({
      imports: [RunMapPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
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

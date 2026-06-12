import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RunSummaryPageComponent } from './run-summary-page.component';
import { RunService } from '../../core/services/run/run.service';

class RunServiceStub {
  readonly summary = signal({
    title: 'Run Complete',
    status: 'boss_cleared',
    rewards: ['Gold Tooth'],
    progression: ['Unlocked Region 2'],
    survivors: ['Fang'],
    defeated: ['Muck'],
  });
}

describe('RunSummaryPageComponent', () => {
  it('renders run summary data from the run service', async () => {
    await TestBed.configureTestingModule({
      imports: [RunSummaryPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunSummaryPageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Run Complete');
    expect(compiled.textContent).toContain('Boss Cleared');
    expect(compiled.textContent).toContain('Gold Tooth');
    expect(compiled.textContent).toContain('Unlocked Region 2');
    expect(compiled.textContent).toContain('Fang');
    expect(compiled.textContent).toContain('Muck');
    expect(compiled.textContent).toContain('Return Home');
  });
});

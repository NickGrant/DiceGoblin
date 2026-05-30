import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RunSummaryPageComponent } from './run-summary-page.component';
import { RunService } from '../../core/services/run/run.service';

class RunServiceStub {
  readonly summary = signal({
    title: 'Run Complete',
    status: 'complete',
    rewards: ['Gold Tooth'],
    progression: [],
    survivors: ['Fang'],
    defeated: [],
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
    expect(compiled.textContent).toContain('Gold Tooth');
    expect(compiled.textContent).toContain('Fang');
  });
});

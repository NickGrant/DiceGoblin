import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RunSummaryPageComponent } from './run-summary-page.component';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  readonly summary = signal({
    title: 'Run Complete',
    status: 'boss_cleared',
    rewards: ['Teeth +125', 'New Units: Goblin Bruiser', 'New Dice: bone d8'],
    progression: ['Fang +30 XP'],
    survivors: ['Fang'],
    defeated: ['Muck'],
    rewardDetail: {
      currency_soft: 125,
      units: [{ unit_instance_id: 'u1', label: 'Goblin Bruiser' }],
      dice: [{ dice_instance_id: 'd1', label: 'bone d8' }],
    },
    progressionDetail: [{
      unit_instance_id: 'u1',
      label: 'Fang',
      xp_gained: 30,
      level_gain_count: 0,
      final_level: 2,
      final_xp: 90,
      xp_to_next_level: 30,
      tier: 1,
      max_level: 6,
      unit_type_name: 'Bruiser',
    }],
  });
}

class SessionServiceStub {
  readonly units = signal([
    {
      id: 'u1',
      name: 'Fang',
      level: 2,
      tier: 1,
      xp: 90,
      xp_to_next_level: 30,
      max_level: 6,
      unit_type_name: 'Bruiser',
    },
  ]);
  readonly dice = signal([
    {
      id: 'd1',
      rarity: 'rare',
      sides: 8,
      affixes: [],
    },
  ]);
}

describe('RunSummaryPageComponent', () => {
  it('renders shared reward cards and progression bars from structured summary data', async () => {
    await TestBed.configureTestingModule({
      imports: [RunSummaryPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunSummaryPageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Run Complete');
    expect(compiled.textContent).toContain('Rewards');
    expect(compiled.textContent).toContain('125');
    expect(compiled.textContent).toContain('Fang');
    expect(compiled.textContent).toContain('d8');
    expect(compiled.textContent).toContain('Rare');
    expect(compiled.textContent).not.toContain('XP 90/120');
    expect(compiled.textContent).not.toContain('Level Up');
    expect(compiled.textContent).not.toContain('Outcome');
    expect(compiled.textContent).not.toContain('Survivors');
    expect(compiled.textContent).not.toContain('Defeated');
    expect(compiled.textContent).toContain('Return Home');
  });

  it('uses structured summary snapshots to avoid fake level-up callouts after cleanup', async () => {
    class CleanupRunServiceStub {
      readonly summary = signal({
        title: 'Run Abandoned',
        status: 'abandoned',
        rewards: [],
        progression: ['Boghand +20 XP'],
        survivors: [],
        defeated: [],
        rewardDetail: {
          currency_soft: 0,
          units: [],
          dice: [],
        },
        progressionDetail: [{
          unit_instance_id: 'u2',
          label: 'Boghand',
          xp_gained: 20,
          level_gain_count: 0,
          final_level: 3,
          final_xp: 20,
          xp_to_next_level: 180,
          tier: 1,
          max_level: 6,
          unit_type_name: 'Bruiser',
        }],
      });
    }

    class CleanupSessionServiceStub {
      readonly units = signal([
        {
          id: 'u2',
          name: 'Boghand',
          level: 3,
          tier: 1,
          xp: 0,
          xp_to_next_level: 200,
          max_level: 6,
          unit_type_name: 'Bruiser',
        },
      ]);
      readonly dice = signal([]);
    }

    await TestBed.configureTestingModule({
      imports: [RunSummaryPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: CleanupRunServiceStub },
        { provide: SessionService, useClass: CleanupSessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunSummaryPageComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Boghand');
    expect(compiled.textContent).not.toContain('Level Up');
    expect(compiled.textContent).not.toContain('XP 0/200');
  });

  it('renders zero-xp run units from progression detail so no deployed unit disappears', async () => {
    class FullRunSummaryServiceStub {
      readonly summary = signal({
        title: 'Run Abandoned',
        status: 'abandoned',
        rewards: [],
        progression: ['Boghand +20 XP'],
        survivors: ['Boghand'],
        defeated: ['Copperwhistle'],
        rewardDetail: {
          currency_soft: 0,
          units: [],
          dice: [],
        },
        progressionDetail: [
          {
            unit_instance_id: 'u2',
            label: 'Boghand',
            xp_gained: 20,
            is_defeated: false,
            level_gain_count: 0,
            final_level: 3,
            final_xp: 20,
            xp_to_next_level: 180,
            tier: 1,
            max_level: 6,
            unit_type_name: 'Bruiser',
          },
          {
            unit_instance_id: 'u3',
            label: 'Copperwhistle',
            xp_gained: 0,
            is_defeated: true,
            level_gain_count: 0,
            final_level: 2,
            final_xp: 0,
            xp_to_next_level: 150,
            tier: 1,
            max_level: 6,
            unit_type_name: 'Bruiser',
          },
        ],
      });
    }

    class FullRunSessionServiceStub {
      readonly units = signal([
        {
          id: 'u2',
          name: 'Boghand',
          level: 3,
          tier: 1,
          xp: 0,
          xp_to_next_level: 200,
          max_level: 6,
          unit_type_name: 'Bruiser',
        },
        {
          id: 'u3',
          name: 'Copperwhistle',
          level: 2,
          tier: 1,
          xp: 0,
          xp_to_next_level: 150,
          max_level: 6,
          unit_type_name: 'Bruiser',
        },
      ]);
      readonly dice = signal([]);
    }

    await TestBed.configureTestingModule({
      imports: [RunSummaryPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: FullRunSummaryServiceStub },
        { provide: SessionService, useClass: FullRunSessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(RunSummaryPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;

    expect(component.progressionCards()).toHaveSize(2);
    expect(compiled.textContent).toContain('Boghand');
    expect(compiled.textContent).toContain('Copperwhistle');
    expect(compiled.textContent).not.toContain('No progression milestones recorded.');
  });
});

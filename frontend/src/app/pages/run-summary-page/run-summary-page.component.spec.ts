import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { RunSummaryPageComponent } from './run-summary-page.component';

class RunServiceStub {
  readonly summary = signal({
    title: 'Run Complete',
    status: 'boss_cleared',
    rewards: ['Teeth +125', 'New Units: Goblin Bruiser', 'New Dice: bone d8'],
    progression: ['Fang +30 XP'],
    survivors: ['Fang'],
    defeated: ['Muck'],
    meta: {
      new_feature_unlocks: ['wrong_machine'],
      new_region_unlocks: ['mountains'],
    },
    rewardDetail: {
      currency_soft: 125,
      units: [{ unit_instance_id: 'u1', label: 'Goblin Bruiser' }],
      dice: [{ dice_instance_id: 'd1', label: 'bone d8' }],
      items: [{ item_slug: 'pig_ear', name: 'Pig Ear', quantity: 2, rarity: 'rare' }],
    },
    stolenPages: [{ dialogue_id: 'mountains-archivist-first-contact', title: 'Archivist First Contact' }],
    progressionDetail: [
      {
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
      },
    ],
  });
}

class SessionServiceStub {
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Commander',
    userId: '42',
    csrfToken: 'token',
  });
  readonly profileData = signal<any>({
    feature_unlocks: [],
    seen_dialogues: [],
    regions: [{ slug: 'mountains', name: 'Mountains' }],
  });
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
  refreshProfile = jasmine.createSpy('refreshProfile').and.resolveTo(undefined);
}

describe('RunSummaryPageComponent', () => {
  it('renders loot-style rewards and current unit bars from structured summary data', async () => {
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
    expect(compiled.textContent).toContain('Acquisitions');
    expect(compiled.textContent).toContain('125');
    expect(compiled.textContent).toContain('Fang');
    expect(compiled.textContent).toContain('d8');
    expect(compiled.textContent).toContain('Rare');
    expect(compiled.textContent).toContain('Pig Ear x2');
    expect(compiled.textContent).toContain('Wrong Machine');
    expect(compiled.textContent).toContain('Mountains');
    expect(compiled.textContent).toContain('Archivist First Contact');
    expect(compiled.textContent).toContain('Return Home');
    expect(compiled.querySelectorAll('.run-summary-loot-masonry .loot-card')).toHaveSize(7);
    expect(compiled.querySelectorAll('dg-unit-bar')).toHaveSize(1);
    expect(compiled.querySelector('dg-unit-grid-object')).toBeNull();
  });

  it('uses structured summary snapshots for current progression values', async () => {
    class CleanupRunServiceStub {
      readonly summary = signal({
        title: 'Returned Home',
        status: 'abandoned',
        rewards: [],
        progression: ['Boghand +20 XP'],
        survivors: [],
        defeated: [],
        meta: null,
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
            level_gain_count: 0,
            final_level: 3,
            final_xp: 20,
            xp_to_next_level: 180,
            tier: 1,
            max_level: 6,
            unit_type_name: 'Bruiser',
          },
        ],
      });
    }

    class CleanupSessionServiceStub extends SessionServiceStub {
      override readonly units = signal([
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
      override readonly dice = signal([]);
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

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Boghand');
    expect(component.squadOutcomeUnits()).toEqual([
      jasmine.objectContaining({
        xpGained: 20,
        positionLabel: '+20 XP',
        unit: jasmine.objectContaining({
          name: 'Boghand',
          level: 3,
          xp: 20,
        }),
      }),
    ]);
  });

  it('renders zero-xp run units from progression detail so no deployed unit disappears', async () => {
    class FullRunSummaryServiceStub {
      readonly summary = signal({
        title: 'Returned Home',
        status: 'abandoned',
        rewards: [],
        progression: ['Boghand +20 XP'],
        survivors: ['Boghand'],
        defeated: ['Copperwhistle'],
        meta: null,
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

    class FullRunSessionServiceStub extends SessionServiceStub {
      override readonly units = signal([
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
      override readonly dice = signal([]);
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

    expect(component.squadOutcomeUnits()).toHaveSize(2);
    expect(compiled.querySelectorAll('dg-unit-bar')).toHaveSize(2);
    expect(compiled.textContent).toContain('Boghand');
    expect(compiled.textContent).toContain('Copperwhistle');
  });
});

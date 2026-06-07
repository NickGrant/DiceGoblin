import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap } from '@angular/router';
import { SquadDetailsPageComponent } from './squad-details-page.component';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';

class SessionServiceStub {
  readonly squads = signal([
    {
      id: 's1',
      name: 'Alpha',
      unit_ids: ['u1', 'u2'],
      formation: [{ cell: 'A1', unit_instance_id: 'u1' }],
      is_active: false,
    },
  ] as any[]);
  readonly units = signal([
    { id: 'u1', name: 'Fang', locked: false },
    { id: 'u2', name: 'Moss', locked: false },
  ] as any[]);
  readonly profileData = signal({ active_run: null } as any);
  readonly activeSquad = signal(null as any);
}

class SquadServiceStub {
  updateTeam = jasmine.createSpy('updateTeam').and.resolveTo({ ok: true });
  activateTeam = jasmine.createSpy('activateTeam').and.resolveTo({ ok: true });
}

describe('SquadDetailsPageComponent', () => {
  it('saves squad changes through the squad service', async () => {
    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.save();

    const squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
    expect(squadService.updateTeam).toHaveBeenCalledWith(
      's1',
      jasmine.objectContaining({
        name: 'Alpha',
        unit_ids: ['u1', 'u2'],
      }),
    );
    expect(fixture.componentInstance.message()).toBe('Squad saved.');
  });

  it('clears formation assignments for units removed from the squad', async () => {
    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleUnit('u1');
    await fixture.componentInstance.save();

    const squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
    const [, payload] = squadService.updateTeam.calls.mostRecent().args as [string, any];
    const a1 = payload.formation.find((entry: any) => entry.cell === 'A1');

    expect(payload.unit_ids).toEqual(['u2']);
    expect(a1.unit_instance_id).toBeNull();
  });

  it('hydrates local editor state when the squad arrives after component creation', async () => {
    class DelayedSessionServiceStub {
      readonly squads = signal([] as any[]);
      readonly units = signal([{ id: 'u1', name: 'Fang' }] as any[]);
      readonly profileData = signal({ active_run: null } as any);
      readonly activeSquad = signal(null as any);
    }

    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: DelayedSessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    const sessionService = TestBed.inject(SessionService) as unknown as DelayedSessionServiceStub;
    fixture.detectChanges();

    sessionService.squads.set([
      {
        id: 's1',
        name: 'Late Squad',
        unit_ids: ['u1'],
        formation: [{ cell: 'B2', unit_instance_id: 'u1' }],
        is_active: false,
      },
    ]);
    fixture.detectChanges();

    expect(fixture.componentInstance.name).toBe('Late Squad');
    expect(fixture.componentInstance.selectedUnitIds.has('u1')).toBeTrue();
    expect(fixture.componentInstance.formationAssignments.get('B2')).toBe('u1');
  });

  it('moves a unit out of its previous formation slot when assigned to a new one', async () => {
    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.setCell('B2', 'u1');

    expect(fixture.componentInstance.formationAssignments.get('A1')).toBeNull();
    expect(fixture.componentInstance.formationAssignments.get('B2')).toBe('u1');
    expect(fixture.componentInstance.isUnitAssignedElsewhere('A1', 'u1')).toBeTrue();
    expect(fixture.componentInstance.isUnitAssignedElsewhere('B2', 'u1')).toBeFalse();
  });

  it('shows a lock message and blocks squad mutations during an active run', async () => {
    class LockedSessionServiceStub extends SessionServiceStub {
      override readonly squads = signal([
        {
          id: 's1',
          name: 'Alpha',
          unit_ids: ['u1', 'u2'],
          formation: [{ cell: 'A1', unit_instance_id: 'u1' }],
          is_active: true,
        },
      ] as any[]);
      override readonly profileData = signal({ active_run: { run_id: '9' } } as any);
      override readonly activeSquad = signal(this.squads()[0] as any);
    }

    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: LockedSessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.save();
    await fixture.componentInstance.activate();

    const squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
    const host: HTMLElement = fixture.nativeElement;

    expect(host.textContent).toContain('This active squad is locked while its run is in progress.');
    expect(fixture.componentInstance.squadLocked()).toBeTrue();
    expect(squadService.updateTeam).not.toHaveBeenCalled();
    expect(squadService.activateTeam).not.toHaveBeenCalled();
  });
});

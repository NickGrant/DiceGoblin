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
    { id: 'u1', name: 'Fang', level: 3, unit_type_name: 'Guardian', locked: false },
    { id: 'u2', name: 'Moss', level: 2, unit_type_name: 'Bruiser', locked: false },
    { id: 'u3', name: 'Rivet', level: 1, unit_type_name: 'Scout', locked: false },
  ] as any[]);
  readonly profileData = signal({ active_run: null } as any);
  readonly activeSquad = signal(null as any);
  readonly squadUnitCap = signal(4);
}

class SquadServiceStub {
  updateTeam = jasmine.createSpy('updateTeam').and.resolveTo({ ok: true });
}

function buildDropEvent(previousId: string, unitId: string): any {
  return {
    previousContainer: { id: previousId },
    item: { data: unitId },
  };
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
        unit_ids: ['u1'],
      }),
    );
    expect(fixture.componentInstance.message()).toBe('Squad saved.');
  });

  it('removes units from membership when dropped back to the available pool', async () => {
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

    fixture.componentInstance.dropUnit(buildDropEvent('formation-cell-A1', 'u1'), { type: 'available' });
    await fixture.componentInstance.save();

    const squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
    const [, payload] = squadService.updateTeam.calls.mostRecent().args as [string, any];
    const a1 = payload.formation.find((entry: any) => entry.cell === 'A1');

    expect(payload.unit_ids).toEqual([]);
    expect(a1.unit_instance_id).toBeNull();
  });

  it('hydrates local editor state when the squad arrives after component creation', async () => {
    class DelayedSessionServiceStub {
      readonly squads = signal([] as any[]);
      readonly units = signal([{ id: 'u1', name: 'Fang', level: 3, unit_type_name: 'Guardian' }] as any[]);
      readonly profileData = signal({ active_run: null } as any);
      readonly activeSquad = signal(null as any);
      readonly squadUnitCap = signal(4);
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
    expect(fixture.componentInstance.formationAssignments.get('B2')).toBe('u1');
  });

  it('moves a unit out of its previous formation slot when dropped into a new one', async () => {
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

    fixture.componentInstance.dropUnit(buildDropEvent('formation-cell-A1', 'u1'), { type: 'cell', cell: 'B2' });

    expect(fixture.componentInstance.formationAssignments.get('A1')).toBeNull();
    expect(fixture.componentInstance.formationAssignments.get('B2')).toBe('u1');
  });

  it('adds available units directly into formation via drag and drop', async () => {
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

    fixture.componentInstance.dropUnit(buildDropEvent('available-drop', 'u3'), { type: 'cell', cell: 'C3' });

    expect(fixture.componentInstance.formationAssignments.get('C3')).toBe('u3');
  });

  it('blocks adding a new unit into an empty slot when the squad is already at cap', async () => {
    class CappedSessionServiceStub extends SessionServiceStub {
      override readonly squads = signal([
        {
          id: 's1',
          name: 'Alpha',
          unit_ids: ['u1', 'u2', 'u3', 'u4'],
          formation: [
            { cell: 'A1', unit_instance_id: 'u1' },
            { cell: 'A2', unit_instance_id: 'u2' },
            { cell: 'B1', unit_instance_id: 'u3' },
            { cell: 'B2', unit_instance_id: 'u4' },
          ],
          is_active: false,
        },
      ] as any[]);
      override readonly units = signal([
        { id: 'u1', name: 'Fang', level: 3, unit_type_name: 'Guardian', locked: false },
        { id: 'u2', name: 'Moss', level: 2, unit_type_name: 'Bruiser', locked: false },
        { id: 'u3', name: 'Rivet', level: 1, unit_type_name: 'Scout', locked: false },
        { id: 'u4', name: 'Brass', level: 1, unit_type_name: 'Marksman', locked: false },
        { id: 'u5', name: 'Tongs', level: 1, unit_type_name: 'Bruiser', locked: false },
      ] as any[]);
      override readonly squadUnitCap = signal(4);
    }

    await TestBed.configureTestingModule({
      imports: [SquadDetailsPageComponent],
      providers: [
        { provide: SessionService, useClass: CappedSessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: convertToParamMap({ squadId: 's1' }) } },
        },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(SquadDetailsPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.dropUnit(buildDropEvent('available-drop', 'u5'), { type: 'cell', cell: 'C3' });

    expect(fixture.componentInstance.formationAssignments.get('C3')).toBeNull();
    expect(fixture.componentInstance.error()).toContain('capped at 4 units');
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

    fixture.componentInstance.dropUnit(buildDropEvent('available-drop', 'u3'), { type: 'cell', cell: 'B2' });
    await fixture.componentInstance.save();
    const squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
    const host: HTMLElement = fixture.nativeElement;

    expect(host.textContent).toContain('This active squad is locked while its run is in progress.');
    expect(host.textContent).toContain('Back');
    expect(host.textContent).toContain('Front');
    expect(fixture.componentInstance.squadLocked()).toBeTrue();
    expect(fixture.componentInstance.formationAssignments.get('B2')).toBeNull();
    expect(squadService.updateTeam).not.toHaveBeenCalled();
  });
});

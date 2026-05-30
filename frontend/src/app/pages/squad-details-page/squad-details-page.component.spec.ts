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
      unit_ids: ['u1'],
      formation: [],
      is_active: false,
    },
  ] as any[]);
  readonly units = signal([{ id: 'u1', name: 'Fang' }] as any[]);
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
    expect(squadService.updateTeam).toHaveBeenCalled();
    expect(fixture.componentInstance.message()).toBe('Squad saved.');
  });
});

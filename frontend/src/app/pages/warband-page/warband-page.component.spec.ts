import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { RouterLink, provideRouter } from '@angular/router';
import { WarbandPageComponent } from './warband-page.component';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';

class SessionServiceStub {
  readonly profile = signal({ activeSquadName: 'Alpha' });
  readonly squads = signal([{ id: '1', name: 'Alpha', is_active: true, unit_ids: ['u1'] }]);
  readonly units = signal([{ id: 'u1', name: 'Fang' }]);
  readonly profileData = signal({ active_run: null });
}

class SquadServiceStub {
  createTeam = jasmine.createSpy('createTeam').and.resolveTo({ ok: true });
  activateTeam = jasmine.createSpy('activateTeam').and.resolveTo({ ok: true });
}

describe('WarbandPageComponent', () => {
  let squadService: SquadServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [WarbandPageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: SquadService, useClass: SquadServiceStub },
      ],
    }).compileComponents();

    squadService = TestBed.inject(SquadService) as unknown as SquadServiceStub;
  });

  it('creates a squad and sets a success message', async () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.createSquad();

    expect(squadService.createTeam).toHaveBeenCalled();
    expect(fixture.componentInstance.message()).toBe('Squad created.');
  });

  it('activates a squad and sets a success message', async () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.activateSquad('1');

    expect(squadService.activateTeam).toHaveBeenCalledWith('1');
    expect(fixture.componentInstance.message()).toBe('Active squad updated.');
  });

  it('uses unit cards as details links without unit action buttons', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const unitLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('Fang'));

    expect(unitLinkDebug).toBeDefined();
    expect(unitLinkDebug!.injector.get(RouterLink).href).toContain('/warband/units/u1');
    expect(host.textContent).not.toContain('Dice');
  });
});

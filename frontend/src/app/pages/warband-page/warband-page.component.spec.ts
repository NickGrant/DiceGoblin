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
  readonly units = signal([
    { id: 'u1', name: 'Fang', unit_type_name: 'Bruiser', locked: false },
    { id: 'u2', name: 'Muckjaw', unit_type_name: 'Plaguehand', locked: false },
  ]);
  readonly profileData = signal({ active_run: null });
  readonly activeSquad = signal({ id: '1', name: 'Alpha', is_active: true, unit_ids: ['u1'] } as any);
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

  it('renders squad cards as details links with a separate active toggle', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const squadLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('Alpha'));
    const toggle = host.querySelector('.squad-card__toggle') as HTMLButtonElement | null;

    expect(squadLinkDebug).toBeDefined();
    expect(squadLinkDebug!.injector.get(RouterLink).href).toContain('/warband/squads/1');
    expect(toggle).not.toBeNull();
    expect(host.textContent).not.toContain('Details');
    expect(host.textContent).not.toContain('Activate');
    expect(host.textContent).not.toContain('Active squad');
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
    expect(host.textContent).not.toContain('Unit Record');
  });

  it('marks the active squad as locked during an active run and disables squad toggles', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.profileData.set({ active_run: { run_id: '9' } } as any);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const toggle = host.querySelector('.squad-card__toggle') as HTMLButtonElement | null;

    expect(host.textContent).toContain('Locked while this squad is committed to the active run.');
    expect(toggle?.disabled).toBeTrue();
  });

  it('renders units in the horizontal rail layout', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const rail = host.querySelector('.warband-units-rail');
    const tiles = host.querySelectorAll('.warband-units-rail__tile');

    expect(rail).not.toBeNull();
    expect(tiles.length).toBe(2);
  });

  it('filters units by selected unit types', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleUnitType('Bruiser');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const tiles = host.querySelectorAll('.warband-units-rail__tile');

    expect(tiles.length).toBe(1);
    expect(host.textContent).toContain('Fang');
    expect(host.textContent).not.toContain('Muckjaw');
  });

  it('clears unit type filters', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleUnitType('Bruiser');
    fixture.componentInstance.clearUnitTypeFilters();

    expect(fixture.componentInstance.selectedUnitTypes()).toEqual([]);
  });
});

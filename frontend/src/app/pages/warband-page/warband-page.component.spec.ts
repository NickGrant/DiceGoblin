import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { Router, RouterLink, provideRouter } from '@angular/router';
import { WarbandPageComponent } from './warband-page.component';
import { SessionService } from '../../core/services/session/session.service';
import { SquadService } from '../../core/services/squad/squad.service';

class SessionServiceStub {
  readonly profile = signal({ activeSquadName: 'Alpha' });
  readonly squads = signal([
    { id: '1', name: 'Alpha', is_active: true, unit_ids: ['u1'] },
    { id: '2', name: 'Beta', is_active: false, unit_ids: ['u1', 'u2'] },
  ]);
  readonly units = signal([
    { id: 'u1', name: 'Fang', unit_type_name: 'Bruiser', tier: 1, level: 2, locked: false },
    { id: 'u2', name: 'Muckjaw', unit_type_name: 'Plaguehand', tier: 2, level: 6, locked: false },
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
  let router: Router;

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
    router = TestBed.inject(Router);
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

  it('renders unlocked squad cards as full-card links', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const squadLinkDebug = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((element) => element.nativeElement.textContent?.includes('Alpha'));

    expect(squadLinkDebug).toBeDefined();
    expect(squadLinkDebug!.injector.get(RouterLink).href).toContain('/warband/squads/1');
    expect(host.querySelector('.squad-card__nameplate--link')).not.toBeNull();
  });

  it('renders an activation hotspot for inactive unlocked squads', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const activateButton = host.querySelector('.squad-card__activate') as HTMLButtonElement | null;

    expect(activateButton).not.toBeNull();
    expect(activateButton?.getAttribute('aria-label')).toContain('Set Beta as the active squad');
  });

  it('does not render the old hover-inspect sidebar', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    expect(host.querySelector('.warband-units-inspect__panel')).toBeNull();
    expect(host.querySelector('.warband-units-roster')).not.toBeNull();
  });

  it('marks the active squad as locked during an active run', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.profileData.set({ active_run: { run_id: '9' } } as any);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const lockedCard = host.querySelector('.squad-card.is-locked');
    const lockedLink = host.querySelector('.squad-card.is-locked .squad-card__nameplate--link');

    expect(host.textContent).toContain('Squads and Squad members are locked while on a run.');
    expect(lockedCard).not.toBeNull();
    expect(lockedLink).toBeNull();
  });

  it('renders units in the full-width roster grid', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const grid = host.querySelector('.warband-units-grid');
    const tiles = host.querySelectorAll('.warband-units-grid__tile');

    expect(grid).not.toBeNull();
    expect(tiles.length).toBe(2);
    expect(host.querySelectorAll('dg-unit-bar').length).toBe(2);
  });

  it('filters units by selected unit type', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.updateUnitType('Bruiser');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const tiles = host.querySelectorAll('.warband-units-grid__tile');

    expect(tiles.length).toBe(1);
    expect(host.textContent).toContain('Fang');
  });

  it('filters units by selected tier', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.updateUnitTier('2');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const tiles = host.querySelectorAll('.warband-units-grid__tile');

    expect(tiles.length).toBe(1);
    expect(host.textContent).toContain('Muckjaw');
  });

  it('filters units by selected level range', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.updateLevelMin('5');
    fixture.componentInstance.updateLevelMax('6');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const tiles = host.querySelectorAll('.warband-units-grid__tile');

    expect(tiles.length).toBe(1);
    expect(host.textContent).toContain('Muckjaw');
  });

  it('clears unit type filters', () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();

    fixture.componentInstance.updateUnitType('Bruiser');
    fixture.componentInstance.updateUnitTier('2');
    fixture.componentInstance.updateLevelMin('2');
    fixture.componentInstance.updateLevelMax('6');
    fixture.componentInstance.clearUnitFilters();

    expect(fixture.componentInstance.selectedUnitType()).toBeNull();
    expect(fixture.componentInstance.selectedUnitTier()).toBeNull();
    expect(fixture.componentInstance.selectedLevelMin()).toBeNull();
    expect(fixture.componentInstance.selectedLevelMax()).toBeNull();
  });

  it('opens a unit directly when a grid tile is activated', async () => {
    const fixture = TestBed.createComponent(WarbandPageComponent);
    fixture.detectChanges();
    spyOn(router, 'navigate').and.resolveTo(true);

    await fixture.componentInstance.openUnit('u2');

    expect(router.navigate).toHaveBeenCalledWith(['/warband/units', 'u2']);
  });
});

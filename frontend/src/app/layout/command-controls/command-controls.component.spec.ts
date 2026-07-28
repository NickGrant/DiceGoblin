import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { provideRouter, Router, RouterLink } from '@angular/router';
import { CommandControlsComponent } from './command-controls.component';
import { AudioDirectorService } from '../../core/services/audio/audio-director.service';
import { SessionService } from '../../core/services/session/session.service';

class SessionServiceStub {
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Nick',
  });

  readonly profile = signal({
    energyCurrent: 12,
    energyMax: 20,
    softCurrency: 93,
  });

  readonly profileData = signal({
    currency: {
      raw_chaos: 7,
    },
  });

  readonly featureUnlocks = signal(['shop']);
  readonly hasActiveRun = signal(false);
  readonly logout = jasmine.createSpy('logout').and.resolveTo();
}

class AudioDirectorServiceStub {
  readonly isEnabled = signal(true);
  readonly isUnlocked = signal(false);
  readonly isMuted = signal(false);
  readonly enableSound = jasmine.createSpy('enableSound').and.resolveTo();
  readonly toggleMute = jasmine.createSpy('toggleMute');
}

describe('CommandControlsComponent', () => {
  let sessionService: SessionServiceStub;
  let audioDirector: AudioDirectorServiceStub;
  let router: Router;
  let originalResizeObserver: typeof ResizeObserver | undefined;

  class ResizeObserverStub {
    constructor(private readonly callback: ResizeObserverCallback) {}

    observe(): void {
      this.callback([], this as unknown as ResizeObserver);
    }

    disconnect(): void {}
  }

  beforeEach(async () => {
    originalResizeObserver = window.ResizeObserver;
    Object.defineProperty(window, 'ResizeObserver', {
      configurable: true,
      writable: true,
      value: ResizeObserverStub,
    });

    await TestBed.configureTestingModule({
      imports: [CommandControlsComponent],
      providers: [
        provideRouter([]),
        {
          provide: SessionService,
          useClass: SessionServiceStub,
        },
        {
          provide: AudioDirectorService,
          useClass: AudioDirectorServiceStub,
        },
      ],
    }).compileComponents();

    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    audioDirector = TestBed.inject(AudioDirectorService) as unknown as AudioDirectorServiceStub;
    router = TestBed.inject(Router);
  });

  afterEach(() => {
    Object.defineProperty(window, 'ResizeObserver', {
      configurable: true,
      writable: true,
      value: originalResizeObserver,
    });

    document.documentElement.style.removeProperty('--command-controls-height');
  });

  it('renders the commander and resource values', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;

    expect(compiled.textContent).toContain('Nick');
    expect(compiled.textContent).toContain('12 / 20');
    expect(compiled.textContent).toContain('93');
    expect(compiled.textContent).toContain('Enable Sound');
  });

  it('enables sound when the audio control is pressed before unlock', async () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.audio-control') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(audioDirector.enableSound).toHaveBeenCalled();
    expect(audioDirector.toggleMute).not.toHaveBeenCalled();
  });

  it('toggles mute once audio is unlocked', async () => {
    audioDirector.isUnlocked.set(true);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.audio-control') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(audioDirector.toggleMute).toHaveBeenCalled();
  });

  it('includes a codex link that routes directly to the codex', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const guideLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Codex');

    expect(guideLink).toBeDefined();
    expect(router.serializeUrl(guideLink!.injector.get(RouterLink).urlTree!)).toBe('/codex');
  });

  it('includes a guide link that routes directly to the guide', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const guideLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Guide');

    expect(guideLink).toBeDefined();
    expect(router.serializeUrl(guideLink!.injector.get(RouterLink).urlTree!)).toBe('/guide');
  });

  it('includes a start run link that routes to region selection', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const runLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Start Run');

    expect(runLink).toBeDefined();
    expect(runLink!.nativeElement.textContent).toContain('Start Run');
    expect(router.serializeUrl(runLink!.injector.get(RouterLink).urlTree!)).toBe('/regions');
  });

  it('updates the run link while a run is active', () => {
    sessionService.hasActiveRun.set(true);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const runLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Continue Run');

    expect(runLink).toBeDefined();
    expect(runLink!.nativeElement.textContent).toContain('Continue Run');
    expect(router.serializeUrl(runLink!.injector.get(RouterLink).urlTree!)).toBe('/run/map');
  });

  it('stores the measured hud height in a shared CSS variable', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    spyOn(fixture.nativeElement as HTMLElement, 'getBoundingClientRect').and.returnValue({
      width: 320,
      height: 96,
      top: 0,
      left: 0,
      right: 320,
      bottom: 96,
      x: 0,
      y: 0,
      toJSON: () => '',
    } as DOMRect);

    fixture.detectChanges();

    expect(document.documentElement.style.getPropertyValue('--command-controls-height')).toBe('96px');
  });

  it('delegates logout to the session service when the logout button is clicked', async () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const buttons = fixture.nativeElement.querySelectorAll('.menu-item-action') as NodeListOf<HTMLButtonElement>;
    const button = buttons[0];
    button.click();
    await fixture.whenStable();

    expect(sessionService.logout).toHaveBeenCalled();
  });

  it('renders the floating header art assets', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(window.getComputedStyle(compiled).backgroundImage).toContain('floating-header-bar.png');
    expect(compiled.querySelector('.slots')).not.toBeNull();
    expect(compiled.querySelector('.menu-toggle-box')).not.toBeNull();
    expect(compiled.querySelector('.menu-toggle-inner')).not.toBeNull();
  });

  it('opens a labeled dropdown menu from the arrow toggle', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const toggle = fixture.nativeElement.querySelector('.menu-toggle') as HTMLButtonElement;
    toggle.click();
    fixture.detectChanges();

    const menu = fixture.nativeElement.querySelector('.menu') as HTMLElement | null;
    expect(menu).not.toBeNull();
    expect(toggle.classList.contains('is-active')).toBeTrue();
    expect(menu?.textContent).toContain('Home');
    expect(menu?.textContent).toContain('Start Run');
    expect(menu?.textContent).toContain('Warband');
    expect(menu?.textContent).toContain('Inventory');
    expect(menu?.textContent).toContain('Shop');
    expect(menu?.textContent).not.toContain('Academy');
    expect(menu?.textContent).toContain('Guide');
    expect(menu?.textContent).toContain('Codex');
    expect(menu?.textContent).toContain('Logout');
    expect(window.getComputedStyle(menu!).animationName).toContain('menu-enter');
  });

  it('preloads dropdown frame art before the menu is opened', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const preloadImages = Array.from(
      fixture.nativeElement.querySelectorAll('.menu-preload img'),
    ) as HTMLImageElement[];

    expect(preloadImages.map((image) => image.getAttribute('src'))).toEqual([
      '/assets/ui/header/menu-top-frame.png',
      '/assets/ui/header/menu-center-frame.png',
      '/assets/ui/header/menu-bottom-frame.png',
      '/assets/ui/header/floating-header-menu-row.png',
    ]);
  });

  it('hides the academy menu item until the unlock is earned', () => {
    sessionService.featureUnlocks.set([]);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const academyItem = compiled.querySelector('.menu [aria-label="Academy"]');

    expect(academyItem).toBeNull();
  });

  it('hides the shop menu item until the unlock is earned', () => {
    sessionService.featureUnlocks.set([]);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const shopItem = compiled.querySelector('.menu [aria-label="Shop"]');

    expect(shopItem).toBeNull();
  });

  it('shows the shop menu item after the unlock is earned', () => {
    sessionService.featureUnlocks.set(['shop']);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const shopLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Shop');

    expect(shopLink).toBeDefined();
    expect(router.serializeUrl(shopLink!.injector.get(RouterLink).urlTree!)).toBe('/shop');
  });

  it('shows the Wrong Machine menu item after the unlock is earned', () => {
    sessionService.featureUnlocks.set(['wrong_machine']);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const machineLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Wrong Machine');

    expect(machineLink).toBeDefined();
    expect(machineLink!.nativeElement.textContent).toContain('Machine');
    expect(machineLink!.nativeElement.querySelector('.menu-item-icon')?.getAttribute('src')).toBe('/assets/ui/icons/icon_encounter_locked.png');
    expect(router.serializeUrl(machineLink!.injector.get(RouterLink).urlTree!)).toBe('/wrong-machine');
  });

  it('shows Raw Chaos as an icon and balance after the Wrong Machine unlock', () => {
    sessionService.featureUnlocks.set(['wrong_machine']);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const chaosSlot = compiled.querySelector('.slot-chaos') as HTMLElement;

    expect(chaosSlot.getAttribute('aria-label')).toBe('Raw Chaos');
    expect(chaosSlot.querySelector('.raw-chaos-icon')).not.toBeNull();
    expect(chaosSlot.textContent?.trim()).toBe('7');
  });

  it('shows the academy menu item after the unlock is earned', () => {
    sessionService.featureUnlocks.set(['academy']);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.menu [aria-label="Academy"]')).not.toBeNull();
  });

  it('closes the dropdown after tapping a menu link', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();

    component.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const mobileLink = fixture.nativeElement.querySelector('.menu a') as HTMLAnchorElement;
    mobileLink.click();
    fixture.detectChanges();

    expect(component.mobileMenuOpen()).toBeFalse();
  });

  it('shows login and hides authenticated routes when not authenticated', () => {
    sessionService.session.set({
      isAuthenticated: false,
      displayName: 'Visitor',
    });

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.componentInstance.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('.slot-name')?.textContent).toContain('Guest');
    expect(compiled.querySelector('.slot-energy')?.textContent).toContain('--');
    expect(compiled.querySelector('.menu [aria-label="Warband"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Start Run"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Continue Run"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Inventory"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Wrong Machine"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Shop"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Academy"]')).toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Home"]')).not.toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Guide"]')).not.toBeNull();
    expect(compiled.querySelector('.menu [aria-label="Codex"]')).toBeNull();
    expect(compiled.querySelector('.menu-item-action')?.textContent).toContain('Login');
  });
});

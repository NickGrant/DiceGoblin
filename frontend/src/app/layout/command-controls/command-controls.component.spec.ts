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

  readonly featureUnlocks = signal(['shop']);
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

    const button = fixture.nativeElement.querySelector('.hud-audio') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(audioDirector.enableSound).toHaveBeenCalled();
    expect(audioDirector.toggleMute).not.toHaveBeenCalled();
  });

  it('toggles mute once audio is unlocked', async () => {
    audioDirector.isUnlocked.set(true);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.hud-audio') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(audioDirector.toggleMute).toHaveBeenCalled();
  });

  it('includes a guide link that routes directly to the field guide', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const guideLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Field Guide');

    expect(guideLink).toBeDefined();
    expect(router.serializeUrl(guideLink!.injector.get(RouterLink).urlTree!)).toBe('/field-guide');
    const icon = guideLink!.nativeElement.querySelector('img') as HTMLImageElement | null;
    expect(icon?.getAttribute('src')).toContain('icon_guide.png');
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
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.hud-logout') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(sessionService.logout).toHaveBeenCalled();
  });

  it('renders the new split header art assets', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    const panels = compiled.querySelectorAll('.hud-panel');

    expect(panels.length).toBe(2);
    expect(compiled.querySelector('.hud-panel--nav')).not.toBeNull();
    expect(compiled.querySelector('.hud-panel--player')).not.toBeNull();
  });

  it('opens a labeled mobile menu from the player panel', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const toggle = fixture.nativeElement.querySelector('.hud-menu-toggle') as HTMLButtonElement;
    toggle.click();
    fixture.detectChanges();

    const menu = fixture.nativeElement.querySelector('.hud-mobile-menu') as HTMLElement | null;
    expect(menu).not.toBeNull();
    expect(menu?.textContent).toContain('Home');
    expect(menu?.textContent).toContain('Warband');
    expect(menu?.textContent).toContain('Inventory');
    expect(menu?.textContent).toContain('Shop');
    expect(menu?.textContent).toContain('Guide');
    expect(menu?.textContent).toContain('Logout');
  });

  it('disables the shop icon until the farm unlock is earned', () => {
    sessionService.featureUnlocks.set([]);

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const disabledItems = Array.from(fixture.nativeElement.querySelectorAll('.hud-panel--nav [aria-disabled="true"]')) as HTMLElement[];
    const shopItem = disabledItems.find((item) => item.getAttribute('aria-label')?.includes('Shop unavailable until you defeat The Farm'));

    expect(shopItem).toBeDefined();
  });

  it('closes the mobile menu after tapping a menu link', () => {
    const fixture = TestBed.createComponent(CommandControlsComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();

    component.mobileMenuOpen.set(true);
    fixture.detectChanges();

    const mobileLink = fixture.nativeElement.querySelector('.hud-mobile-menu nav a') as HTMLAnchorElement;
    mobileLink.click();
    fixture.detectChanges();

    expect(component.mobileMenuOpen()).toBeFalse();
  });

  it('disables protected navigation items and shows login when not authenticated', () => {
    sessionService.session.set({
      isAuthenticated: false,
      displayName: 'Visitor',
    });

    const fixture = TestBed.createComponent(CommandControlsComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Login');
    expect(compiled.textContent).not.toContain('12 / 20');
    expect(compiled.querySelector('.hud-logout')).toBeNull();
    expect(compiled.querySelectorAll('.hud-panel--nav [aria-disabled="true"]').length).toBe(3);
  });
});

import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { provideRouter, Router, RouterLink } from '@angular/router';
import { BottomCommandStripComponent } from './bottom-command-strip.component';
import { SessionService } from '../../core/services/session/session.service';

class SessionServiceStub {
  readonly session = signal({
    displayName: 'Nick',
  });

  readonly profile = signal({
    energyCurrent: 12,
    energyMax: 20,
    softCurrency: 93,
  });

  readonly logout = jasmine.createSpy('logout').and.resolveTo();
}

describe('BottomCommandStripComponent', () => {
  let sessionService: SessionServiceStub;
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
      imports: [BottomCommandStripComponent],
      providers: [
        provideRouter([]),
        {
          provide: SessionService,
          useClass: SessionServiceStub,
        },
      ],
    }).compileComponents();

    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    router = TestBed.inject(Router);
  });

  afterEach(() => {
    Object.defineProperty(window, 'ResizeObserver', {
      configurable: true,
      writable: true,
      value: originalResizeObserver,
    });

    document.documentElement.style.removeProperty('--bottom-command-strip-height');
  });

  it('renders the commander and resource values', () => {
    const fixture = TestBed.createComponent(BottomCommandStripComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;

    expect(compiled.textContent).toContain('Nick');
    expect(compiled.textContent).toContain('12 / 20');
    expect(compiled.textContent).toContain('93');
  });

  it('includes a guide link that preserves the current in-game route', () => {
    Object.defineProperty(router, 'url', {
      configurable: true,
      get: () => '/run/node/42',
    });

    const fixture = TestBed.createComponent(BottomCommandStripComponent);
    fixture.detectChanges();

    const guideLink = fixture.debugElement
      .queryAll(By.directive(RouterLink))
      .find((debugElement) => debugElement.attributes['aria-label'] === 'Field Guide');

    expect(guideLink).toBeDefined();
    expect(router.serializeUrl(guideLink!.injector.get(RouterLink).urlTree!)).toBe('/field-guide?returnUrl=%2Frun%2Fnode%2F42');
  });

  it('stores the measured hud height in a shared CSS variable', () => {
    const fixture = TestBed.createComponent(BottomCommandStripComponent);
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

    expect(document.documentElement.style.getPropertyValue('--bottom-command-strip-height')).toBe('96px');
  });

  it('delegates logout to the session service when the logout button is clicked', async () => {
    const fixture = TestBed.createComponent(BottomCommandStripComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.hud-logout') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(sessionService.logout).toHaveBeenCalled();
  });
});

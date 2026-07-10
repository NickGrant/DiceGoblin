import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';
import { AudioDirectorService } from './core/services/audio/audio-director.service';
import { SessionService } from './core/services/session/session.service';
import { ViewportOrientationService } from './core/services/viewport/viewport-orientation.service';

class SessionServiceStub {
  readonly isLoading = signal(false);
  readonly error = signal<string | null>(null);
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
  readonly session = signal({ isAuthenticated: false, displayName: 'Visitor' });
  readonly profile = signal({ energyCurrent: 0, energyMax: 0, softCurrency: 0 });
  readonly featureUnlocks = signal<string[]>([]);
}

class AudioDirectorServiceStub {
  readonly initialize = jasmine.createSpy('initialize');
  readonly setRouteContext = jasmine.createSpy('setRouteContext');
  readonly isEnabled = signal(true);
  readonly isUnlocked = signal(false);
  readonly isMuted = signal(false);
  readonly enableSound = jasmine.createSpy('enableSound').and.resolveTo();
  readonly toggleMute = jasmine.createSpy('toggleMute');
}

class ViewportOrientationServiceStub {
  readonly initialize = jasmine.createSpy('initialize');
  readonly isLandscapeGateActive = signal(false);
}

describe('App', () => {
  it('creates the root shell', async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AudioDirectorService, useClass: AudioDirectorServiceStub },
        { provide: ViewportOrientationService, useClass: ViewportOrientationServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();

    expect(fixture.componentInstance).toBeTruthy();
  });

  it('shows the rotate prompt instead of the shell when phone portrait mode is blocked', async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AudioDirectorService, useClass: AudioDirectorServiceStub },
        { provide: ViewportOrientationService, useClass: ViewportOrientationServiceStub },
      ],
    }).compileComponents();

    const viewportOrientation = TestBed.inject(ViewportOrientationService) as unknown as ViewportOrientationServiceStub;
    viewportOrientation.isLandscapeGateActive.set(true);

    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.textContent).toContain('Landscape Required');
    expect(host.querySelector('app-command-controls')).toBeNull();
    expect(host.querySelector('.orientation-gate')).not.toBeNull();
  });
});

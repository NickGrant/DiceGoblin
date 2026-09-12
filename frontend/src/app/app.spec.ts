import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';
import { AudioDirectorService } from './core/services/audio/audio-director.service';
import { SessionService } from './core/services/session/session.service';

class SessionServiceStub {
  readonly isLoading = signal(false);
  readonly error = signal<string | null>(null);
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
}

class AudioDirectorServiceStub {
  readonly initialize = jasmine.createSpy('initialize');
  readonly setRouteContext = jasmine.createSpy('setRouteContext');
}

describe('App', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [App],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: AudioDirectorService, useClass: AudioDirectorServiceStub },
      ],
    });
  });

  it('creates the root shell', async () => {
    await TestBed.compileComponents();

    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();

    expect(fixture.componentInstance).toBeTruthy();
  });

  it('does not mount prototype gameplay chrome', async () => {
    await TestBed.compileComponents();

    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.querySelector('app-command-controls')).toBeNull();
    expect(host.querySelector('.orientation-gate')).toBeNull();
  });

  it('gives /game a clean shell without Angular status UI', async () => {
    await TestBed.compileComponents();

    const session = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    session.isLoading.set(true);

    const fixture = TestBed.createComponent(App);
    fixture.componentInstance.isGameRoute.set(true);
    fixture.detectChanges();

    const host = fixture.nativeElement as HTMLElement;
    expect(host.querySelector('.app-shell--game')).not.toBeNull();
    expect(host.querySelector('router-outlet')).not.toBeNull();
    expect(host.querySelector('.status-card')).toBeNull();
  });
});

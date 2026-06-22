import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { GameShellComponent } from './game-shell.component';
import { SessionService } from '../../core/services/session/session.service';

class SessionServiceStub {
  readonly isLoading = signal(false);
  readonly error = signal<string | null>(null);
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
  readonly session = signal({ isAuthenticated: true, displayName: 'Nick' });
  readonly profile = signal({ energyCurrent: 1, energyMax: 2, softCurrency: 3 });
}

describe('GameShellComponent', () => {
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GameShellComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(GameShellComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });
});

import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { App } from './app';
import { SessionService } from './core/services/session/session.service';

class SessionServiceStub {
  readonly isLoading = signal(false);
  readonly error = signal<string | null>(null);
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
  readonly session = signal({ isAuthenticated: false, displayName: 'Visitor' });
  readonly profile = signal({ energyCurrent: 0, energyMax: 0, softCurrency: 0 });
}

describe('App', () => {
  it('creates the root shell', async () => {
    await TestBed.configureTestingModule({
      imports: [App],
      providers: [provideRouter([]), { provide: SessionService, useClass: SessionServiceStub }],
    }).compileComponents();

    const fixture = TestBed.createComponent(App);
    fixture.detectChanges();

    expect(fixture.componentInstance).toBeTruthy();
  });
});

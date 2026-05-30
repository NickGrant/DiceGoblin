import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
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
  let router: Router;
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
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

    router = TestBed.inject(Router);
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('renders the commander and resource values', () => {
    const fixture = TestBed.createComponent(BottomCommandStripComponent);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;

    expect(compiled.textContent).toContain('Nick');
    expect(compiled.textContent).toContain('12 / 20');
    expect(compiled.textContent).toContain('93');
  });

  it('logs out and routes to login when the logout button is clicked', async () => {
    spyOn(router, 'navigateByUrl').and.resolveTo(true);

    const fixture = TestBed.createComponent(BottomCommandStripComponent);
    fixture.detectChanges();

    const button = fixture.nativeElement.querySelector('.hud-logout') as HTMLButtonElement;
    button.click();
    await fixture.whenStable();

    expect(sessionService.logout).toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login');
  });
});

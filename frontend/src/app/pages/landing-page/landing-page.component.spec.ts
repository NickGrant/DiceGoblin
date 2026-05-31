import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { LandingPageComponent } from './landing-page.component';
import { SessionService } from '../../core/services/session/session.service';

class SessionServiceStub {
  initialize = jasmine.createSpy('initialize').and.resolveTo();
  session = jasmine.createSpy('session').and.returnValue({ isAuthenticated: false });
}

describe('LandingPageComponent', () => {
  it('creates a login url for discord auth', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const fixture = TestBed.createComponent(LandingPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.loginUrl).toContain('/auth/discord/start');
  });

  it('redirects authenticated users to home', async () => {
    await TestBed.configureTestingModule({
      imports: [LandingPageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    const router = TestBed.inject(Router);
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.session.and.returnValue({ isAuthenticated: true });
    spyOn(router, 'navigateByUrl').and.resolveTo(true);

    const fixture = TestBed.createComponent(LandingPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();

    expect(sessionService.initialize).toHaveBeenCalled();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/home');
  });
});

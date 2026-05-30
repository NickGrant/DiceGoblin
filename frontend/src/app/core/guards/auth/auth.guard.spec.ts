import { TestBed } from '@angular/core/testing';
import { Router, UrlTree } from '@angular/router';
import { authChildGuard, authGuard } from './auth.guard';
import { SessionService } from '../../services/session/session.service';

describe('authGuard', () => {
  let sessionService: jasmine.SpyObj<SessionService>;
  let router: jasmine.SpyObj<Router>;

  beforeEach(() => {
    sessionService = jasmine.createSpyObj<SessionService>('SessionService', ['initialize', 'session']);
    router = jasmine.createSpyObj<Router>('Router', ['createUrlTree']);

    TestBed.configureTestingModule({
      providers: [
        { provide: SessionService, useValue: sessionService },
        { provide: Router, useValue: router },
      ],
    });
  });

  it('allows authenticated users through the shell route', async () => {
    sessionService.initialize.and.resolveTo();
    sessionService.session.and.returnValue({ isAuthenticated: true } as any);

    const result = await TestBed.runInInjectionContext(() => authGuard({} as any, {} as any));

    expect(sessionService.initialize).toHaveBeenCalled();
    expect(result).toBeTrue();
  });

  it('redirects unauthenticated users to /login', async () => {
    const redirectTree = {} as UrlTree;
    sessionService.initialize.and.resolveTo();
    sessionService.session.and.returnValue({ isAuthenticated: false } as any);
    router.createUrlTree.and.returnValue(redirectTree);

    const result = await TestBed.runInInjectionContext(() => authGuard({} as any, {} as any));

    expect(router.createUrlTree).toHaveBeenCalledWith(['/login']);
    expect(result).toBe(redirectTree);
  });

  it('applies the same behavior to child routes', async () => {
    sessionService.initialize.and.resolveTo();
    sessionService.session.and.returnValue({ isAuthenticated: true } as any);

    const result = await TestBed.runInInjectionContext(() => authChildGuard({} as any, {} as any));

    expect(result).toBeTrue();
  });
});

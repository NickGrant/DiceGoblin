import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, Router } from '@angular/router';
import { SessionService } from '../../services/session/session.service';

async function requireAuthenticatedUser() {
  const sessionService = inject(SessionService);
  const router = inject(Router);

  await sessionService.initialize();

  return sessionService.session().isAuthenticated ? true : router.createUrlTree(['/login']);
}

async function requireGuestUser() {
  const sessionService = inject(SessionService);
  const router = inject(Router);

  await sessionService.initialize();

  return sessionService.session().isAuthenticated ? router.createUrlTree(['/home']) : true;
}

export const authGuard: CanActivateFn = async () => requireAuthenticatedUser();
export const authChildGuard: CanActivateChildFn = async () => requireAuthenticatedUser();
export const guestGuard: CanActivateFn = async () => requireGuestUser();

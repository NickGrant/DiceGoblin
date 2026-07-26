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

async function requireFeatureUnlock(unlockKey: string) {
  const sessionService = inject(SessionService);
  const router = inject(Router);

  await sessionService.initialize();

  if (!sessionService.session().isAuthenticated) {
    return router.createUrlTree(['/login']);
  }

  const unlocked = sessionService.profileData()?.feature_unlocks?.includes(unlockKey) ?? false;
  if (unlocked) {
    return true;
  }

  if (unlockKey === 'shop') {
    return router.createUrlTree(['/home']);
  }

  if (unlockKey === 'academy' && !(sessionService.profileData()?.feature_unlocks?.includes('shop') ?? false)) {
    return router.createUrlTree(['/home']);
  }

  return router.createUrlTree(['/shop']);
}

export const authGuard: CanActivateFn = async () => requireAuthenticatedUser();
export const authChildGuard: CanActivateChildFn = async () => requireAuthenticatedUser();
export const guestGuard: CanActivateFn = async () => requireGuestUser();
export const shopFeatureGuard: CanActivateFn = async () => requireFeatureUnlock('shop');
export const academyFeatureGuard: CanActivateFn = async () => requireFeatureUnlock('academy');
export const wrongMachineFeatureGuard: CanActivateFn = async () => requireFeatureUnlock('wrong_machine');

import { routes } from './app.routes';
import { GuidePageComponent } from './pages/guide-page/guide-page.component';
import { LandingPageComponent } from './pages/landing-page/landing-page.component';

describe('routes', () => {
  it('keeps public authentication and guide routes in Angular', () => {
    const loginRoute = routes.find((route) => route.path === 'login');
    const publicGuideRoute = routes.find((route) => route.path === 'guide');

    expect(loginRoute?.component).toBe(LandingPageComponent);
    expect(loginRoute?.canActivate?.length).toBe(1);
    expect(publicGuideRoute?.component).toBe(GuidePageComponent);
  });

  it('makes /game the authenticated default and only live game client', () => {
    const shellRoute = routes.find((route) => route.path === '');
    const defaultRoute = shellRoute?.children?.find((route) => route.path === '');
    const gameRoute = shellRoute?.children?.find((route) => route.path === 'game');
    const fallbackRoute = shellRoute?.children?.find((route) => route.path === '**');

    expect(defaultRoute?.redirectTo).toBe('game');
    expect(gameRoute?.loadComponent).toBeDefined();
    expect(gameRoute?.component).toBeUndefined();
    expect(gameRoute?.data?.['audio']).toEqual({ musicIntent: null, ambienceIntent: null });
    expect(fallbackRoute?.redirectTo).toBe('game');
    expect(shellRoute?.children?.map((route) => route.path)).toEqual(['', 'game', '**']);
  });

  it('retains authentication on the /game route boundary', () => {
    const shellRoute = routes.find((route) => route.path === '');

    expect(shellRoute?.canActivate?.length).toBe(1);
    expect(shellRoute?.canActivateChild?.length).toBe(1);
  });
});

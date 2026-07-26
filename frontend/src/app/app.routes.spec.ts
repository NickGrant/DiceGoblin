import { CodexPageComponent } from './pages/codex-page/codex-page.component';
import { GuidePageComponent } from './pages/guide-page/guide-page.component';
import { routes } from './app.routes';
import { WrongMachinePageComponent } from './pages/wrong-machine-page/wrong-machine-page.component';

describe('routes', () => {
  it('keeps guide and codex as separate routes', () => {
    const publicGuideRoute = routes.find((route) => route.path === 'guide');
    const shellRoute = routes.find((route) => route.path === '');
    const codexRoute = shellRoute?.children?.find((route) => route.path === 'codex');
    const legacyFieldGuideRoute = shellRoute?.children?.find((route) => route.path === 'field-guide');

    expect(publicGuideRoute?.component).toBe(GuidePageComponent);
    expect(codexRoute?.component).toBe(CodexPageComponent);
    expect(legacyFieldGuideRoute?.redirectTo).toBe('codex');
  });

  it('registers the Wrong Machine route inside the authenticated shell', () => {
    const shellRoute = routes.find((route) => route.path === '');
    const wrongMachineRoute = shellRoute?.children?.find((route) => route.path === 'wrong-machine');

    expect(wrongMachineRoute?.component).toBe(WrongMachinePageComponent);
    expect(wrongMachineRoute?.canActivate?.length).toBe(1);
  });
});

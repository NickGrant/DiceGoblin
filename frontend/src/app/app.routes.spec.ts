import { GuidePageComponent } from './pages/guide-page/guide-page.component';
import { routes } from './app.routes';

describe('routes', () => {
  it('keeps the public guide and exposes the guide inside the authenticated shell', () => {
    const shellRoute = routes.find((route) => route.path === '');
    const publicGuideRoute = shellRoute?.children?.find((route) => route.path === 'guide');
    const authenticatedBranch = shellRoute?.children?.find((route) => route.path === '');
    const shellGuideRoute = authenticatedBranch?.children?.find((route) => route.path === 'field-guide');

    expect(publicGuideRoute?.component).toBe(GuidePageComponent);
    expect(shellGuideRoute?.component).toBe(GuidePageComponent);
  });
});

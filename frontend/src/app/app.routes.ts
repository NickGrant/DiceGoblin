import { Routes } from '@angular/router';
import { authChildGuard, authGuard, guestGuard } from './core/guards/auth/auth.guard';
import { LandingPageComponent } from './pages/landing-page/landing-page.component';
import { GuidePageComponent } from './pages/guide-page/guide-page.component';

export const routes: Routes = [
  {
    path: 'login',
    component: LandingPageComponent,
    canActivate: [guestGuard],
    data: {
      audio: {
        musicIntent: 'music.login',
      },
    },
  },
  {
    path: 'guide',
    component: GuidePageComponent,
    data: {
      audio: {
        musicIntent: 'music.home',
      },
    },
  },
  {
    path: '',
    canActivate: [authGuard],
    canActivateChild: [authChildGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'game' },
      {
        path: 'game',
        loadComponent: () =>
          import('./game/game-host.component').then((module) => module.GameHostComponent),
        data: { audio: { musicIntent: null, ambienceIntent: null } },
      },
      { path: '**', redirectTo: 'game' },
    ],
  },
  {
    path: '**',
    redirectTo: 'game',
  },
];


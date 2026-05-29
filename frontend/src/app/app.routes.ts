import { Routes } from '@angular/router';
import { GameShellComponent } from './layout/game-shell.component';
import { DebugPageComponent } from './pages/debug-page.component';
import { DicePageComponent } from './pages/dice-page.component';
import { LandingPageComponent } from './pages/landing-page.component';
import { HomePageComponent } from './pages/home-page.component';
import { RegionsPageComponent } from './pages/regions-page.component';
import { ShopPageComponent } from './pages/shop-page.component';
import { WarbandPageComponent } from './pages/warband-page.component';

export const routes: Routes = [
  {
    path: 'login',
    component: LandingPageComponent,
  },
  {
    path: '',
    component: GameShellComponent,
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'home' },
      { path: 'home', component: HomePageComponent },
      { path: 'regions', component: RegionsPageComponent },
      { path: 'warband', component: WarbandPageComponent },
      { path: 'dice', component: DicePageComponent },
      { path: 'shop', component: ShopPageComponent },
      { path: 'debug', component: DebugPageComponent },
    ],
  },
  {
    path: '**',
    redirectTo: 'home',
  },
];

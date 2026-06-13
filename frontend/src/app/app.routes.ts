import { Routes } from '@angular/router';
import { academyFeatureGuard, authChildGuard, authGuard, guestGuard } from './core/guards/auth/auth.guard';
import { GameShellComponent } from './layout/game-shell/game-shell.component';
import { DebugPageComponent } from './pages/debug-page/debug-page.component';
import { DicePageComponent } from './pages/dice-page/dice-page.component';
import { LandingPageComponent } from './pages/landing-page/landing-page.component';
import { HomePageComponent } from './pages/home-page/home-page.component';
import { AcademyPageComponent } from './pages/academy-page/academy-page.component';
import { RegionsPageComponent } from './pages/regions-page/regions-page.component';
import { RunMapPageComponent } from './pages/run-map-page/run-map-page.component';
import { RunNodePageComponent } from './pages/run-node-page/run-node-page.component';
import { RunRestPageComponent } from './pages/run-rest-page/run-rest-page.component';
import { RunSummaryPageComponent } from './pages/run-summary-page/run-summary-page.component';
import { ShopPageComponent } from './pages/shop-page/shop-page.component';
import { SquadDetailsPageComponent } from './pages/squad-details-page/squad-details-page.component';
import { UnitDetailsPageComponent } from './pages/unit-details-page/unit-details-page.component';
import { WarbandPageComponent } from './pages/warband-page/warband-page.component';

export const routes: Routes = [
  {
    path: 'login',
    component: LandingPageComponent,
    canActivate: [guestGuard],
  },
  {
    path: '',
    component: GameShellComponent,
    canActivate: [authGuard],
    canActivateChild: [authChildGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'home' },
      { path: 'home', component: HomePageComponent },
      { path: 'academy', component: AcademyPageComponent, canActivate: [academyFeatureGuard] },
      { path: 'regions', component: RegionsPageComponent },
      { path: 'warband', component: WarbandPageComponent },
      { path: 'warband/units/:unitId', component: UnitDetailsPageComponent },
      { path: 'warband/squads/:squadId', component: SquadDetailsPageComponent },
      { path: 'dice', component: DicePageComponent },
      { path: 'shop', component: ShopPageComponent },
      { path: 'run/map', component: RunMapPageComponent },
      { path: 'run/node/:nodeId', component: RunNodePageComponent },
      { path: 'run/rest/:nodeId', component: RunRestPageComponent },
      { path: 'run/summary', component: RunSummaryPageComponent },
      { path: 'debug', component: DebugPageComponent },
    ],
  },
  {
    path: '**',
    redirectTo: 'home',
  },
];


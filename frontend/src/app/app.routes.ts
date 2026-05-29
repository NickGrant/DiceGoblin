import { Routes } from '@angular/router';
import { GameShellComponent } from './layout/game-shell.component';
import { DebugPageComponent } from './pages/debug-page.component';
import { DicePageComponent } from './pages/dice-page.component';
import { LandingPageComponent } from './pages/landing-page.component';
import { HomePageComponent } from './pages/home-page.component';
import { RegionsPageComponent } from './pages/regions-page.component';
import { RunMapPageComponent } from './pages/run-map-page.component';
import { RunNodePageComponent } from './pages/run-node-page.component';
import { RunRestPageComponent } from './pages/run-rest-page.component';
import { RunSummaryPageComponent } from './pages/run-summary-page.component';
import { ShopPageComponent } from './pages/shop-page.component';
import { SquadDetailsPageComponent } from './pages/squad-details-page.component';
import { UnitDetailsPageComponent } from './pages/unit-details-page.component';
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

import { Routes } from '@angular/router';
import { academyFeatureGuard, authChildGuard, authGuard, guestGuard, shopFeatureGuard, wrongMachineFeatureGuard } from './core/guards/auth/auth.guard';
import { DebugPageComponent } from './pages/debug-page/debug-page.component';
import { DicePageComponent } from './pages/dice-page/dice-page.component';
import { LandingPageComponent } from './pages/landing-page/landing-page.component';
import { HomePageComponent } from './pages/home-page/home-page.component';
import { AcademyPageComponent } from './pages/academy-page/academy-page.component';
import { CodexPageComponent } from './pages/codex-page/codex-page.component';
import { GuidePageComponent } from './pages/guide-page/guide-page.component';
import { RegionsPageComponent } from './pages/regions-page/regions-page.component';
import { RunDialoguePageComponent } from './pages/run-dialogue-page/run-dialogue-page.component';
import { RunLootPageComponent } from './pages/run-loot-page/run-loot-page.component';
import { RunMapPageComponent } from './pages/run-map-page/run-map-page.component';
import { RunNodePageComponent } from './pages/run-node-page/run-node-page.component';
import { RunRestPageComponent } from './pages/run-rest-page/run-rest-page.component';
import { RunSummaryPageComponent } from './pages/run-summary-page/run-summary-page.component';
import { ShopPageComponent } from './pages/shop-page/shop-page.component';
import { SquadDetailsPageComponent } from './pages/squad-details-page/squad-details-page.component';
import { UnitDetailsPageComponent } from './pages/unit-details-page/unit-details-page.component';
import { WarbandPageComponent } from './pages/warband-page/warband-page.component';
import { WrongMachinePageComponent } from './pages/wrong-machine-page/wrong-machine-page.component';

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
      { path: '', pathMatch: 'full', redirectTo: 'home' },
      { path: 'home', component: HomePageComponent, data: { audio: { musicIntent: 'music.home' } } },
      {
        path: 'codex',
        component: CodexPageComponent,
        data: {
          audio: { musicIntent: 'music.home' },
        },
      },
      { path: 'field-guide', redirectTo: 'codex', pathMatch: 'full' },
      {
        path: 'academy',
        component: AcademyPageComponent,
        canActivate: [academyFeatureGuard],
        data: { audio: { musicIntent: 'music.home' } },
      },
      { path: 'regions', component: RegionsPageComponent, data: { audio: { musicIntent: 'music.regions' } } },
      { path: 'warband', component: WarbandPageComponent, data: { audio: { musicIntent: 'music.home' } } },
      {
        path: 'warband/units/:unitId',
        component: UnitDetailsPageComponent,
        data: { audio: { musicIntent: 'music.home' } },
      },
      {
        path: 'warband/squads/:squadId',
        component: SquadDetailsPageComponent,
        data: { audio: { musicIntent: 'music.home' } },
      },
      { path: 'dice', component: DicePageComponent, data: { audio: { musicIntent: 'music.home' } } },
      {
        path: 'shop',
        component: ShopPageComponent,
        canActivate: [shopFeatureGuard],
        data: { audio: { musicIntent: 'music.home' } },
      },
      {
        path: 'wrong-machine',
        component: WrongMachinePageComponent,
        canActivate: [wrongMachineFeatureGuard],
        data: { audio: { musicIntent: 'music.home' } },
      },
      { path: 'run/map', component: RunMapPageComponent, data: { audio: { musicIntent: 'music.run' } } },
      {
        path: 'run/dialogue/:nodeId',
        component: RunDialoguePageComponent,
        data: { audio: { musicIntent: 'music.run' } },
      },
      {
        path: 'run/node/:nodeId',
        component: RunNodePageComponent,
        data: { audio: { musicIntent: 'music.battle.normal' } },
      },
      {
        path: 'run/loot/:nodeId',
        component: RunLootPageComponent,
        data: { audio: { musicIntent: 'music.run' } },
      },
      {
        path: 'run/rest/:nodeId',
        component: RunRestPageComponent,
        data: { audio: { musicIntent: 'music.run' } },
      },
      {
        path: 'run/summary',
        component: RunSummaryPageComponent,
        data: { audio: { musicIntent: 'music.summary' } },
      },
      { path: 'debug', component: DebugPageComponent, data: { audio: { musicIntent: 'music.home' } } },
    ],
  },
  {
    path: '**',
    redirectTo: 'home',
  },
];


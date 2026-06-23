import { Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { RegionUnlockRecord } from '../../core/models/api.models';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';

type RegionCard = {
  slug: string;
  name: string;
  theme: string;
  recommendedLevel: number;
  energyCost: number;
  summary: string;
  pathSummary: string;
  unlockHint: string;
};

type RegionCardViewModel = RegionCard & {
  regionId: string | null;
  isUnlocked: boolean;
};

const REGION_CARDS: RegionCard[] = [
  {
    slug: 'the_farm',
    name: 'The Farm',
    theme: 'farm',
    recommendedLevel: 1,
    energyCost: 3,
    summary: 'Combat, loot, rest, boss, then exit.',
    pathSummary: 'Tutorial route',
    unlockHint: 'Available from the start.',
  },
  {
    slug: 'mountains',
    name: 'Mountains',
    theme: 'mountain',
    recommendedLevel: 1,
    energyCost: 5,
    summary: 'Branching climbs with tougher fights and a boss reward that unlocks the swamps.',
    pathSummary: 'Kobold ascent',
    unlockHint: 'Complete The Farm to unlock.',
  },
  {
    slug: 'swamps',
    name: 'Swamps',
    theme: 'swamp',
    recommendedLevel: 1,
    energyCost: 5,
    summary: 'Branching marsh paths with frogman encounters, rest stops, and a final boss.',
    pathSummary: 'Frogman marsh',
    unlockHint: 'Complete Mountains to unlock.',
  },
];

@Component({
  selector: 'app-regions-page',
  standalone: true,
  imports: [DatePipe, DgAlertComponent, DgCommandBtnDirective, PageFrameComponent],
  templateUrl: './regions-page.component.html',
  styleUrl: './regions-page.component.scss',
})
export class RegionsPageComponent {
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly profileData = this.sessionService.profileData;
  readonly isStarting = signal(false);
  readonly startingSlug = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly regions = computed(() => {
    const unlocks = this.profileData()?.region_unlocks ?? [];
    return REGION_CARDS.map((region) => {
      const unlock = unlocks.find((entry) => entry.region_slug === region.slug) ?? null;
      return {
        ...region,
        regionId: unlock?.region_id ?? null,
        isUnlocked: !!unlock,
      };
    });
  });
  readonly unlockedRegionCount = computed(() => this.regions().filter((region) => region.isUnlocked).length);

  isActiveRegion(regionId: string | null): boolean {
    return this.profileData()?.active_run?.region_id === regionId;
  }

  regionActionLabel(region: RegionCardViewModel): string {
    if (this.startingSlug() === region.slug) {
      return 'Starting...';
    }

    if (this.isActiveRegion(region.regionId)) {
      return 'Continue Run';
    }

    return 'Start Run';
  }

  regionActionDisabled(region: RegionCardViewModel): boolean {
    if (this.startingSlug() === region.slug) {
      return true;
    }

    if (!region.isUnlocked) {
      return true;
    }

    if (this.isActiveRegion(region.regionId)) {
      return false;
    }

    return this.isStarting() || this.hasActiveRun();
  }

  async startRegionRun(regionId: string | null, slug: string): Promise<void> {
    if (!regionId) {
      return;
    }

    this.isStarting.set(true);
    this.startingSlug.set(slug);
    this.message.set(null);
    this.error.set(null);

    try {
      const response = await this.runService.createRun(Number(regionId));
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set('Run started.');
      await this.router.navigateByUrl('/run/map');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to start run.');
    } finally {
      this.isStarting.set(false);
      this.startingSlug.set(null);
    }
  }

  async continueRun(): Promise<void> {
    await this.router.navigateByUrl('/run/map');
  }

  async activateRegion(region: RegionCardViewModel): Promise<void> {
    if (this.isActiveRegion(region.regionId)) {
      await this.continueRun();
      return;
    }

    await this.startRegionRun(region.regionId, region.slug);
  }

  onGridWheel(event: WheelEvent): void {
    const rail = event.currentTarget;
    if (!(rail instanceof HTMLElement) || rail.scrollWidth <= rail.clientWidth) {
      return;
    }

    const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
    if (delta === 0) {
      return;
    }

    event.preventDefault();
    rail.scrollLeft += delta;
  }

  unlockRecord(regionSlug: string): RegionUnlockRecord | null {
    return this.profileData()?.region_unlocks.find((entry) => entry.region_slug === regionSlug) ?? null;
  }
}


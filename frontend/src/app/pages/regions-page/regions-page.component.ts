import { Component, computed, inject, signal } from '@angular/core';
import { DatePipe } from '@angular/common';
import { Router } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { RegionUnlockRecord } from '../../core/models/api.models';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

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
  imports: [DatePipe, DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent],
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
  readonly currentRegionIndex = signal(0);
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
  readonly currentRegion = computed(() => this.regions()[this.currentRegionIndex()] ?? null);
  readonly currentRegionActionLabel = computed(() => {
    const region = this.currentRegion();
    if (!region) {
      return 'Start Run';
    }

    if (this.startingSlug() === region.slug) {
      return 'Starting...';
    }

    if (this.isActiveRegion(region.regionId)) {
      return 'Continue Run';
    }

    return 'Start Run';
  });
  readonly currentRegionActionDisabled = computed(() => {
    const region = this.currentRegion();
    if (!region) {
      return true;
    }

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
  });

  isActiveRegion(regionId: string | null): boolean {
    return this.profileData()?.active_run?.region_id === regionId;
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

  previousRegion(): void {
    this.currentRegionIndex.update((index) => {
      const count = this.regions().length;
      return count > 0 ? (index - 1 + count) % count : 0;
    });
  }

  nextRegion(): void {
    this.currentRegionIndex.update((index) => {
      const count = this.regions().length;
      return count > 0 ? (index + 1) % count : 0;
    });
  }

  goToRegion(index: number): void {
    const count = this.regions().length;
    if (index < 0 || index >= count) {
      return;
    }

    this.currentRegionIndex.set(index);
  }

  async activateCurrentRegion(): Promise<void> {
    const region = this.currentRegion();
    if (!region) {
      return;
    }

    if (this.isActiveRegion(region.regionId)) {
      await this.continueRun();
      return;
    }

    await this.startRegionRun(region.regionId, region.slug);
  }

  unlockRecord(regionSlug: string): RegionUnlockRecord | null {
    return this.profileData()?.region_unlocks.find((entry) => entry.region_slug === regionSlug) ?? null;
  }
}


import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { RegionRecord } from '../../core/models/api.models';
import { REGION_SUMMARY_BY_SLUG } from '../../core/regions/region-catalog';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ConfirmModalComponent } from '../../shared/ui/confirm-modal/confirm-modal.component';

type RegionCardViewModel = RegionRecord & {
  summary: string;
};

@Component({
  selector: 'app-regions-page',
  standalone: true,
  imports: [DgAlertComponent, PageFrameComponent, ConfirmModalComponent],
  templateUrl: './regions-page.component.html',
  styleUrl: './regions-page.component.scss',
})
export class RegionsPageComponent {
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly session = this.sessionService.session;
  readonly profileData = this.sessionService.profileData;
  readonly isStarting = signal(false);
  readonly startingSlug = signal<string | null>(null);
  readonly hoveredSlug = signal<string | null>(null);
  readonly pendingRegionSlug = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly regions = computed<RegionCardViewModel[]>(() =>
    (this.profileData()?.regions ?? []).map((region) => ({
      ...region,
      summary:
        REGION_SUMMARY_BY_SLUG[region.slug] ??
        'Path details will be added as this biome is expanded.',
    })),
  );
  readonly inspectedRegion = computed(() => {
    const regions = this.regions();
    if (!regions.length) {
      return null;
    }

    const hoveredSlug = this.hoveredSlug();
    if (hoveredSlug) {
      const hoveredRegion = regions.find((region) => region.slug === hoveredSlug);
      if (hoveredRegion) {
        return hoveredRegion;
      }
    }

    return regions[0] ?? null;
  });
  readonly pendingRegion = computed(
    () => this.regions().find((region) => region.slug === this.pendingRegionSlug()) ?? null,
  );

  constructor() {}

  isActiveRegion(regionId: string | null): boolean {
    return this.profileData()?.active_run?.region_id === regionId;
  }

  regionActionLabel(region: RegionCardViewModel): string {
    if (this.startingSlug() === region.slug) {
      return 'Starting...';
    }

    if (this.isActiveRegion(region.id)) {
      return 'Continue Run';
    }

    return 'Start Run';
  }

  regionActionDisabled(region: RegionCardViewModel): boolean {
    if (this.startingSlug() === region.slug) {
      return true;
    }

    if (!region.is_unlocked) {
      return true;
    }

    if (this.isActiveRegion(region.id)) {
      return false;
    }

    return this.isStarting() || this.hasActiveRun();
  }

  regionStateLabel(region: RegionCardViewModel): string {
    if (this.isActiveRegion(region.id)) {
      return 'Current Run';
    }

    if (region.is_unlocked) {
      return region.is_completed ? 'Cleared' : 'Unlocked';
    }

    return 'Locked';
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
    if (this.regionActionDisabled(region)) {
      return;
    }

    if (this.isActiveRegion(region.id)) {
      await this.continueRun();
      return;
    }

    this.pendingRegionSlug.set(region.slug);
  }

  previewRegion(regionSlug: string): void {
    this.hoveredSlug.set(regionSlug);
  }

  closeStartRunConfirm(): void {
    if (this.isStarting()) {
      return;
    }

    this.pendingRegionSlug.set(null);
  }

  async confirmStartRun(): Promise<void> {
    const region = this.regions().find((entry) => entry.slug === this.pendingRegionSlug()) ?? null;
    if (!region?.id) {
      this.pendingRegionSlug.set(null);
      return;
    }

    await this.startRegionRun(region.id, region.slug);
    this.pendingRegionSlug.set(null);
  }
}

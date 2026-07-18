import { Component, computed, effect, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { RegionRecord } from '../../core/models/api.models';
import { REGION_SUMMARY_BY_SLUG } from '../../core/regions/region-catalog';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ConfirmModalComponent } from '../../shared/ui/confirm-modal/confirm-modal.component';
import { DialogueChoiceSelection, DialogueScript } from '../../core/dialogue/dialogue.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';

type RegionCardViewModel = RegionRecord & {
  summary: string;
};

@Component({
  selector: 'app-regions-page',
  standalone: true,
  imports: [DgAlertComponent, PageFrameComponent, ConfirmModalComponent, DgDialogueStageComponent],
  templateUrl: './regions-page.component.html',
  styleUrl: './regions-page.component.scss',
})
export class RegionsPageComponent {
  private static readonly START_RUN_INTRO_ID = 'start-run-kickoff';
  private static readonly START_RUN_INTRO_PORTRAIT =
    '/assets/dialogue/portraits/goblin/primordial_frame_0.png';
  private static readonly MOUNTAINS_ARCHIVIST_DIALOGUE_ID = 'mountains-archivist-first-contact';
  private static readonly PLAYER_DIALOGUE_PORTRAIT =
    '/assets/dialogue/portraits/goblin/base_frame_0.png';

  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);
  private startRunIntroChecked = false;

  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly session = this.sessionService.session;
  readonly profileData = this.sessionService.profileData;
  readonly isStarting = signal(false);
  readonly startingSlug = signal<string | null>(null);
  readonly hoveredSlug = signal<string | null>(null);
  readonly pendingRegionSlug = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly startRunIntroDialogue = signal<DialogueScript | null>(null);
  readonly pendingRegionDialogue = signal<DialogueScript | null>(null);
  readonly deferredStartRegionSlug = signal<string | null>(null);
  readonly activeDialogue = computed(
    () => this.pendingRegionDialogue() ?? this.startRunIntroDialogue(),
  );
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

  constructor() {
    effect(() => {
      const userId = this.session().userId?.trim() ?? '';
      const profile = this.profileData();
      if (!userId || !profile || this.hasActiveRun() || this.startRunIntroChecked) {
        return;
      }

      this.startRunIntroChecked = true;
      void this.loadStartRunIntro(userId);
    });
  }

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
    if (this.activeDialogue()) {
      return true;
    }

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
    if (this.activeDialogue() || this.regionActionDisabled(region)) {
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
    if (this.activeDialogue()) {
      return;
    }

    const region = this.regions().find((entry) => entry.slug === this.pendingRegionSlug()) ?? null;
    if (!region?.id) {
      this.pendingRegionSlug.set(null);
      return;
    }

    if (await this.tryOpenRegionDialogue(region.slug)) {
      this.pendingRegionSlug.set(null);
      this.deferredStartRegionSlug.set(region.slug);
      return;
    }

    await this.startRegionRun(region.id, region.slug);
    this.pendingRegionSlug.set(null);
  }

  async handleStartRunIntroComplete(_choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    await this.persistStartRunIntroSeen();
    this.startRunIntroDialogue.set(null);
  }

  async handlePendingRegionDialogueComplete(
    _choiceHistory: DialogueChoiceSelection[],
  ): Promise<void> {
    const regionSlug = this.deferredStartRegionSlug();
    this.pendingRegionDialogue.set(null);
    this.deferredStartRegionSlug.set(null);

    try {
      await this.persistDialogueSeen(RegionsPageComponent.MOUNTAINS_ARCHIVIST_DIALOGUE_ID);
    } finally {
      const region = this.regions().find((entry) => entry.slug === regionSlug) ?? null;
      if (region?.id) {
        await this.startRegionRun(region.id, region.slug);
      }
    }
  }

  private async loadStartRunIntro(_userId: string): Promise<void> {
    if (this.hasSeenDialogue(RegionsPageComponent.START_RUN_INTRO_ID)) {
      return;
    }

    try {
      const dialogue = await this.dialogueService.getDialogue({
        scene: 'start-run',
        tags: ['kickoff'],
        playerName: this.session().displayName,
        playerPortraitUrl: RegionsPageComponent.START_RUN_INTRO_PORTRAIT,
      });

      if (dialogue) {
        this.startRunIntroDialogue.set(dialogue);
      }
    } catch {
      // Keep the regions page usable even if dialogue assets fail to load.
    }
  }

  private hasSeenDialogue(dialogueId: string): boolean {
    return (this.profileData()?.seen_dialogues ?? []).includes(dialogueId);
  }

  private async persistStartRunIntroSeen(): Promise<void> {
    await this.persistDialogueSeen(RegionsPageComponent.START_RUN_INTRO_ID);
  }

  private async persistDialogueSeen(dialogueId: string): Promise<void> {
    try {
      await this.dialogueService.markDialogueSeen(dialogueId);
      await this.sessionService.refreshProfile({ force: true });
    } catch {
      // Keep the page usable even if the persistence call fails during testing.
    }
  }

  private async tryOpenRegionDialogue(regionSlug: string): Promise<boolean> {
    if (regionSlug !== 'mountains') {
      return false;
    }

    if (this.hasSeenDialogue(RegionsPageComponent.MOUNTAINS_ARCHIVIST_DIALOGUE_ID)) {
      return false;
    }

    try {
      const dialogue = await this.dialogueService.getDialogue({
        scene: 'start-run',
        regionSlug,
        tags: ['first-visit'],
        playerName: this.session().displayName,
        playerPortraitUrl: RegionsPageComponent.PLAYER_DIALOGUE_PORTRAIT,
      });

      if (!dialogue) {
        return false;
      }

      this.pendingRegionDialogue.set(dialogue);
      return true;
    } catch {
      return false;
    }
  }
}

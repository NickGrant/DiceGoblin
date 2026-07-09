import { Component, computed, effect, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { RegionUnlockRecord } from '../../core/models/api.models';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ConfirmModalComponent } from '../../shared/ui/confirm-modal/confirm-modal.component';
import { DialogueChoiceSelection, DialogueScript } from '../../core/dialogue/dialogue.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';

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
  imports: [DgAlertComponent, PageFrameComponent, ConfirmModalComponent, DgDialogueStageComponent],
  templateUrl: './regions-page.component.html',
  styleUrl: './regions-page.component.scss',
})
export class RegionsPageComponent {
  private static readonly START_RUN_INTRO_ID = 'start-run-kickoff';
  private static readonly START_RUN_INTRO_PORTRAIT = '/assets/dialogue/portraits/goblin/primordial_frame_0.png';
  private static readonly MOUNTAINS_ARCHIVIST_DIALOGUE_ID = 'mountains-archivist-first-contact';
  private static readonly PLAYER_DIALOGUE_PORTRAIT = '/assets/dialogue/portraits/goblin/base_frame_0.png';

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
  readonly activeDialogue = computed(() => this.pendingRegionDialogue() ?? this.startRunIntroDialogue());
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
  readonly pendingRegion = computed(() => this.regions().find((region) => region.slug === this.pendingRegionSlug()) ?? null);

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

    if (this.isActiveRegion(region.regionId)) {
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
    if (this.activeDialogue() || this.regionActionDisabled(region)) {
      return;
    }

    if (this.isActiveRegion(region.regionId)) {
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
    if (!region?.regionId) {
      this.pendingRegionSlug.set(null);
      return;
    }

    if (await this.tryOpenRegionDialogue(region.slug)) {
      this.pendingRegionSlug.set(null);
      this.deferredStartRegionSlug.set(region.slug);
      return;
    }

    await this.startRegionRun(region.regionId, region.slug);
    this.pendingRegionSlug.set(null);
  }

  unlockRecord(regionSlug: string): RegionUnlockRecord | null {
    return this.profileData()?.region_unlocks.find((entry) => entry.region_slug === regionSlug) ?? null;
  }

  async handleStartRunIntroComplete(_choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    this.startRunIntroDialogue.set(null);
    this.rememberDialogueSeenLocally(RegionsPageComponent.START_RUN_INTRO_ID);
    await this.persistStartRunIntroSeen();
  }

  async handlePendingRegionDialogueComplete(_choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    const regionSlug = this.deferredStartRegionSlug();
    this.pendingRegionDialogue.set(null);
    this.deferredStartRegionSlug.set(null);
    this.rememberDialogueSeenLocally(RegionsPageComponent.MOUNTAINS_ARCHIVIST_DIALOGUE_ID);

    try {
      await this.persistDialogueSeen(RegionsPageComponent.MOUNTAINS_ARCHIVIST_DIALOGUE_ID);
    } finally {
      const region = this.regions().find((entry) => entry.slug === regionSlug) ?? null;
      if (region?.regionId) {
        await this.startRegionRun(region.regionId, region.slug);
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
    return (this.profileData()?.seen_dialogues ?? []).includes(dialogueId) || this.hasSeenDialogueLocally(dialogueId);
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

  private hasSeenDialogueLocally(dialogueId: string): boolean {
    if (typeof window === 'undefined') {
      return false;
    }

    try {
      return window.sessionStorage.getItem(this.dialogueSeenStorageKey(dialogueId)) === '1';
    } catch {
      return false;
    }
  }

  private rememberDialogueSeenLocally(dialogueId: string): void {
    if (typeof window === 'undefined') {
      return;
    }

    try {
      window.sessionStorage.setItem(this.dialogueSeenStorageKey(dialogueId), '1');
    } catch {
      // Keep the page usable if session storage is unavailable.
    }
  }

  private dialogueSeenStorageKey(dialogueId: string): string {
    const userId = this.session().userId?.trim() ?? 'guest';
    return `dialogue-seen:${userId}:${dialogueId}`;
  }
}


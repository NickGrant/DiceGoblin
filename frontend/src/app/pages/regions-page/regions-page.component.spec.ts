import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { RegionsPageComponent } from './regions-page.component';
import { DialogueScript } from '../../core/dialogue/dialogue.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';

class RunServiceStub {
  createRun = jasmine.createSpy('createRun').and.resolveTo({ ok: true });
}

class DialogueServiceStub {
  getDialogue = jasmine.createSpy('getDialogue').and.resolveTo(null);
  markDialogueSeen = jasmine.createSpy('markDialogueSeen').and.resolveTo(undefined);
}

class SessionServiceStub {
  readonly hasActiveRun = signal(false);
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Ravin',
    userId: '42',
    csrfToken: 'token',
  });
  readonly profileData = signal<any>({
    active_run: null,
    seen_dialogues: [],
    regions: [
      { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
      { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
      { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
    ],
    region_unlocks: [
      { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
      { region_id: '2', region_slug: 'mountains', unlocked_at: '2026-06-02T00:00:00Z' },
    ],
  });
  refreshProfile = jasmine.createSpy('refreshProfile').and.resolveTo(undefined);
}

describe('RegionsPageComponent', () => {
  let router: Router;
  let runService: RunServiceStub;
  let dialogueService: DialogueServiceStub;
  let sessionService: SessionServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RegionsPageComponent],
      providers: [
        provideRouter([]),
        { provide: RunService, useClass: RunServiceStub },
        { provide: DialogueService, useClass: DialogueServiceStub },
        { provide: SessionService, useClass: SessionServiceStub },
      ],
    }).compileComponents();

    router = TestBed.inject(Router);
    runService = TestBed.inject(RunService) as unknown as RunServiceStub;
    dialogueService = TestBed.inject(DialogueService) as unknown as DialogueServiceStub;
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
  });

  it('computes unlocked regions from profile data', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.regions().filter((region) => region.is_unlocked).length).toBe(2);
    expect(component.regions().find((region) => region.slug === 'mountains')?.is_unlocked).toBeTrue();
    expect(component.regions().find((region) => region.slug === 'swamps')?.is_unlocked).toBeFalse();
  });

  it('starts a run and routes to the map for unlocked regions', async () => {
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    await fixture.componentInstance.startRegionRun('2', 'mountains');

    expect(runService.createRun).toHaveBeenCalledWith(2);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
  });

  it('shows continue run only for the active region tile', async () => {
    const sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    sessionService.hasActiveRun.set(true);
    sessionService.profileData.set({
      active_run: { region_id: '2' },
      seen_dialogues: [],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', unlocked_at: '2026-06-02T00:00:00Z' },
      ],
    });

    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const farm = component.regions().find((region) => region.slug === 'the_farm')!;
    const mountains = component.regions().find((region) => region.slug === 'mountains')!;

    expect(component.regionActionLabel(farm)).toBe('Start Run');
    expect(component.regionActionDisabled(farm)).toBeTrue();
    expect(component.regionActionLabel(mountains)).toBe('Continue Run');
    expect(component.regionActionDisabled(mountains)).toBeFalse();
  });

  it('defaults inspection to the first region and uses hover preview', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    expect(component.inspectedRegion()?.slug).toBe('the_farm');

    component.previewRegion('mountains');
    expect(component.inspectedRegion()?.slug).toBe('mountains');
  });

  it('opens a confirm state before starting a new run', async () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const mountains = component.regions().find((region) => region.slug === 'mountains')!;

    await component.activateRegion(mountains);

    expect(component.pendingRegion()?.slug).toBe('mountains');
  });

  it('marks locked region art with lock styling classes', () => {
    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();
    fixture.componentInstance.previewRegion('swamps');
    fixture.detectChanges();

    const host: HTMLElement = fixture.nativeElement;
    const lockedTileBadge = host.querySelector('.region-page__tile.is-locked .region-page__tile-badge');
    const lockedInspectImage = host.querySelector('.region-page__inspect-image.is-locked');

    expect(lockedTileBadge?.classList.contains('is-locked')).toBeTrue();
    expect(lockedInspectImage).not.toBeNull();
  });

  it('shows the kickoff dialogue and marks it seen on completion', async () => {
    const kickoffDialogue: DialogueScript = {
      id: 'start-run-kickoff',
      backgroundUrl: '/assets/ui/biome/mystic_cave.png',
      startStepId: 'intro',
      speakers: [
        { id: 'player', side: 'left', name: 'Ravin', portraitUrl: '/assets/dialogue/portraits/goblin/primordial_frame_0.png', party: 'player', role: 'player' },
        { id: 'whim', side: 'right', name: 'The Whim', portraitUrl: '/assets/dialogue/portraits/whim/frame_0.png', party: 'neutral', role: 'npc' },
      ],
      steps: [
        {
          id: 'intro',
          speakerId: 'whim',
          text: 'In the beginning all was chaos...',
          nextStepId: null,
          choices: [],
          enterEffect: null,
        },
      ],
    };
    dialogueService.getDialogue.and.resolveTo(kickoffDialogue);

    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(dialogueService.getDialogue).toHaveBeenCalledWith(
      jasmine.objectContaining({
        scene: 'start-run',
        tags: ['kickoff'],
        playerName: 'Ravin',
      }),
    );
    expect(fixture.componentInstance.startRunIntroDialogue()?.id).toBe('start-run-kickoff');

    const host: HTMLElement = fixture.nativeElement;
    expect(host.querySelector('.region-page-shell--dialogue-open')).not.toBeNull();
    expect(host.querySelector('.region-page__dialogue[role="dialog"]')).not.toBeNull();

    await fixture.componentInstance.handleStartRunIntroComplete([]);

    expect(dialogueService.markDialogueSeen).toHaveBeenCalledWith('start-run-kickoff');
    expect(sessionService.refreshProfile).toHaveBeenCalledWith({ force: true });
    expect(fixture.componentInstance.startRunIntroDialogue()).toBeNull();
  });

  it('skips the kickoff dialogue after the account has already seen it', async () => {
    sessionService.profileData.set({
      active_run: null,
      seen_dialogues: ['start-run-kickoff'],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
      ],
    });

    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    expect(dialogueService.getDialogue).not.toHaveBeenCalled();
    expect(fixture.componentInstance.startRunIntroDialogue()).toBeNull();
  });

  it('shows the mountains archivist dialogue once before starting that run', async () => {
    spyOn(router, 'navigateByUrl').and.resolveTo(true);
    sessionService.profileData.set({
      active_run: null,
      seen_dialogues: ['start-run-kickoff'],
      regions: [
        { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
        { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: false, unlocked_at: '2026-06-02T00:00:00Z' },
        { id: '3', slug: 'swamps', name: 'Swamps', theme: 'swamp', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: false, is_completed: false, unlocked_at: null },
      ],
      region_unlocks: [
        { region_id: '1', region_slug: 'the_farm', unlocked_at: '2026-06-01T00:00:00Z' },
        { region_id: '2', region_slug: 'mountains', unlocked_at: '2026-06-02T00:00:00Z' },
      ],
    });
    dialogueService.getDialogue.and.resolveTo({
      id: 'mountains-archivist-first-contact',
      backgroundUrl: '/assets/ui/biome/mountain.png',
      startStepId: 'archivist-extinct',
      speakers: [
        { id: 'player', side: 'left', name: 'Ravin', portraitUrl: '/assets/dialogue/portraits/goblin/base_frame_0.png', party: 'player', role: 'player' },
        { id: 'archivist', side: 'right', name: 'The Archivist', portraitUrl: '/assets/dialogue/portraits/archivist/frame_0.png', party: 'neutral', role: 'npc' },
      ],
      steps: [
        {
          id: 'archivist-extinct',
          speakerId: 'archivist',
          text: 'Oh. A goblin. You should be extinct.',
          nextStepId: null,
          choices: [],
          enterEffect: null,
        },
      ],
    });

    const fixture = TestBed.createComponent(RegionsPageComponent);
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    dialogueService.getDialogue.calls.reset();
    fixture.componentInstance.pendingRegionSlug.set('mountains');

    await fixture.componentInstance.confirmStartRun();

    expect(dialogueService.getDialogue).toHaveBeenCalledWith(
      jasmine.objectContaining({
        scene: 'start-run',
        regionSlug: 'mountains',
        tags: ['first-visit'],
        playerName: 'Ravin',
      }),
    );
    expect(fixture.componentInstance.pendingRegionDialogue()?.id).toBe('mountains-archivist-first-contact');
    expect(runService.createRun).not.toHaveBeenCalled();
    expect(fixture.componentInstance.pendingRegionSlug()).toBeNull();

    await fixture.componentInstance.handlePendingRegionDialogueComplete([]);

    expect(dialogueService.markDialogueSeen).toHaveBeenCalledWith('mountains-archivist-first-contact');
    expect(runService.createRun).toHaveBeenCalledWith(2);
    expect(router.navigateByUrl).toHaveBeenCalledWith('/run/map');
    expect(fixture.componentInstance.pendingRegionDialogue()).toBeNull();
  });
});

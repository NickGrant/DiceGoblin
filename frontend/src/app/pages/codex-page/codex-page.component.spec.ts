import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { SessionService } from '../../core/services/session/session.service';
import { CodexPageComponent } from './codex-page.component';

class SessionServiceStub {
  readonly session = signal({
    isAuthenticated: true,
    displayName: 'Commander',
    userId: 5,
    csrfToken: 'token',
  });
  readonly hasActiveRun = signal(false);
  readonly profileData = signal<any>({
    feature_unlocks: ['academy', 'sell_bonus'],
    unit_type_unlocks: ['support_banner_t1'],
    seen_dialogues: ['start-run-kickoff', 'mountains-archivist-first-contact', 'farm-shop-unlock'],
    dice: [
      { id: 'd1', affixes: [{ name: 'Bulwark', affix_slug: 'bulwark', value: 1 }] },
      { id: 'd2', affixes: [{ name: 'Execute', affix_slug: 'execute', value: 1 }] },
    ],
    regions: [
      { id: '1', slug: 'the_farm', name: 'The Farm', theme: 'farm', recommended_level: 1, energy_cost: 3, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-01T00:00:00Z' },
      { id: '2', slug: 'mountains', name: 'Mountains', theme: 'mountain', recommended_level: 1, energy_cost: 5, is_enabled: true, is_unlocked: true, is_completed: true, unlocked_at: '2026-06-02T00:00:00Z' },
    ],
    region_unlocks: [
      { region_id: '1', region_slug: 'the_farm', region_name: 'The Farm', unlocked_at: '2026-06-01T00:00:00Z' },
      { region_id: '2', region_slug: 'mountains', region_name: 'Mountains', unlocked_at: '2026-06-02T00:00:00Z' },
    ],
    active_run: { region_name: 'Mountains' },
    objectives: [
      {
        id: 'equip-first-die',
        title: 'Equip a die',
        description: 'Attach at least one die to a raider ability before pushing deeper.',
        status: 'complete',
        priority: 100,
        progress_current: 1,
        progress_target: 1,
        route: '/dice',
        meta: {},
      },
      {
        id: 'claim-first-victory',
        title: 'Claim a battle victory',
        description: 'Resolve and claim one victorious combat reward to grow the warband.',
        status: 'active',
        priority: 45,
        progress_current: 0,
        progress_target: 1,
        route: '/run/map',
        meta: {},
      },
    ],
    codex: {
      owned_entries: [],
      owned_by_type: {
        feature: ['academy', 'sell_bonus'],
        unit_type: ['support_banner_t1'],
        affix: ['bulwark_plus', 'execute_below_half'],
        enemy: ['mudwrestler', 'mudslinger', 'mudking'],
        lore: ['start-run-kickoff', 'mountains-archivist-first-contact', 'farm-shop-unlock'],
        biome: [],
        kin: [],
        item: [],
      },
    },
  });
  readonly initialize = jasmine.createSpy('initialize').and.resolveTo();
}

class DialogueServiceStub {
  readonly loreScripts = [
    {
      id: 'start-run-kickoff',
      title: "The Whim's First Fragment",
      summary: 'The Whim explains what happened to goblinkind.',
      tags: ['lore'],
      backgroundUrl: '/assets/ui/biome/mystic_cave.png',
      speakers: [
        { id: 'player', side: 'left' as const, name: 'Commander', portraitUrl: null, party: 'player', role: 'player' },
        { id: 'whim', side: 'right' as const, name: 'The Whim', portraitUrl: '/assets/ui/units/animated/whim/base/frame_0.png', party: 'neutral', role: 'npc' },
      ],
      startStepId: 'start',
      steps: [{ id: 'start', speakerId: 'whim', text: 'Go make a mess.', nextStepId: null, choices: [], enterEffect: null }],
    },
    {
      id: 'mountains-archivist-first-contact',
      title: 'The Archivist Takes Notice',
      summary: 'The Archivist discovers that goblins still exist.',
      tags: ['lore'],
      backgroundUrl: '/assets/ui/biome/mountain.png',
      speakers: [
        { id: 'archivist', side: 'right' as const, name: 'The Archivist', portraitUrl: '/assets/ui/units/animated/archivist/base/frame_0.png', party: 'neutral', role: 'npc' },
      ],
      startStepId: 'start',
      steps: [{ id: 'start', speakerId: 'archivist', text: 'Remarkable.', nextStepId: null, choices: [], enterEffect: null }],
    },
    {
      id: 'farm-shop-unlock',
      title: 'The Tooth Collector Freed',
      summary: 'The Mudking releases The Tooth Collector.',
      tags: ['lore'],
      backgroundUrl: '/assets/ui/biome/farm.png',
      speakers: [
        { id: 'tooth-collector', side: 'left' as const, name: 'The Tooth Collector', portraitUrl: '/assets/ui/units/animated/tooth_collector/base/frame_0.png', party: 'neutral', role: 'npc' },
      ],
      startStepId: 'start',
      steps: [{ id: 'start', speakerId: 'tooth-collector', text: 'Finally.', nextStepId: null, choices: [], enterEffect: null }],
    },
  ];
  readonly getLoreDialogues = jasmine.createSpy('getLoreDialogues').and.callFake((dialogueIds: ReadonlyArray<string>) =>
    Promise.resolve(this.loreScripts.filter((script) => dialogueIds.includes(script.id))),
  );
}

describe('CodexPageComponent', () => {
  let sessionService: SessionServiceStub;
  let dialogueService: DialogueServiceStub;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CodexPageComponent],
      providers: [
        provideRouter([]),
        { provide: SessionService, useClass: SessionServiceStub },
        { provide: DialogueService, useClass: DialogueServiceStub },
      ],
    }).compileComponents();
    sessionService = TestBed.inject(SessionService) as unknown as SessionServiceStub;
    dialogueService = TestBed.inject(DialogueService) as unknown as DialogueServiceStub;
  });

  it('renders codex progress and vertical category navigation', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const rail = fixture.nativeElement.querySelector('.codex-rail') as HTMLElement;
    expect(text).toContain('Codex');
    expect(rail).not.toBeNull();
    expect(rail.textContent).toContain('Codex Archive');
    expect(rail.textContent).toContain('Recovered Records');
    expect(text).toContain('Features');
    expect(text).toContain('Permanent account upgrades');
    expect(text).toContain('Objectives');
    expect(text).toContain('Current and cleared guidance');
    expect(text).toContain('Units');
    expect(text).toContain('Affixes');
    expect(text).toContain('Enemies');
    expect(text).toContain('Lore');
    expect(text).toContain('Feature Unlocks');
    expect(text).not.toContain('Map Glossary');
  });

  it('keeps each progress label and value in the same heading row', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const heading = fixture.nativeElement.querySelector('.stat-head') as HTMLElement;
    expect(heading).not.toBeNull();
    expect(heading.querySelector('span')?.textContent).toContain('Feature unlocks');
    expect(heading.querySelector('strong')?.textContent).toContain('2/10');
  });

  it('shows feature unlocks as category-icon rows with locked details hidden', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const featureEntries = fixture.nativeElement.querySelectorAll('.feature-entry');
    expect(text).toContain('Permanent account upgrades');
    expect(featureEntries.length).toBe(10);
    expect(featureEntries[1].getAttribute('style')).toContain('--depth: 0');
    expect(featureEntries[2].getAttribute('style')).toContain('--depth: 1');
    expect(featureEntries[5].getAttribute('style')).toContain('--depth: 1');
    expect(featureEntries[8].getAttribute('style')).toContain('--depth: 1');
    expect(fixture.nativeElement.querySelectorAll('.feature-icon').length).toBe(10);
    expect(text).toContain('Academy');
    expect(text).toContain('Feature Unlock - 250 teeth');
    expect(text).toContain('Sharp Dealer');
    expect(text).toContain('Economy Upgrade - 500 teeth');
    expect(text).toContain('???');
    expect(text).toContain('Unlock to learn more.');
  });

  it('shows current and completed objectives in the codex archive', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('objectives');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const objectiveEntries = Array.from(fixture.nativeElement.querySelectorAll('.objective-entry')) as HTMLElement[];

    expect(text).toContain('Objective Archive');
    expect(objectiveEntries.length).toBe(2);
    expect(objectiveEntries[0].textContent).toContain('Claim a battle victory');
    expect(objectiveEntries[0].textContent).toContain('Current');
    expect(objectiveEntries[0].textContent).toContain('0/1');
    expect(objectiveEntries[1].textContent).toContain('Equip a die');
    expect(objectiveEntries[1].textContent).toContain('Complete');
    expect(objectiveEntries[1].textContent).toContain('1/1');
  });

  it('shows all units as a locked and unlocked hierarchy on a separate category', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('units');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const unitEntries = fixture.nativeElement.querySelectorAll('.unit-entry');
    expect(unitEntries.length).toBe(20);
    expect(text).toContain('Bannerbearer');
    expect(text).toContain('A support specialist that reinforces nearby allies');
    expect(text).toContain('???');
    expect(text).not.toContain('Unknown Class');
    expect(fixture.nativeElement.querySelectorAll('.role-icon').length).toBe(20);
    expect(fixture.nativeElement.querySelectorAll('.unit-thumbnail').length).toBe(20);
    expect(fixture.nativeElement.querySelector('.unit-thumbnail')?.getAttribute('style') ?? '').not.toContain('123');
    expect(fixture.nativeElement.querySelectorAll('.unit-thumbnail--silhouette').length).toBe(19);
    expect(fixture.nativeElement.querySelector('.unit-thumbnail[alt="Bannerbearer portrait"]')?.getAttribute('src')).toContain(
      '/assets/ui/units/thumbnails/goblin/bannerbearer.png',
    );
    expect(fixture.nativeElement.querySelector('.unit-thumbnail--silhouette')?.getAttribute('src')).toContain(
      '/assets/ui/units/thumbnails/goblin/silhouette.png',
    );
  });

  it('switches categories and shows discovered affixes', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('affixes');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const affixEntries = fixture.nativeElement.querySelectorAll('.affix-entry');
    expect(text).toContain('Affix Archive');
    expect(affixEntries.length).toBe(6);
    expect(fixture.nativeElement.querySelectorAll('.affix-icon').length).toBe(6);
    expect(text).toContain('Bulwark');
    expect(text).toContain('Execute');
    expect(text).not.toContain('Unknown Affix');
    expect(text).toContain('Unlock to learn more.');
  });

  it('shows all enemies and skips sprites where art is unavailable', () => {
    sessionService.profileData.update((profile) => ({
      ...profile,
      regions: profile.regions.map((region: any) => (
        region.slug === 'mountains' ? { ...region, is_completed: false } : region
      )),
    }));

    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('enemies');
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    const enemyEntries = fixture.nativeElement.querySelectorAll('.enemy-entry');
    expect(text).toContain('Enemy Record');
    expect(enemyEntries.length).toBe(11);
    expect(text).toContain('Mudwrestler');
    expect(text).toContain('Mudslinger');
    expect(text).toContain('Mudking');
    expect(text).not.toContain('Unknown Enemy');
    expect(fixture.nativeElement.querySelectorAll('.enemy-sprite').length).toBe(11);
    expect(fixture.nativeElement.querySelectorAll('.enemy-entry--no-sprite').length).toBe(0);
    expect(fixture.nativeElement.querySelectorAll('.enemy-entry .role-icon').length).toBe(11);
    expect(fixture.nativeElement.querySelectorAll('.biome-badge').length).toBe(11);
    expect(fixture.nativeElement.querySelector('.biome-badge')?.getAttribute('src')).toContain('/assets/ui/biome/farm_badge.png');
    expect(fixture.nativeElement.querySelector('.silhouette')).not.toBeNull();
  });

  it('shows replayable lore entries only for seen lore-tagged dialogues', async () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    const component = fixture.componentInstance as any;
    component.setActiveCategory('lore');
    fixture.detectChanges();
    await fixture.whenStable();
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent as string;
    expect(text).toContain('Lore Archive');
    expect(dialogueService.getLoreDialogues).toHaveBeenCalled();
    expect(fixture.nativeElement.querySelectorAll('.lore-entry').length).toBe(3);
    expect(text).toContain("The Whim's First Fragment");
    expect(text).toContain('The Archivist Takes Notice');
    expect(text).toContain('The Tooth Collector Freed');
    expect(fixture.nativeElement.querySelectorAll('.lore-replay').length).toBe(3);
  });

  it('initializes session state on init', () => {
    const fixture = TestBed.createComponent(CodexPageComponent);
    fixture.detectChanges();

    expect(sessionService.initialize).toHaveBeenCalled();
  });
});

import { Component, OnDestroy, OnInit, computed, effect, inject, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
  faBullseye,
  faDiceD20,
  faHandFist,
  faBomb,
  faSkull,
  faShieldHalved,
} from '@fortawesome/free-solid-svg-icons';
import { resolveCompletedRegionSlugs } from '../../core/regions/region-catalog';
import { FeatureUnlockCategoryLabel, resolveFeatureUnlockCategory } from '../../core/feature-unlocks/feature-unlock-categories';
import { DialogueScript } from '../../core/dialogue/dialogue.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { ObjectiveRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';
import { resolvePrototypeEnemySpriteUrl } from '../../shared/ui/prototype-art/prototype-art';
import { resolveUnitAnimationFrameUrls, resolveUnitSilhouetteUrl, resolveUnitThumbnailUrl } from '../../shared/ui/unit-art/unit-art';
import { resolveFeatureUnlockIcon, resolveUnitRoleIcon } from '../../shared/ui/category-icons/category-icons';
import { RequirementCardComponent } from '../../shared/ui/requirement-card/requirement-card.component';
import { TabStripComponent, TabStripItem } from '../../shared/ui/tab-strip/tab-strip.component';

type CodexCategory = 'features' | 'objectives' | 'units' | 'affixes' | 'enemies' | 'lore';

type CodexCategoryNavItem = {
  key: CodexCategory;
  label: string;
  summary: string;
};

type CodexUnit = {
  name: string;
  slug: string;
  role: string;
  tier: number;
  description: string;
  cost: number;
  children?: ReadonlyArray<CodexUnit>;
};

type GuideAffix = {
  slug: string;
  name: string;
  rarity: string;
  description: string;
};

type GuideUnlock = {
  name: string;
  key: string;
  category: FeatureUnlockCategoryLabel;
  depth: number;
  cost: number;
  description: string;
};

type GuideBestiaryUnit = {
  slug: string;
  name: string;
  biome: string;
  role: string;
  assetKey?: string;
};

type CodexMetric = {
  label: string;
  value: string;
  detail: string;
  progressPercent: number;
};

type CodexAffixEntry = GuideAffix & {
  discovered: boolean;
};

type CodexEnemyEntry = GuideBestiaryUnit & {
  discovered: boolean;
};

type CodexLoreEntry = {
  id: string;
  title: string;
  summary: string;
  script: DialogueScript;
};

const BIOME_GUIDE_UNITS: ReadonlyArray<GuideBestiaryUnit> = [
  {
    slug: 'mudwrestler',
    name: 'Mudwrestler',
    biome: 'The Farm',
    role: 'Frontline',
  },
  {
    slug: 'mudslinger',
    name: 'Mudslinger',
    biome: 'The Farm',
    role: 'Backline',
  },
  {
    slug: 'mudking',
    name: 'Mudking',
    biome: 'The Farm',
    role: 'Frontline',
  },
  {
    slug: 'kobold_skirmisher',
    name: 'Kobold Skirmisher',
    biome: 'Mountains',
    role: 'Backline',
    assetKey: 'kobold/skirmisher',
  },
  {
    slug: 'kobold_shieldbearer',
    name: 'Kobold Shieldbearer',
    biome: 'Mountains',
    role: 'Frontline',
    assetKey: 'kobold/shieldbearer',
  },
  {
    slug: 'kobold_sharpshooter',
    name: 'Kobold Sharpshooter',
    biome: 'Mountains',
    role: 'Backline',
    assetKey: 'kobold/sharpshooter',
  },
  {
    slug: 'kobold_warchief',
    name: 'Kobold Warchief',
    biome: 'Mountains',
    role: 'Backline',
    assetKey: 'kobold/warchief',
  },
  {
    slug: 'frogman_bruiser',
    name: 'Frogman Bruiser',
    biome: 'Swamps',
    role: 'Frontline',
  },
  {
    slug: 'frogman_spearhunter',
    name: 'Frogman Spearhunter',
    biome: 'Swamps',
    role: 'Frontline',
  },
  {
    slug: 'frogman_wardrummer',
    name: 'Frogman Wardrummer',
    biome: 'Swamps',
    role: 'Support',
  },
  {
    slug: 'frogman_bog_tyrant',
    name: 'Bog Tyrant',
    biome: 'Swamps',
    role: 'Frontline',
  },
];

const GUIDE_UNIT_ANIMATION_INTERVAL_MS = 320;
const TIER_ONE_UNIT_COST = 250;
const PROMOTED_UNIT_COST = 500;
const PLAYER_DIALOGUE_PORTRAIT = '/assets/ui/units/animated/goblin/base/frame_0.png';

const UNIT_TREE: ReadonlyArray<CodexUnit> = [
  {
    name: 'Bruiser',
    slug: 'frontline_bruiser_t1',
    role: 'Frontline',
    tier: 1,
    cost: TIER_ONE_UNIT_COST,
    description: 'A durable frontliner built to absorb hits and keep pressure on the enemy line.',
    children: [
      {
        name: 'Enforcer',
        slug: 'frontline_bruiser_t2',
        role: 'Frontline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'An upgraded bruiser branch that leans into heavier execution damage and frontline pressure.',
        children: [
          {
            name: 'Juggernaut',
            slug: 'frontline_bruiser_t3',
            role: 'Frontline',
            tier: 3,
            cost: PROMOTED_UNIT_COST,
            description: 'A deep bruiser promotion that commits fully to punishing pressure and frontline dominance.',
          },
        ],
      },
      {
        name: 'Pit Fighter',
        slug: 'frontline_pit_fighter_t2',
        role: 'Frontline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A risky brawler branch that cashes in on wounded states, counters, and comeback turns.',
      },
    ],
  },
  {
    name: 'Guardian',
    slug: 'frontline_guardian_t1',
    role: 'Frontline',
    tier: 1,
    cost: TIER_ONE_UNIT_COST,
    description: 'A shield-first defender that trades damage for stronger protection and staying power.',
    children: [
      {
        name: 'Bulwark',
        slug: 'frontline_guardian_t2',
        role: 'Frontline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A fortified guardian branch that specializes in tanking, guard conversion, and line-holding.',
        children: [
          {
            name: 'Ironwall',
            slug: 'frontline_guardian_t3',
            role: 'Frontline',
            tier: 3,
            cost: PROMOTED_UNIT_COST,
            description: 'A deep guardian promotion built around maximum line control and defensive staying power.',
          },
        ],
      },
      {
        name: 'Shieldbreaker',
        slug: 'frontline_shieldbreaker_t2',
        role: 'Frontline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'An anti-armor frontline branch built to crack defenses open for the squad.',
      },
    ],
  },
  {
    name: 'Marksman',
    slug: 'backline_marksman_t1',
    role: 'Backline',
    tier: 1,
    cost: TIER_ONE_UNIT_COST,
    description: 'A ranged damage dealer that thrives from the back row with steady offensive pressure.',
    children: [
      {
        name: 'Deadeye',
        slug: 'backline_marksman_t2',
        role: 'Backline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A precision ranged branch focused on single-target removal and armor-piercing shots.',
        children: [
          {
            name: 'Sharpshot',
            slug: 'backline_marksman_t3',
            role: 'Backline',
            tier: 3,
            cost: PROMOTED_UNIT_COST,
            description: 'A deep marksman promotion that turns backline pressure into lethal precision.',
          },
        ],
      },
      {
        name: 'Trapper',
        slug: 'backline_trapper_t2',
        role: 'Backline',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A utility archer branch built around marks, setup tools, and treasure sense.',
      },
    ],
  },
  {
    name: 'Bannerbearer',
    slug: 'support_banner_t1',
    role: 'Support',
    tier: 1,
    cost: TIER_ONE_UNIT_COST,
    description: 'A support specialist that reinforces nearby allies and helps the squad endure longer fights.',
    children: [
      {
        name: 'Warcaller',
        slug: 'support_banner_t2',
        role: 'Support',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'An offensive support branch that turns team tempo and buffs into aggressive momentum.',
        children: [
          {
            name: 'Warchanter',
            slug: 'support_banner_t3',
            role: 'Support',
            tier: 3,
            cost: PROMOTED_UNIT_COST,
            description: 'A deep banner promotion that makes sustained buffs and squad tempo the center of the fight.',
          },
        ],
      },
      {
        name: 'Mascot',
        slug: 'support_mascot_t2',
        role: 'Support',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A chaotic support branch that spreads luck, morale swings, and scrappy clutch bonuses.',
      },
    ],
  },
  {
    name: 'Saboteur',
    slug: 'control_saboteur_t1',
    role: 'Utility',
    tier: 1,
    cost: TIER_ONE_UNIT_COST,
    description: 'A disruptive skirmisher focused on interference, control, and breaking enemy momentum.',
    children: [
      {
        name: 'Trickshot',
        slug: 'control_saboteur_t2',
        role: 'Utility',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A sharper control branch that punishes compromised enemies with precise follow-up pressure.',
        children: [
          {
            name: 'Venomwright',
            slug: 'control_saboteur_t3',
            role: 'Utility',
            tier: 3,
            cost: PROMOTED_UNIT_COST,
            description: 'A deep saboteur promotion that turns disruption and poison setup into a late-game control plan.',
          },
        ],
      },
      {
        name: 'Plaguehand',
        slug: 'control_plaguehand_t2',
        role: 'Utility',
        tier: 2,
        cost: PROMOTED_UNIT_COST,
        description: 'A poison-heavy control branch that spreads weakness and softens multiple targets at once.',
      },
    ],
  },
];

@Component({
  selector: 'app-codex-page',
  standalone: true,
  imports: [DgDialogueStageComponent, FontAwesomeModule, NgTemplateOutlet, PageFrameComponent, RequirementCardComponent, TabStripComponent],
  templateUrl: './codex-page.component.html',
  styleUrl: './codex-page.component.scss',
})
export class CodexPageComponent implements OnInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);
  private readonly guideUnitAnimationTimers = new Map<string, ReturnType<typeof window.setInterval>>();
  private lastLoreDialogueKey = '';

  protected readonly session = this.sessionService.session;
  protected readonly profileData = this.sessionService.profileData;
  protected readonly hasActiveRun = this.sessionService.hasActiveRun;
  protected readonly guideUnitFrameIndexes = signal<Record<string, number>>({});
  protected readonly codexLoreEntries = signal<ReadonlyArray<CodexLoreEntry>>([]);
  protected readonly activeLoreDialogue = signal<DialogueScript | null>(null);
  protected readonly activeCategory = signal<CodexCategory>('features');
  protected readonly pageTitle = 'Codex';
  protected readonly pageSubtitle = 'A living record of your unlocks, sightings, and discoveries.';
  protected readonly breadcrumbs = [{ label: 'Codex' }];
  protected readonly categories: ReadonlyArray<CodexCategoryNavItem> = [
    { key: 'features', label: 'Features', summary: 'Permanent account upgrades' },
    { key: 'objectives', label: 'Objectives', summary: 'Current and cleared guidance' },
    { key: 'units', label: 'Units', summary: 'Known warband classes' },
    { key: 'affixes', label: 'Affixes', summary: 'Discovered die traits' },
    { key: 'enemies', label: 'Enemies', summary: 'Recorded hostile units' },
    { key: 'lore', label: 'Lore', summary: 'Recovered story pages' },
  ];
  protected readonly categoryTabs = computed<ReadonlyArray<TabStripItem>>(() =>
    this.categories.map((category) => ({
      id: category.key,
      label: category.label,
      kicker: category.summary,
    })),
  );

  protected readonly unitTree = UNIT_TREE;

  protected readonly affixes: ReadonlyArray<GuideAffix> = [
    { slug: 'attack_flat', name: 'Atk+', rarity: 'Common', description: '+1 damage on attack rolls.' },
    { slug: 'defense_flat', name: 'Guard+', rarity: 'Common', description: '+1 defense while this die is equipped.' },
    { slug: 'bulwark_plus', name: 'Bulwark', rarity: 'Uncommon', description: '+10% defense while this die is equipped.' },
    { slug: 'precision_plus', name: 'Precision', rarity: 'Uncommon', description: '+10% attack while this die is equipped.' },
    { slug: 'execute_below_half', name: 'Execute', rarity: 'Rare', description: 'When the target is below 50% HP, deal 15% more damage.' },
    { slug: 'explode_once', name: 'Explode', rarity: 'Rare', description: 'When this die rolls max, roll again once and combine the result.' },
  ];

  protected readonly unlocks: ReadonlyArray<GuideUnlock> = [
    { name: 'Academy', key: 'academy', category: resolveFeatureUnlockCategory('academy'), depth: 0, cost: 250, description: 'Unlock promotions and unit-type research for your warband.' },
    { name: 'Bigger Squad', key: 'bigger_squad', category: resolveFeatureUnlockCategory('bigger_squad'), depth: 0, cost: 500, description: 'Raise your squad size cap from 4 units to 6.' },
    { name: 'Biggerest Squad', key: 'biggerest_squad', category: resolveFeatureUnlockCategory('biggerest_squad'), depth: 1, cost: 1000, description: 'Raise your squad size cap from 6 units to the full 9-slot formation.' },
    { name: 'Coupon Book', key: 'shop_discount', category: resolveFeatureUnlockCategory('shop_discount'), depth: 0, cost: 500, description: 'Make all future shop purchases cost 10% less.' },
    { name: 'Sharp Dealer', key: 'sell_bonus', category: resolveFeatureUnlockCategory('sell_bonus'), depth: 0, cost: 500, description: 'Make dice sales pay out 10% more teeth.' },
    { name: 'Market Mastery', key: 'market_mastery', category: resolveFeatureUnlockCategory('market_mastery'), depth: 1, cost: 1000, description: 'Improve both shop discounts and sale payouts to 20% once both economy upgrades are unlocked.' },
    { name: 'Second Deal', key: 'second_daily_deal', category: resolveFeatureUnlockCategory('second_daily_deal'), depth: 0, cost: 500, description: 'Add a second daily deal slot so the shop offers two rotating featured dice each day.' },
    { name: 'Deep Pantry', key: 'energy_cap_75', category: resolveFeatureUnlockCategory('energy_cap_75'), depth: 0, cost: 750, description: 'Raise your max energy from 50 to 75.' },
    { name: 'Bottomless Pantry', key: 'energy_cap_100', category: resolveFeatureUnlockCategory('energy_cap_100'), depth: 1, cost: 1250, description: 'Raise your max energy from 75 to 100 once Deep Pantry is unlocked.' },
    { name: 'Loaded Caltrops', key: 'explode_d4s', category: resolveFeatureUnlockCategory('explode_d4s'), depth: 0, cost: 2000, description: 'Give every d4 a one-time explode when it rolls max during combat.' },
  ];

  protected readonly completedBiomeSlugs = computed(() => resolveCompletedRegionSlugs(this.profileData()));
  protected readonly codexObjectives = computed<ReadonlyArray<ObjectiveRecord>>(() =>
    [...(this.profileData()?.objectives ?? [])].sort((left, right) => {
      const leftComplete = left.status === 'complete' ? 1 : 0;
      const rightComplete = right.status === 'complete' ? 1 : 0;
      return leftComplete - rightComplete || (right.priority ?? 0) - (left.priority ?? 0) || left.title.localeCompare(right.title);
    }),
  );

  protected readonly discoveredBiomeUnits = computed(() => {
    const completedBiomes = new Set(this.completedBiomeSlugs());
    return BIOME_GUIDE_UNITS.filter((unit) => completedBiomes.has(this.normalizeBiomeSlug(unit.biome)));
  });

  protected readonly codexMetrics = computed<ReadonlyArray<CodexMetric>>(() => {
    const profile = this.profileData();
    const completedRegions = this.completedBiomeSlugs().length;
    const unlockedFeatures = this.unlocks.filter((unlock) => this.hasAcquiredFeatureUnlock(unlock.key)).length;
    const unlockedUnits = this.unlockedUnitCount();
    const seenAffixes = this.discoveredAffixSlugs().size;
    const loreCount = this.codexLoreEntries().length;
    const discoveredEnemyCount = this.discoveredEnemySlugs().size;

    return [
      {
        label: 'Feature unlocks',
        value: `${unlockedFeatures}/${this.unlocks.length}`,
        detail: unlockedFeatures ? 'Permanent account upgrades recovered.' : 'No permanent account upgrades recorded yet.',
        progressPercent: this.percent(unlockedFeatures, this.unlocks.length),
      },
      {
        label: 'Units',
        value: `${unlockedUnits}/${this.totalUnitCount()}`,
        detail: unlockedUnits ? 'Unit types currently available in your warband.' : 'Recruit more units to expand promotions.',
        progressPercent: this.percent(unlockedUnits, this.totalUnitCount()),
      },
      {
        label: 'Seen affixes',
        value: `${seenAffixes}/${this.affixes.length}`,
        detail: seenAffixes ? 'Affix language spotted on owned dice.' : 'Appraise more dice to fill in the affix archive.',
        progressPercent: this.percent(seenAffixes, this.affixes.length),
      },
      {
        label: 'Lore pages',
        value: `${loreCount}`,
        detail: loreCount ? 'Dialogue moments and discoveries logged to the archive.' : 'No lore entries logged yet.',
        progressPercent: Math.min(100, loreCount * 20),
      },
      {
        label: 'Defeated enemy types',
        value: `${discoveredEnemyCount}/${BIOME_GUIDE_UNITS.length}`,
        detail: completedRegions ? `${completedRegions} cleared region${completedRegions === 1 ? '' : 's'} feeding the bestiary.` : 'Clear regions to confirm enemy records.',
        progressPercent: this.percent(discoveredEnemyCount, BIOME_GUIDE_UNITS.length),
      },
    ];
  });

  protected readonly codexAffixEntries = computed<ReadonlyArray<CodexAffixEntry>>(() => {
    const discovered = this.discoveredAffixSlugs();
    return this.affixes.map((affix) => ({
      ...affix,
      discovered: discovered.has(affix.slug),
    }));
  });

  protected readonly codexEnemyEntries = computed<ReadonlyArray<CodexEnemyEntry>>(() => {
    const discovered = this.discoveredEnemySlugs();
    return BIOME_GUIDE_UNITS.map((unit) => ({
      ...unit,
      discovered: discovered.has(unit.slug),
    }));
  });

  constructor() {
    effect(() => {
      const seenDialogueKey = Array.from(new Set(this.profileData()?.seen_dialogues ?? []))
        .sort()
        .join('|');
      const sessionKey = `${this.session().displayName ?? ''}|${seenDialogueKey}`;
      if (sessionKey === this.lastLoreDialogueKey) {
        return;
      }

      this.lastLoreDialogueKey = sessionKey;
      void this.loadLoreDialogues();
    });
  }

  ngOnInit(): void {
    void this.sessionService.initialize();
  }

  ngOnDestroy(): void {
    for (const timer of this.guideUnitAnimationTimers.values()) {
      window.clearInterval(timer);
    }
    this.guideUnitAnimationTimers.clear();
  }

  protected setActiveCategory(category: string): void {
    if (!this.categories.some((item) => item.key === category)) {
      return;
    }

    this.activeCategory.set(category as CodexCategory);
  }

  protected replayLoreDialogue(entry: CodexLoreEntry): void {
    this.activeLoreDialogue.set(entry.script);
  }

  protected closeLoreDialogue(): void {
    this.activeLoreDialogue.set(null);
  }

  protected hasAcquiredFeatureUnlock(unlockKey: string): boolean {
    if (!this.session().isAuthenticated) {
      return false;
    }

    return this.isCodexOwned('feature', unlockKey) || (this.profileData()?.feature_unlocks.includes(unlockKey) ?? false);
  }

  protected hasAcquiredUnitUnlock(unitSlug: string): boolean {
    if (!this.session().isAuthenticated) {
      return false;
    }

    return this.isCodexOwned('unit_type', unitSlug) || (this.profileData()?.unit_type_unlocks.includes(unitSlug) ?? false);
  }

  protected roleIcon(role: string): IconDefinition {
    return resolveUnitRoleIcon(role);
  }

  protected featureIcon(category: FeatureUnlockCategoryLabel): IconDefinition {
    return resolveFeatureUnlockIcon(category);
  }

  protected objectiveProgressLabel(objective: ObjectiveRecord): string {
    const target = Math.max(1, objective.progress_target);
    const current = Math.min(Math.max(0, objective.progress_current), target);
    return `${current}/${target}`;
  }

  protected objectiveStatusLabel(objective: ObjectiveRecord): string {
    if (objective.status === 'complete') {
      return 'Complete';
    }

    return 'Current';
  }

  protected affixIcon(affix: CodexAffixEntry): IconDefinition {
    if (!affix.discovered) {
      return faDiceD20;
    }

    switch (this.normalizeKey(affix.name)) {
      case 'guard':
      case 'bulwark':
        return faShieldHalved;
      case 'precision':
        return faBullseye;
      case 'execute':
        return faSkull;
      case 'explode':
        return faBomb;
      default:
        return faHandFist;
    }
  }

  protected unitPortraitUrl(unit: CodexUnit): string {
    if (!this.hasAcquiredUnitUnlock(unit.slug)) {
      return resolveUnitSilhouetteUrl();
    }

    return resolveUnitThumbnailUrl(unit.slug) ?? resolveUnitSilhouetteUrl();
  }

  protected enemySpriteUrl(enemySlug: string): string {
    return resolvePrototypeEnemySpriteUrl(enemySlug);
  }

  protected hasEnemySprite(unit: GuideBestiaryUnit): boolean {
    return resolveUnitAnimationFrameUrls(unit.slug).length > 0;
  }

  protected biomeBadgeUrl(biome: string): string {
    const theme = this.normalizeBiomeSlug(biome).replace(/^the_/, '').replace(/s$/, '');
    return `/assets/ui/biome/${theme}_badge.png`;
  }

  protected guideUnitFramePath(unit: GuideBestiaryUnit): string {
    const frameIndex = this.guideUnitFrameIndexes()[unit.slug] ?? 0;
    const frames = resolveUnitAnimationFrameUrls(unit.slug);
    if (!frames.length) {
      return '';
    }

    return frames[frameIndex % frames.length];
  }

  protected startGuideUnitAnimation(unitSlug: string): void {
    if (typeof window === 'undefined' || this.guideUnitAnimationTimers.has(unitSlug)) {
      return;
    }

    this.guideUnitFrameIndexes.update((current) => ({ ...current, [unitSlug]: 1 }));
    const timer = window.setInterval(() => {
      this.guideUnitFrameIndexes.update((current) => {
        const currentFrame = current[unitSlug] ?? 1;
        const nextFrame = currentFrame >= 3 ? 1 : currentFrame + 1;
        return { ...current, [unitSlug]: nextFrame };
      });
    }, GUIDE_UNIT_ANIMATION_INTERVAL_MS);
    this.guideUnitAnimationTimers.set(unitSlug, timer);
  }

  protected stopGuideUnitAnimation(unitSlug: string): void {
    const timer = this.guideUnitAnimationTimers.get(unitSlug);
    if (timer) {
      window.clearInterval(timer);
      this.guideUnitAnimationTimers.delete(unitSlug);
    }

    this.guideUnitFrameIndexes.update((current) => ({ ...current, [unitSlug]: 0 }));
  }

  private discoveredAffixSlugs(): Set<string> {
    const codexAffixes = this.codexKeysForType('affix');
    if (codexAffixes !== null) {
      return codexAffixes;
    }

    return new Set(
      (this.profileData()?.dice ?? [])
        .flatMap((die) => die.affixes ?? [])
        .map((affix) => affix.affix_slug ?? '')
        .filter((value): value is string => value.length > 0),
    );
  }

  private discoveredEnemySlugs(): Set<string> {
    const codexEnemies = this.codexKeysForType('enemy');
    if (codexEnemies !== null) {
      return codexEnemies;
    }

    return new Set(this.discoveredBiomeUnits().map((unit) => unit.slug));
  }

  private flattenedUnits(): ReadonlyArray<CodexUnit> {
    const flatten = (units: ReadonlyArray<CodexUnit>): CodexUnit[] => units.flatMap((unit) => [
      unit,
      ...flatten(unit.children ?? []),
    ]);

    return flatten(this.unitTree);
  }

  private totalUnitCount(): number {
    return this.flattenedUnits().length;
  }

  private unlockedUnitCount(): number {
    return this.flattenedUnits().filter((unit) => this.hasAcquiredUnitUnlock(unit.slug)).length;
  }

  private normalizeKey(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, '');
  }

  private codexKeysForType(entryType: string): Set<string> | null {
    const ownedByType = this.profileData()?.codex?.owned_by_type;
    if (!ownedByType || !Object.prototype.hasOwnProperty.call(ownedByType, entryType)) {
      return null;
    }

    return new Set((ownedByType[entryType] ?? []).map((key) => key.trim()).filter((key) => key.length > 0));
  }

  private isCodexOwned(entryType: string, entryKey: string): boolean {
    return this.codexKeysForType(entryType)?.has(entryKey) ?? false;
  }

  private normalizeBiomeSlug(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, '_');
  }

  private humanizeLabel(value: string): string {
    return value
      .trim()
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .replace(/\b\w/g, (match) => match.toUpperCase());
  }

  private async loadLoreDialogues(): Promise<void> {
    const loreKeys = this.codexKeysForType('lore');
    const seenDialogues = Array.from(new Set(loreKeys !== null ? Array.from(loreKeys) : (this.profileData()?.seen_dialogues ?? [])));
    if (!seenDialogues.length) {
      this.codexLoreEntries.set([]);
      return;
    }

    try {
      const scripts = await this.dialogueService.getLoreDialogues(seenDialogues, {
        scene: 'codex',
        playerName: this.session().displayName,
        playerPortraitUrl: PLAYER_DIALOGUE_PORTRAIT,
      });
      this.codexLoreEntries.set(scripts.map((script) => ({
        id: script.id,
        title: script.title ?? this.humanizeLabel(script.id),
        summary: script.summary ?? 'Recovered dialogue from your journey.',
        script,
      })));
    } catch {
      this.codexLoreEntries.set([]);
    }
  }

  private percent(value: number, total: number): number {
    if (total <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, Math.round((value / total) * 100)));
  }
}

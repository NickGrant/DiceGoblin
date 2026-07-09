import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { TabStripComponent } from '../../shared/ui/tab-strip/tab-strip.component';
import { resolveUnitImageUrl } from '../../shared/ui/unit-art/unit-art';

type GuideChapterId = 'overview' | 'warband' | 'dice' | 'expeditions';

type GuideChapter = {
  id: GuideChapterId;
  label: string;
  kicker: string;
  title: string;
  summary: string;
};

type GuideUnit = {
  name: string;
  slug: string;
  role: string;
  tier: number;
  maxLevel: number;
  summary: string;
};

type GuideAffix = {
  name: string;
  rarity: string;
  description: string;
};

type GuideUnlock = {
  name: string;
  key: string;
  cost: number;
  description: string;
};

type GuideNode = {
  name: string;
  icon: string;
  description: string;
};

type GuideDiceFamily = {
  rarity: string;
  image: string;
  summary: string;
};

type GuideDieSize = {
  label: string;
  image: string;
  summary: string;
};

type GuideBestiaryUnit = {
  slug: string;
  name: string;
  biome: string;
  role: string;
  assetKey: string;
};

const REGION_COMPLETION_UNLOCK_MAP: Record<string, string> = {
  the_farm: 'mountains',
  mountains: 'swamps',
};

const BIOME_GUIDE_UNITS: ReadonlyArray<GuideBestiaryUnit> = [
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
];

const GUIDE_UNIT_ANIMATION_INTERVAL_MS = 320;

@Component({
  selector: 'app-guide-page',
  standalone: true,
  imports: [PageFrameComponent, TabStripComponent],
  templateUrl: './guide-page.component.html',
  styleUrl: './guide-page.component.scss',
})
export class GuidePageComponent implements OnInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly guideUnitAnimationTimers = new Map<string, ReturnType<typeof window.setInterval>>();

  protected readonly session = this.sessionService.session;
  protected readonly profileData = this.sessionService.profileData;
  protected readonly hasActiveRun = this.sessionService.hasActiveRun;
  protected readonly activeChapter = signal<GuideChapterId>('overview');
  protected readonly guideUnitFrameIndexes = signal<Record<string, number>>({});

  protected readonly chapters: ReadonlyArray<GuideChapter> = [
    {
      id: 'overview',
      label: 'Overview',
      kicker: 'Quick Read',
      title: 'Learn the rhythm of a run',
      summary: 'Start here for the fastest explanation of how squads, unlocks, and expeditions fit together.',
    },
    {
      id: 'warband',
      label: 'Warband',
      kicker: 'Roster',
      title: 'Build, unlock, and promote units',
      summary: 'Browse the current roster, see what unlocks permanently expand the warband, and review promotion rules.',
    },
    {
      id: 'dice',
      label: 'Dice',
      kicker: 'Loadouts',
      title: 'Understand dice, sizes, and affixes',
      summary: 'See the main dice families, size breakpoints, and affix language used throughout the game.',
    },
    {
      id: 'expeditions',
      label: 'Expeditions',
      kicker: 'Runs',
      title: 'Read how combat and map nodes work',
      summary: 'Review encounter flow, node meanings, failure handling, and the basic rules that shape a successful run.',
    },
  ];

  protected readonly unitUnlocks: ReadonlyArray<GuideUnit> = [
    {
      name: 'Bruiser',
      slug: 'frontline_bruiser_t1',
      role: 'Frontline',
      tier: 1,
      maxLevel: 10,
      summary: 'A durable frontliner that can promote early at level 6 or master the class at level 10 for a passive capstone.',
    },
    {
      name: 'Guardian',
      slug: 'frontline_guardian_t1',
      role: 'Frontline',
      tier: 1,
      maxLevel: 10,
      summary: 'A shield-first defender that can hold for a level 10 capstone or branch early into pure tanking or anti-armor roles.',
    },
    {
      name: 'Marksman',
      slug: 'backline_marksman_t1',
      role: 'Backline',
      tier: 1,
      maxLevel: 10,
      summary: 'A back-row damage dealer that can rush promotion at level 6 or stay to earn a targeting-focused capstone at level 10.',
    },
    {
      name: 'Bannerbearer',
      slug: 'support_banner_t1',
      role: 'Support',
      tier: 1,
      maxLevel: 10,
      summary: 'A support specialist that can either master bolster-focused capstones or branch into offensive or luck-based support.',
    },
    {
      name: 'Saboteur',
      slug: 'control_saboteur_t1',
      role: 'Utility',
      tier: 1,
      maxLevel: 10,
      summary: 'A disruptive debuffer that can promote from level 6 or stay through level 10 for stronger control passives.',
    },
  ];

  protected readonly units: ReadonlyArray<GuideUnit> = [
    { name: 'Bruiser', slug: 'frontline_bruiser_t1', role: 'Frontline', tier: 1, maxLevel: 10, summary: 'Balanced offense and toughness for the front row.' },
    { name: 'Enforcer', slug: 'frontline_bruiser_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'A pressure bruiser that leans into execution damage and attack suppression.' },
    { name: 'Pit Fighter', slug: 'frontline_pit_fighter_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'A comeback brawler built around wounded payoffs, counterattacks, and survival spikes.' },
    { name: 'Juggernaut', slug: 'frontline_bruiser_t3', role: 'Frontline', tier: 3, maxLevel: 10, summary: 'The heavy end of the bruiser line, intended to become a squad-anchor frontline threat.' },
    { name: 'Guardian', slug: 'frontline_guardian_t1', role: 'Frontline', tier: 1, maxLevel: 10, summary: 'Defense-first tank for holding the line.' },
    { name: 'Bulwark', slug: 'frontline_guardian_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'A dedicated tank that redirects pressure and converts die rolls into temporary guard stacks.' },
    { name: 'Shieldbreaker', slug: 'frontline_shieldbreaker_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'An anti-armor frontline branch that cracks defenses open for the rest of the squad.' },
    { name: 'Ironwall', slug: 'frontline_guardian_t3', role: 'Frontline', tier: 3, maxLevel: 10, summary: 'The guardian line\'s endgame wall, built to anchor formations and absorb focused fire.' },
    { name: 'Marksman', slug: 'backline_marksman_t1', role: 'Backline', tier: 1, maxLevel: 10, summary: 'Reliable ranged damage from safer back-row positions.' },
    { name: 'Deadeye', slug: 'backline_marksman_t2', role: 'Backline', tier: 2, maxLevel: 10, summary: 'A single-target ranged specialist with stronger armor-piercing pressure.' },
    { name: 'Trapper', slug: 'backline_trapper_t2', role: 'Backline', tier: 2, maxLevel: 10, summary: 'A utility archer that marks enemies and can reveal hidden treasure once per run.' },
    { name: 'Sharpshot', slug: 'backline_marksman_t3', role: 'Backline', tier: 3, maxLevel: 10, summary: 'The marksman line\'s endgame sniper, intended for elite ranged focus fire.' },
    { name: 'Bannerbearer', slug: 'support_banner_t1', role: 'Support', tier: 1, maxLevel: 10, summary: 'Support path focused on bolsters, sustain, and setting up later support branches.' },
    { name: 'Warcaller', slug: 'support_banner_t2', role: 'Support', tier: 2, maxLevel: 10, summary: 'An offensive support branch that buffs allies and accelerates combat momentum.' },
    { name: 'Mascot', slug: 'support_mascot_t2', role: 'Support', tier: 2, maxLevel: 10, summary: 'A luck-driven support branch that spreads scrappy bonuses and clutch protection.' },
    { name: 'Saboteur', slug: 'control_saboteur_t1', role: 'Utility', tier: 1, maxLevel: 10, summary: 'Control-focused unit built around disruption and enemy debuffs.' },
    { name: 'Trickshot', slug: 'control_saboteur_t2', role: 'Utility', tier: 2, maxLevel: 10, summary: 'A precision debuffer that punishes enemies already suffering status effects.' },
    { name: 'Plaguehand', slug: 'control_plaguehand_t2', role: 'Utility', tier: 2, maxLevel: 10, summary: 'A poison-focused control branch that weakens multiple enemies at once.' },
  ];

  protected readonly affixes: ReadonlyArray<GuideAffix> = [
    { name: 'Atk+', rarity: 'Common', description: '+1 damage on attack rolls.' },
    { name: 'Guard+', rarity: 'Common', description: '+1 defense while this die is equipped.' },
    { name: 'Bulwark', rarity: 'Uncommon', description: '+10% defense while this die is equipped.' },
    { name: 'Precision', rarity: 'Uncommon', description: '+10% attack while this die is equipped.' },
    { name: 'Execute', rarity: 'Rare', description: 'When the target is below 50% HP, deal 15% more damage.' },
    { name: 'Explode', rarity: 'Rare', description: 'When this die rolls max, roll again once and combine the result.' },
  ];

  protected readonly unlocks: ReadonlyArray<GuideUnlock> = [
    { name: 'Academy', key: 'academy', cost: 250, description: 'Unlock promotions and unit-type research for your warband.' },
    { name: 'Bigger Squad', key: 'bigger_squad', cost: 500, description: 'Raise your squad size cap from 4 units to 6.' },
    { name: 'Biggerest Squad', key: 'biggerest_squad', cost: 1000, description: 'Raise your squad size cap from 6 units to the full 9-slot formation.' },
    { name: 'Coupon Book', key: 'shop_discount', cost: 500, description: 'Make all future shop purchases cost 10% less.' },
    { name: 'Sharp Dealer', key: 'sell_bonus', cost: 500, description: 'Make dice sales pay out 10% more teeth.' },
    { name: 'Market Mastery', key: 'market_mastery', cost: 1000, description: 'Improve both shop discounts and sale payouts to 20% once both economy upgrades are unlocked.' },
    { name: 'Second Deal', key: 'second_daily_deal', cost: 500, description: 'Add a second daily deal slot so the shop offers two rotating featured dice each day.' },
    { name: 'Deep Pantry', key: 'energy_cap_75', cost: 750, description: 'Raise your max energy from 50 to 75.' },
    { name: 'Bottomless Pantry', key: 'energy_cap_100', cost: 1250, description: 'Raise your max energy from 75 to 100 once Deep Pantry is unlocked.' },
    { name: 'Loaded Caltrops', key: 'explode_d4s', cost: 2000, description: 'Give every d4 a one-time explode when it rolls max during combat.' },
  ];

  protected readonly nodes: ReadonlyArray<GuideNode> = [
    {
      name: 'Combat',
      icon: '/assets/ui/icons/icon_encounter_combat.png',
      description: 'Fight a regular encounter. Combat and boss nodes are the main source of unit XP during a run.',
    },
    {
      name: 'Loot',
      icon: '/assets/ui/icons/icon_encounter_loot.png',
      description: 'Resolve a reward node without combat. Loot nodes do not directly award unit XP.',
    },
    {
      name: 'Rest',
      icon: '/assets/ui/icons/icon_encounter_rest.png',
      description: 'Fully heal run units, clear defeated flags, and reset cooldowns and statuses before continuing.',
    },
    {
      name: 'Boss',
      icon: '/assets/ui/icons/icon_encounter_boss.png',
      description: 'A higher-stakes combat encounter that follows the same core combat rules as other battles.',
    },
    {
      name: 'Exit',
      icon: '/assets/ui/icons/icon_home.png',
      description: 'Finish a successful run and move to the summary screen for rewards and cleanup.',
    },
  ];

  protected readonly diceFamilies: ReadonlyArray<GuideDiceFamily> = [
    { rarity: 'Common', image: this.diceImage('common', 6), summary: 'Cardboard dice are the baseline economy pieces you buy, loot, and replace most often.' },
    { rarity: 'Uncommon', image: this.diceImage('uncommon', 8), summary: 'Wood dice start adding stronger affix combinations and better sell value.' },
    { rarity: 'Rare', image: this.diceImage('rare', 10), summary: 'Bone dice are premium upgrades with more dramatic payoff and stronger affix ceilings.' },
    { rarity: 'Epic', image: this.diceImage('epic', 12), summary: 'Metal dice are heavier endgame pieces intended to anchor higher-value loadouts.' },
    { rarity: 'Legendary', image: this.diceImage('legendary', 20), summary: 'Gemstone dice represent the flashiest rarity tier and the broadest raw power ceiling.' },
  ];

  protected readonly dieSizes: ReadonlyArray<GuideDieSize> = [
    { label: 'd4', image: this.diceImage('common', 4), summary: 'Smallest die size. Useful for cheap utility slots and low-variance filler.' },
    { label: 'd6', image: this.diceImage('common', 6), summary: 'The default all-rounder size and the most familiar starting point for loadouts.' },
    { label: 'd8', image: this.diceImage('common', 8), summary: 'A noticeable power jump that still shows up regularly in the early and mid game.' },
    { label: 'd10', image: this.diceImage('common', 10), summary: 'A premium mid-to-high roll option that starts feeling explosive with good affixes.' },
    { label: 'd12', image: this.diceImage('common', 12), summary: 'Large die size with strong ceiling value for major attack or support slots.' },
    { label: 'd20', image: this.diceImage('common', 20), summary: 'The biggest standard die, best suited to high-impact abilities and chase upgrades.' },
  ];

  protected readonly completedBiomeSlugs = computed(() => {
    const unlockedRegions = new Set((this.profileData()?.region_unlocks ?? []).map((entry) => entry.region_slug));
    return Object.entries(REGION_COMPLETION_UNLOCK_MAP)
      .filter(([, unlockedRegionSlug]) => unlockedRegions.has(unlockedRegionSlug))
      .map(([completedRegionSlug]) => completedRegionSlug);
  });

  protected readonly discoveredBiomeUnits = computed(() => {
    if (!this.session().isAuthenticated) {
      return [] as GuideBestiaryUnit[];
    }

    const completedBiomes = new Set(this.completedBiomeSlugs());
    return BIOME_GUIDE_UNITS.filter((unit) => completedBiomes.has(this.normalizeBiomeSlug(unit.biome)));
  });

  ngOnInit(): void {
    void this.sessionService.initialize();
  }

  ngOnDestroy(): void {
    for (const timer of this.guideUnitAnimationTimers.values()) {
      window.clearInterval(timer);
    }
    this.guideUnitAnimationTimers.clear();
  }

  protected setActiveChapter(chapterId: GuideChapterId): void {
    this.activeChapter.set(chapterId);
  }

  protected handleChapterSelection(chapterId: string): void {
    this.setActiveChapter(chapterId as GuideChapterId);
  }

  protected isActiveChapter(chapterId: GuideChapterId): boolean {
    return this.activeChapter() === chapterId;
  }

  protected activeChapterMeta(): GuideChapter {
    return this.chapters.find((chapter) => chapter.id === this.activeChapter()) ?? this.chapters[0];
  }

  protected hasAcquiredFeatureUnlock(unlockKey: string): boolean {
    if (!this.session().isAuthenticated) {
      return false;
    }

    return this.profileData()?.feature_unlocks.includes(unlockKey) ?? false;
  }

  protected hasAcquiredUnitUnlock(unitSlug: string): boolean {
    if (!this.session().isAuthenticated) {
      return false;
    }

    return this.profileData()?.unit_type_unlocks.includes(unitSlug) ?? false;
  }

  protected unitArtUrl(unitSlug: string): string {
    return resolveUnitImageUrl(unitSlug) ?? '';
  }

  protected guideUnitFramePath(unit: GuideBestiaryUnit): string {
    const frameIndex = this.guideUnitFrameIndexes()[unit.slug] ?? 0;
    return `/assets/ui/units/animated/${unit.assetKey}/frame_${frameIndex}.png`;
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

  private diceImage(rarity: string, sides: number): string {
    return resolveDiceArtStyles(rarity, sides, 96).imageUrl;
  }

  private normalizeBiomeSlug(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, '_');
  }
}

import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { faLock } from '@fortawesome/free-solid-svg-icons';
import { resolveCompletedRegionSlugs } from '../../core/regions/region-catalog';
import { DiceAffixRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolvePrototypeEnemySpriteUrl, resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';

type CodexCategory = 'features' | 'units' | 'affixes' | 'enemies' | 'lore';

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

type GuideBestiaryUnit = {
  slug: string;
  name: string;
  biome: string;
  role: string;
  assetKey: string;
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
  selector: 'app-codex-page',
  standalone: true,
  imports: [FontAwesomeModule, PageFrameComponent],
  templateUrl: './codex-page.component.html',
  styleUrl: './codex-page.component.scss',
})
export class CodexPageComponent implements OnInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly guideUnitAnimationTimers = new Map<string, ReturnType<typeof window.setInterval>>();

  protected readonly session = this.sessionService.session;
  protected readonly profileData = this.sessionService.profileData;
  protected readonly hasActiveRun = this.sessionService.hasActiveRun;
  protected readonly guideUnitFrameIndexes = signal<Record<string, number>>({});
  protected readonly activeCategory = signal<CodexCategory>('features');
  protected readonly pageTitle = 'Codex';
  protected readonly pageSubtitle = 'A living record of your unlocks, sightings, and discoveries.';
  protected readonly breadcrumbs = [{ label: 'Codex' }];
  protected readonly faLock = faLock;
  protected readonly categories: ReadonlyArray<{ key: CodexCategory; label: string }> = [
    { key: 'features', label: 'Features' },
    { key: 'units', label: 'Units' },
    { key: 'affixes', label: 'Affixes' },
    { key: 'enemies', label: 'Enemies' },
    { key: 'lore', label: 'Lore' },
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

  protected readonly completedBiomeSlugs = computed(() => resolveCompletedRegionSlugs(this.profileData()));

  protected readonly discoveredBiomeUnits = computed(() => {
    const completedBiomes = new Set(this.completedBiomeSlugs());
    return BIOME_GUIDE_UNITS.filter((unit) => completedBiomes.has(this.normalizeBiomeSlug(unit.biome)));
  });

  protected readonly codexMetrics = computed<ReadonlyArray<CodexMetric>>(() => {
    const profile = this.profileData();
    const completedRegions = this.completedBiomeSlugs().length;
    const unlockedFeatures = profile?.feature_unlocks.length ?? 0;
    const unlockedUnits = profile?.unit_type_unlocks.length ?? 0;
    const seenAffixes = this.discoveredAffixNames().size;
    const loreCount = this.codexLoreEntries().length;

    return [
      {
        label: 'Feature unlocks',
        value: `${unlockedFeatures}/${this.unlocks.length}`,
        detail: unlockedFeatures ? 'Permanent account upgrades recovered.' : 'No permanent account upgrades recorded yet.',
        progressPercent: this.percent(unlockedFeatures, this.unlocks.length),
      },
      {
        label: 'Starter classes',
        value: `${unlockedUnits}/${this.unitUnlocks.length}`,
        detail: unlockedUnits ? 'Base class lines currently available in your warband.' : 'Recruit more unit lines to expand promotions.',
        progressPercent: this.percent(unlockedUnits, this.unitUnlocks.length),
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
        value: `${this.discoveredBiomeUnits().length}/${BIOME_GUIDE_UNITS.length}`,
        detail: completedRegions ? `${completedRegions} cleared region${completedRegions === 1 ? '' : 's'} feeding the bestiary.` : 'Clear regions to confirm enemy records.',
        progressPercent: this.percent(this.discoveredBiomeUnits().length, BIOME_GUIDE_UNITS.length),
      },
    ];
  });

  protected readonly codexAffixEntries = computed<ReadonlyArray<CodexAffixEntry>>(() => {
    const discovered = this.discoveredAffixNames();
    return this.affixes.map((affix) => ({
      ...affix,
      discovered: discovered.has(this.normalizeKey(affix.name)),
    }));
  });

  protected readonly codexEnemyEntries = computed<ReadonlyArray<CodexEnemyEntry>>(() => {
    const discovered = new Set(this.discoveredBiomeUnits().map((unit) => unit.slug));
    return BIOME_GUIDE_UNITS.map((unit) => ({
      ...unit,
      discovered: discovered.has(unit.slug),
    }));
  });

  protected readonly codexLoreEntries = computed<ReadonlyArray<CodexLoreEntry>>(() => {
    const seenDialogues = Array.from(new Set(this.profileData()?.seen_dialogues ?? []));
    return seenDialogues.map((entryId) => ({
      id: entryId,
      title: this.humanizeLabel(entryId),
      summary: 'Recovered from camp dialogue, region story beats, or another codex-worthy encounter.',
    }));
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

  protected setActiveCategory(category: CodexCategory): void {
    this.activeCategory.set(category);
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

  protected unitSpriteUrl(unitSlug: string): string {
    return resolvePrototypeUnitSpriteUrl(unitSlug);
  }

  protected enemySpriteUrl(enemySlug: string): string {
    return resolvePrototypeEnemySpriteUrl(enemySlug);
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

  private discoveredAffixNames(): Set<string> {
    return new Set(
      (this.profileData()?.dice ?? [])
        .flatMap((die) => die.affixes ?? [])
        .map((affix) => this.affixKey(affix))
        .filter((value): value is string => value.length > 0),
    );
  }

  private affixKey(affix: DiceAffixRecord): string {
    return this.normalizeKey(affix.name ?? affix.affix_slug ?? '');
  }

  private normalizeKey(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, '');
  }

  private humanizeLabel(value: string): string {
    return value
      .trim()
      .replace(/[_-]+/g, ' ')
      .replace(/\s+/g, ' ')
      .replace(/\b\w/g, (match) => match.toUpperCase());
  }

  private normalizeBiomeSlug(value: string): string {
    return value.trim().toLowerCase().replace(/\s+/g, '_');
  }

  private percent(value: number, total: number): number {
    if (total <= 0) {
      return 0;
    }

    return Math.max(0, Math.min(100, Math.round((value / total) * 100)));
  }
}

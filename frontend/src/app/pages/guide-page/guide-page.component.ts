import { Component, OnInit, inject } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
  faBolt,
  faBullseye,
  faCoins,
  faDiceD20,
  faFlag,
  faGraduationCap,
  faShieldHalved,
  faUsers,
  faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import { SessionService } from '../../core/services/session/session.service';
import { FEATURE_UNLOCK_CATEGORY_DETAILS, FeatureUnlockCategoryLabel } from '../../core/feature-unlocks/feature-unlock-categories';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';
import { DgSectionRailItem, SectionRailComponent } from '../../shared/ui/section-rail/section-rail.component';

type GuideStep = {
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

type GuideCombatStat = {
  name: string;
  description: string;
};

type GuideCombatFlowStep = {
  label: string;
  title: string;
  description: string;
};

type GuideIconEntry = {
  label: string;
  icon: IconDefinition;
  description: string;
};

const FEATURE_CATEGORY_ICON_BY_LABEL: Record<FeatureUnlockCategoryLabel, IconDefinition> = {
  'Feature Unlock': faGraduationCap,
  'Squad Upgrade': faUsers,
  'Economy Upgrade': faCoins,
  'Energy Upgrade': faBolt,
  'Dice Upgrade': faDiceD20,
};

const PUBLIC_GUIDE_STEPS: ReadonlyArray<GuideStep> = [
  {
    kicker: '1. Assemble',
    title: 'Build a squad around roles, not just levels',
    summary: 'Frontliners buy time, ranged units convert that time into damage, and support or control units smooth out bad rolls.',
  },
  {
    kicker: '2. Equip',
    title: 'Put your best dice on your highest-impact abilities',
    summary: 'An empty slot still resolves, but it resolves as a weak roll. Strong loadouts come from matching die size and affix style to the right skill.',
  },
  {
    kicker: '3. Venture',
    title: 'Use expeditions to cash in permanent progress',
    summary: 'Loot, XP, and unlocks mostly come from surviving runs long enough to return home and reinvest the rewards.',
  },
];

@Component({
  selector: 'app-guide-page',
  standalone: true,
  imports: [FontAwesomeModule, PageFrameComponent, SectionRailComponent],
  templateUrl: './guide-page.component.html',
  styleUrl: './guide-page.component.scss',
})
export class GuidePageComponent implements OnInit {
  private readonly sessionService = inject(SessionService);

  protected readonly breadcrumbs = [{ label: 'Guide' }];
  protected readonly guideSections: ReadonlyArray<DgSectionRailItem> = [
    { id: 'guide-loop', label: 'Base Loop', href: '#guide-loop' },
    { id: 'guide-combat', label: 'Combat Stats', href: '#guide-combat' },
    { id: 'guide-warband', label: 'Warband', href: '#guide-warband' },
    { id: 'guide-dice', label: 'Dice', href: '#guide-dice' },
    { id: 'guide-map', label: 'Map Glossary', href: '#guide-map' },
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
      name: 'Marksman',
      slug: 'backline_marksman_t1',
      role: 'Backline',
      tier: 1,
      maxLevel: 10,
      summary: 'A back-row damage dealer that can rush promotion at level 6 or stay to earn a targeting-focused capstone at level 10.',
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
      name: 'Shrine',
      icon: '/assets/ui/node-art/shrines/good_a.png',
      description: 'Resolve a route event for a visible favor result, usually without a fight.',
    },
    {
      name: 'Hazard',
      icon: '/assets/ui/icons/icon_encounter_locked.png',
      description: 'Handle route danger and review the result before returning to the map.',
    },
    {
      name: 'Chaos',
      icon: '/assets/ui/icons/icon_encounter_boss.png',
      description: 'Spin the Wrong Machine reels, lock the result, then fight the altered encounter.',
    },
    {
      name: 'Dialogue',
      icon: '/assets/ui/icons/icon_guide.png',
      description: 'Read story scenes and stolen-page discoveries that can unlock new context.',
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

  protected readonly combatStats: ReadonlyArray<GuideCombatStat> = [
    {
      name: 'Attack',
      description: 'Raises outgoing damage from offensive abilities before the defender gets a say.',
    },
    {
      name: 'Defense',
      description: 'Softens incoming hits and makes durable frontliners much better at buying time.',
    },
    {
      name: 'HP',
      description: 'Determines how much punishment a unit can take before being defeated for the current run.',
    },
    {
      name: 'Ability dice',
      description: 'The die assigned to an ability contributes the roll value, while affixes bend the result toward damage, defense, or special payoff.',
    },
    {
      name: 'Action order',
      description: 'Units act when their equipped abilities are ready. Lower speed costs cycle sooner, so loadout choices determine who acts most often.',
    },
    {
      name: 'Damage flow',
      description: 'Attacks combine ability value, die roll, and Attack, then the target Defense reduces the hit before HP is removed.',
    },
    {
      name: 'Precision and Resolve',
      description: 'Precision helps offensive effects land. Resolve helps resist harmful statuses and keeps fragile units from folding to control.',
    },
  ];

  protected readonly combatFlowSteps: ReadonlyArray<GuideCombatFlowStep> = [
    {
      label: '01',
      title: 'Ready abilities',
      description: 'Each unit uses its equipped abilities. Lower speed costs become ready sooner, so loadout speed shapes action frequency.',
    },
    {
      label: '02',
      title: 'Pick the action',
      description: 'When an ability is ready, the unit follows that ability definition for target rules, effect type, and any special conditions.',
    },
    {
      label: '03',
      title: 'Roll the assigned die',
      description: 'The full die roll is added to the action. Affixes and combat effects can then adjust damage, defense, healing, or control.',
    },
    {
      label: '04',
      title: 'Apply stats and effects',
      description: 'Offense combines ability value, die roll, Attack, and bonuses. The target uses Defense and active effects to soften hits.',
    },
    {
      label: '05',
      title: 'Update the board',
      description: 'HP, defeated state, cooldowns, statuses, and one-fight effects update before the next ready action is evaluated.',
    },
  ];

  protected readonly unitTypeIcons: ReadonlyArray<GuideIconEntry> = [
    { label: 'Frontline', icon: faShieldHalved, description: 'Durable unit types built to hold space and absorb pressure.' },
    { label: 'Backline', icon: faBullseye, description: 'Ranged unit types that convert protected turns into damage.' },
    { label: 'Support', icon: faFlag, description: 'Team-focused unit types that reinforce allies, tempo, or morale.' },
    { label: 'Utility', icon: faWandMagicSparkles, description: 'Disruptive unit types that interfere with enemy plans.' },
  ];

  protected readonly featureTypeIcons: ReadonlyArray<GuideIconEntry> = FEATURE_UNLOCK_CATEGORY_DETAILS.map((entry) => ({
    ...entry,
    icon: FEATURE_CATEGORY_ICON_BY_LABEL[entry.label],
  }));

  ngOnInit(): void {
    void this.sessionService.initialize();
  }

  protected unitSpriteUrl(unitSlug: string): string {
    return resolvePrototypeUnitSpriteUrl(unitSlug);
  }

  protected scrollToGuideSection(sectionId: string, event: Event): void {
    event.preventDefault();
    document.getElementById(sectionId)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  private diceImage(rarity: string, sides: number): string {
    return resolveDiceArtStyles(rarity, sides, 96).imageUrl;
  }

  protected readonly publicGuideSteps = PUBLIC_GUIDE_STEPS;
}

import { Component, OnInit, computed, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';

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

@Component({
  selector: 'app-guide-page',
  standalone: true,
  imports: [RouterLink, DgCommandBtnDirective],
  templateUrl: './guide-page.component.html',
  styleUrl: './guide-page.component.scss',
})
export class GuidePageComponent implements OnInit {
  private readonly sessionService = inject(SessionService);
  private readonly route = inject(ActivatedRoute);

  protected readonly session = this.sessionService.session;
  protected readonly profileData = this.sessionService.profileData;
  protected readonly hasActiveRun = this.sessionService.hasActiveRun;
  protected readonly heroEyebrow = computed(() => this.session().isAuthenticated ? 'Field Manual' : 'Public Field Manual');
  protected readonly returnUrl = computed(() => {
    if (!this.session().isAuthenticated) {
      return null;
    }

    const candidate = this.route.snapshot.queryParamMap.get('returnUrl');

    if (
      !candidate
      || !candidate.startsWith('/')
      || candidate.startsWith('//')
      || candidate === '/guide'
      || candidate.startsWith('/guide?')
      || candidate === '/field-guide'
      || candidate.startsWith('/field-guide?')
    ) {
      return null;
    }

    return candidate;
  });
  protected readonly primaryActionLabel = computed(() => {
    if (!this.session().isAuthenticated) {
      return 'Sign In';
    }

    if (this.returnUrl()) {
      return this.returnUrl()!.startsWith('/run/') ? 'Return to Run' : 'Back to Game';
    }

    return this.hasActiveRun() ? 'Return to Run' : 'Back to HQ';
  });
  protected readonly primaryActionRoute = computed(() => {
    if (!this.session().isAuthenticated) {
      return '/login';
    }

    if (this.returnUrl()) {
      return this.returnUrl()!;
    }

    return this.hasActiveRun() ? '/run/map' : '/home';
  });

  ngOnInit(): void {
    void this.sessionService.initialize();
  }

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
}

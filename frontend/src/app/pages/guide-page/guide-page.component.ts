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
      maxLevel: 6,
      summary: 'A durable frontliner built to absorb hits and keep pressure on the enemy line.',
    },
    {
      name: 'Guardian',
      slug: 'frontline_guardian_t1',
      role: 'Frontline',
      tier: 1,
      maxLevel: 6,
      summary: 'A shield-first defender that trades damage for stronger protection and staying power.',
    },
    {
      name: 'Marksman',
      slug: 'backline_marksman_t1',
      role: 'Backline',
      tier: 1,
      maxLevel: 6,
      summary: 'A ranged damage dealer that thrives from the back row with steady offensive pressure.',
    },
    {
      name: 'Bannerbearer',
      slug: 'support_banner_t1',
      role: 'Support',
      tier: 1,
      maxLevel: 8,
      summary: 'A support specialist that reinforces nearby allies and helps the squad endure longer fights.',
    },
    {
      name: 'Saboteur',
      slug: 'control_saboteur_t1',
      role: 'Utility',
      tier: 1,
      maxLevel: 8,
      summary: 'A disruptive skirmisher focused on interference, control, and breaking enemy momentum.',
    },
  ];

  protected readonly units: ReadonlyArray<GuideUnit> = [
    { name: 'Bruiser', slug: 'frontline_bruiser_t1', role: 'Frontline', tier: 1, maxLevel: 6, summary: 'Balanced offense and toughness for the front row.' },
    { name: 'Enforcer', slug: 'frontline_bruiser_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'A tougher bruiser upgrade that keeps scaling pressure.' },
    { name: 'Juggernaut', slug: 'frontline_bruiser_t3', role: 'Frontline', tier: 3, maxLevel: 14, summary: 'The heavy end of the bruiser path with the biggest body and strongest growth.' },
    { name: 'Guardian', slug: 'frontline_guardian_t1', role: 'Frontline', tier: 1, maxLevel: 6, summary: 'Defense-first tank for holding the line.' },
    { name: 'Bulwark', slug: 'frontline_guardian_t2', role: 'Frontline', tier: 2, maxLevel: 10, summary: 'A sturdier guardian with stronger defensive scaling.' },
    { name: 'Ironwall', slug: 'frontline_guardian_t3', role: 'Frontline', tier: 3, maxLevel: 14, summary: 'The toughest defensive wall in the guardian path.' },
    { name: 'Marksman', slug: 'backline_marksman_t1', role: 'Backline', tier: 1, maxLevel: 6, summary: 'Reliable ranged damage from safer back-row positions.' },
    { name: 'Deadeye', slug: 'backline_marksman_t2', role: 'Backline', tier: 2, maxLevel: 10, summary: 'A sharper ranged upgrade that keeps attack growth high.' },
    { name: 'Sharpshot', slug: 'backline_marksman_t3', role: 'Backline', tier: 3, maxLevel: 14, summary: 'The peak marksman path with elite ranged pressure.' },
    { name: 'Bannerbearer', slug: 'support_banner_t1', role: 'Support', tier: 1, maxLevel: 8, summary: 'Support path focused on keeping allies going.' },
    { name: 'Warcaller', slug: 'support_banner_t2', role: 'Support', tier: 2, maxLevel: 12, summary: 'An upgraded support path with sturdier stats and stronger staying power.' },
    { name: 'Saboteur', slug: 'control_saboteur_t1', role: 'Utility', tier: 1, maxLevel: 8, summary: 'Control-focused unit built around disruption.' },
    { name: 'Trickshot', slug: 'control_saboteur_t2', role: 'Utility', tier: 2, maxLevel: 12, summary: 'An upgraded saboteur path that keeps ranged interference online.' },
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

import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  DiceAffixRecord,
  DiceRecord,
  RunSummaryPayload,
  UnitRecord,
} from '../../core/models/api.models';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { UnitBarComponent } from '../../shared/ui/unit-bar/unit-bar.component';
import {
  resolveUnitSilhouetteUrl,
  resolveUnitThumbnailUrl,
} from '../../shared/ui/unit-art/unit-art';

type RewardUnitCard = {
  id: string;
  unit: UnitRecord;
};

type RewardDiceCard = {
  id: string;
  die: DiceRecord;
};

type RewardDisplayCard = {
  id: string;
  label: string;
  meta: string | null;
  imageUrl: string;
};

type RewardTextCard = {
  id: string;
  label: string;
  meta: string;
  imageUrl: string;
};

type SquadOutcomeUnit = {
  id: string;
  unit: UnitRecord;
  xpGained: number;
  defeated: boolean;
  positionLabel: string | null;
};

type ProgressionDetailEntry = NonNullable<RunSummaryPayload['progression_detail']>[number];

@Component({
  selector: 'app-run-summary-page',
  standalone: true,
  imports: [
    DgAlertComponent,
    DgCommandBtnDirective,
    PageFrameComponent,
    RouterLink,
    UnitBarComponent,
  ],
  templateUrl: './run-summary-page.component.html',
  styleUrl: './run-summary-page.component.scss',
})
export class RunSummaryPageComponent {
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly summary = this.runService.summary;
  readonly units = this.sessionService.units;
  readonly dice = this.sessionService.dice;
  readonly profileData = this.sessionService.profileData;
  readonly session = this.sessionService.session;
  readonly rewardUnitCards = computed<RewardDisplayCard[]>(() => {
    const structured = this.rewardUnits().map(({ id, unit }) => ({
      id,
      label: unit.name,
      meta: unit.unit_type_name ?? unit.unit_type_slug ?? 'Unit',
      imageUrl:
        resolveUnitThumbnailUrl(unit.unit_type_slug ?? unit.unit_type_name ?? unit.name) ??
        resolveUnitSilhouetteUrl(),
    }));
    const fallback = this.rewardUnitFallbackLabels().map((label, index) => ({
      id: `reward-unit-fallback-${index}-${label}`,
      label,
      meta: 'Unit',
      imageUrl: resolveUnitThumbnailUrl(label) ?? resolveUnitSilhouetteUrl(),
    }));

    return this.uniqueRewardCards([...structured, ...fallback]);
  });
  readonly rewardDiceCards = computed<RewardDisplayCard[]>(() => {
    const structured = this.rewardDice().map(({ id, die }) => ({
      id,
      label: this.diceLabel(die),
      meta: this.formatAffixNames(die.affixes ?? []) ?? this.diceLabel(die),
      imageUrl: resolveDiceArtStyles(die.rarity, die.sides, 96).imageUrl,
    }));
    const fallback = this.rewardDiceFallbackLabels().map((label, index) => ({
      id: `reward-dice-fallback-${index}-${label}`,
      label,
      meta: this.titleCaseDiceLabel(label),
      imageUrl: this.diceImageFromLabel(label),
    }));

    return this.uniqueRewardCards([...structured, ...fallback]);
  });
  readonly rewardUnitCount = computed(() => this.rewardUnitCards().length);
  readonly rewardDiceCount = computed(() => this.rewardDiceCards().length);
  readonly rewardItemCards = computed<RewardTextCard[]>(() => {
    const items = this.summary()?.rewardDetail?.items ?? [];
    return items.map((item, index) => ({
      id: `reward-item-${item.item_slug}-${index}`,
      label: item.quantity > 1 ? `${item.name} x${item.quantity}` : item.name,
      meta: this.humanizeId(item.rarity || 'item'),
      imageUrl: '/assets/ui/icons/icon_inventory.png',
    }));
  });
  readonly unlockCards = computed<RewardTextCard[]>(() => {
    const meta = this.summary()?.meta;
    const featureUnlocks = meta?.new_feature_unlocks ?? [];
    const regionUnlocks = meta?.new_region_unlocks ?? [];
    const cards: RewardTextCard[] = featureUnlocks.map((unlock) => ({
      id: `feature-unlock-${unlock}`,
      label: this.featureUnlockLabel(unlock),
      meta: 'Feature Unlocked',
      imageUrl: this.featureUnlockIcon(unlock),
    }));

    for (const regionSlug of regionUnlocks) {
      cards.push({
        id: `region-unlock-${regionSlug}`,
        label: this.regionUnlockLabel(regionSlug),
        meta: 'Region Unlocked',
        imageUrl: '/assets/ui/icons/icon_home.png',
      });
    }

    return cards;
  });
  readonly stolenPageCards = computed<RewardTextCard[]>(() =>
    (this.summary()?.stolenPages ?? []).map((page) => ({
      id: `stolen-page-${page.dialogue_id}`,
      label: page.title,
      meta: 'Stolen Page',
      imageUrl: '/assets/ui/icons/icon_guide.png',
    })),
  );
  readonly resultTitle = computed(() => {
    const status = this.summary()?.status ?? '';
    if (status.includes('abandon')) {
      return 'Returned Home';
    }
    if (status.includes('fail') || status.includes('defeat')) {
      return 'Run Failed';
    }
    return 'Run Complete';
  });
  readonly squadOutcomeUnits = computed<SquadOutcomeUnit[]>(() => {
    const summary = this.summary();
    if (!summary) {
      return [];
    }

    const unitsById = new Map(this.units().map((unit) => [unit.id, unit]));
    if (summary.progressionDetail.length > 0) {
      return summary.progressionDetail
        .map((entry, index) => this.squadOutcomeFromProgression(entry, unitsById, index))
        .filter((entry): entry is SquadOutcomeUnit => entry !== null);
    }

    const progressionByName = new Map(
      summary.progression
        .map((line) => this.parseProgressionLine(line))
        .filter((entry): entry is { unitName: string; xpGained: number } => entry !== null)
        .map((entry) => [entry.unitName.trim().toLowerCase(), entry.xpGained]),
    );
    const survivors = summary.survivors.map((name, index) =>
      this.squadOutcomeFromName(name, false, index, progressionByName),
    );
    const defeated = summary.defeated.map((name, index) =>
      this.squadOutcomeFromName(name, true, index, progressionByName),
    );

    return this.uniqueSquadOutcomeUnits([...survivors, ...defeated]);
  });

  readonly rewardCurrency = computed(() => {
    const rewardDetail = this.summary()?.rewardDetail;
    if (rewardDetail) {
      return Math.max(0, rewardDetail.currency_soft ?? 0);
    }

    const line = this.summary()?.rewards.find((item) => /^Teeth \+\d+$/i.test(item));
    if (!line) {
      return 0;
    }
    const match = line.match(/\+(\d+)/);
    return match ? Number(match[1]) : 0;
  });

  readonly rewardUnits = computed<RewardUnitCard[]>(() => {
    const rewardUnits = this.summary()?.rewardDetail?.units ?? [];
    if (rewardUnits.length === 0) {
      return [];
    }

    const unitsById = new Map(this.units().map((unit) => [unit.id, unit]));
    return rewardUnits
      .map((entry, index) => {
        if (!entry.unit_instance_id) {
          return null;
        }

        const unit = unitsById.get(entry.unit_instance_id);
        if (!unit) {
          return null;
        }

        return {
          id: `reward-unit-${entry.unit_instance_id}-${index}`,
          unit,
        };
      })
      .filter((entry): entry is RewardUnitCard => entry !== null);
  });

  readonly rewardUnitFallbackLabels = computed<string[]>(() => {
    const rewardUnits = this.summary()?.rewardDetail?.units ?? [];
    if (rewardUnits.length === 0) {
      const line = this.summary()?.rewards.find((item) => item.startsWith('New Units: '));
      return line ? this.expandCountList(line.replace('New Units: ', '')) : [];
    }

    const unitIds = new Set(this.units().map((unit) => unit.id));
    return rewardUnits
      .filter((entry) => !entry.unit_instance_id || !unitIds.has(entry.unit_instance_id))
      .map((entry) => entry.label)
      .filter((label) => label.trim().length > 0);
  });

  readonly rewardDice = computed<RewardDiceCard[]>(() => {
    const rewardDice = this.summary()?.rewardDetail?.dice ?? [];
    if (rewardDice.length === 0) {
      return [];
    }

    const diceById = new Map(this.dice().map((die) => [die.id, die]));
    return rewardDice
      .map((entry, index) => {
        if (!entry.dice_instance_id) {
          return null;
        }

        const die = diceById.get(entry.dice_instance_id);
        if (!die) {
          return null;
        }

        return {
          id: `reward-dice-${entry.dice_instance_id}-${index}`,
          die,
        };
      })
      .filter((entry): entry is RewardDiceCard => entry !== null);
  });

  readonly rewardDiceFallbackLabels = computed<string[]>(() => {
    const rewardDice = this.summary()?.rewardDetail?.dice ?? [];
    if (rewardDice.length === 0) {
      const line = this.summary()?.rewards.find((item) => item.startsWith('New Dice: '));
      return line
        ? this.expandCountList(line.replace('New Dice: ', '')).map((label) =>
            this.titleCaseDiceLabel(label),
          )
        : [];
    }

    const diceIds = new Set(this.dice().map((die) => die.id));
    return rewardDice
      .filter((entry) => !entry.dice_instance_id || !diceIds.has(entry.dice_instance_id))
      .map((entry) => entry.label)
      .filter((label) => label.trim().length > 0);
  });

  readonly rewardSectionsEmpty = computed(
    () =>
      this.rewardCurrency() <= 0 &&
      this.rewardUnitCards().length === 0 &&
      this.rewardDiceCards().length === 0 &&
      this.rewardItemCards().length === 0 &&
      this.unlockCards().length === 0 &&
      this.stolenPageCards().length === 0,
  );

  diceLabel(die: DiceRecord): string {
    if (typeof die.display_name === 'string' && die.display_name.trim()) {
      return die.display_name.trim();
    }

    const rarity =
      typeof die.rarity === 'string' && die.rarity.trim()
        ? `${die.rarity.trim().charAt(0).toUpperCase()}${die.rarity.trim().slice(1)} `
        : '';
    return `${rarity}d${die.sides ?? '?'}`.trim();
  }

  private parseProgressionLine(line: string): { unitName: string; xpGained: number } | null {
    const match = line.match(/^(.*) \+(\d+) XP$/);
    if (!match) {
      return null;
    }

    return {
      unitName: match[1].trim(),
      xpGained: Number(match[2]),
    };
  }

  private findUnitByName(unitName: string): UnitRecord | null {
    return (
      this.units().find(
        (unit) => unit.name.trim().toLowerCase() === unitName.trim().toLowerCase(),
      ) ?? null
    );
  }

  private progressionUnitForEntry(
    entry: ProgressionDetailEntry,
    unitsById: Map<string, UnitRecord>,
    index: number,
  ): UnitRecord | null {
    const currentUnit = unitsById.get(entry.unit_instance_id) ?? this.findUnitByName(entry.label);
    if (!currentUnit && typeof entry.final_level !== 'number') {
      return null;
    }

    const fallbackName = entry.label?.trim()
      ? entry.label
      : `Unit ${entry.unit_instance_id || index}`;
    return {
      ...(currentUnit ?? { id: entry.unit_instance_id || `summary-${index}`, name: fallbackName }),
      id: entry.unit_instance_id || currentUnit?.id || `summary-${index}`,
      name: currentUnit?.name ?? fallbackName,
      level: typeof entry.final_level === 'number' ? entry.final_level : (currentUnit?.level ?? 1),
      xp: typeof entry.final_xp === 'number' ? entry.final_xp : (currentUnit?.xp ?? 0),
      xp_to_next_level:
        typeof entry.xp_to_next_level === 'number'
          ? entry.xp_to_next_level
          : (currentUnit?.xp_to_next_level ?? 0),
      tier: typeof entry.tier === 'number' ? entry.tier : (currentUnit?.tier ?? 1),
      max_level:
        typeof entry.max_level === 'number' ? entry.max_level : (currentUnit?.max_level ?? 1),
      unit_type_name: entry.unit_type_name || currentUnit?.unit_type_name,
      current_hp: currentUnit?.current_hp ?? currentUnit?.max_hp ?? 1,
      max_hp: currentUnit?.max_hp ?? currentUnit?.current_hp ?? 1,
    };
  }

  private squadOutcomeFromProgression(
    entry: ProgressionDetailEntry,
    unitsById: Map<string, UnitRecord>,
    index: number,
  ): SquadOutcomeUnit | null {
    const unit = this.progressionUnitForEntry(entry, unitsById, index);
    if (!unit) {
      return null;
    }

    const levelGainCount = Math.max(0, entry.level_gain_count ?? 0);
    return {
      id: entry.unit_instance_id || `outcome-${index}`,
      unit,
      xpGained: entry.xp_gained,
      defeated: !!entry.is_defeated,
      positionLabel: this.outcomeLabel(entry.xp_gained, levelGainCount),
    };
  }

  private squadOutcomeFromName(
    name: string,
    defeated: boolean,
    index: number,
    progressionByName: Map<string, number>,
  ): SquadOutcomeUnit {
    const unit =
      this.findUnitByName(name) ??
      this.fallbackUnit(name, `${defeated ? 'defeated' : 'survivor'}-${index}`);
    const xpGained = progressionByName.get(name.trim().toLowerCase()) ?? 0;

    return {
      id: `${defeated ? 'defeated' : 'survivor'}-${unit.id}-${index}`,
      unit,
      xpGained,
      defeated,
      positionLabel: defeated ? null : this.outcomeLabel(xpGained, 0),
    };
  }

  private outcomeLabel(xpGained: number, levelGainCount: number): string | null {
    const labels: string[] = [];
    if (xpGained > 0) {
      labels.push(`+${xpGained} XP`);
    }
    if (levelGainCount > 0) {
      labels.push(levelGainCount === 1 ? 'Level Up' : `+${levelGainCount} Levels`);
    }

    return labels.length ? labels.join(' - ') : null;
  }

  private fallbackUnit(name: string, id: string): UnitRecord {
    return {
      id,
      name,
      level: 1,
      tier: 1,
      max_level: 1,
      current_hp: 1,
      max_hp: 1,
      xp: 0,
      xp_to_next_level: 0,
      unit_type_name: 'Unit',
    };
  }

  private expandCountList(line: string): string[] {
    if (!line.trim()) {
      return [];
    }

    const entries = line
      .split(',')
      .map((item) => item.trim())
      .filter((item) => item.length > 0);
    const expanded: string[] = [];

    for (const entry of entries) {
      const match = entry.match(/^(.*) x(\d+)$/);
      const label = (match?.[1] ?? entry).trim();
      const count = Number(match?.[2] ?? '1');
      const repetitions = Number.isFinite(count) && count > 0 ? count : 1;

      for (let index = 0; index < repetitions; index++) {
        expanded.push(label);
      }
    }

    return expanded;
  }

  private titleCaseDiceLabel(label: string): string {
    return label.replace(/\b\w/g, (char) => char.toUpperCase());
  }

  private formatAffixNames(affixes: DiceAffixRecord[]): string | null {
    const names = affixes
      .map((affix) => (affix.name ?? this.humanizeId(affix.affix_slug ?? '')).trim())
      .filter((name) => name.length > 0);

    return names.length > 0 ? names.join(' ') : null;
  }

  private diceImageFromLabel(label: string): string {
    const match = label.trim().match(/\bd(\d+)\b/i);
    const rarity = label.trim().match(/^(common|uncommon|rare|epic|legendary)\b/i)?.[1] ?? 'common';
    return resolveDiceArtStyles(rarity, Number(match?.[1] ?? 6), 96).imageUrl;
  }

  private featureUnlockLabel(unlock: string): string {
    return this.humanizeId(unlock);
  }

  private featureUnlockIcon(unlock: string): string {
    if (unlock === 'shop') {
      return '/assets/ui/icons/icon_shop.png';
    }
    if (unlock === 'wrong_machine') {
      return '/assets/ui/icons/icon_encounter_locked.png';
    }

    return '/assets/ui/icons/icon_home.png';
  }

  private regionUnlockLabel(regionSlug: string): string {
    const region = this.profileData()?.regions?.find((entry) => entry.slug === regionSlug);
    return region?.name ?? this.humanizeId(regionSlug);
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }

  private uniqueRewardCards(cards: RewardDisplayCard[]): RewardDisplayCard[] {
    const seen = new Set<string>();
    return cards.filter((card) => {
      const normalized = card.label.trim().toLowerCase();
      if (!normalized || seen.has(normalized)) {
        return false;
      }
      seen.add(normalized);
      return true;
    });
  }

  private uniqueSquadOutcomeUnits(units: SquadOutcomeUnit[]): SquadOutcomeUnit[] {
    const seen = new Set<string>();
    return units.filter((entry) => {
      const key = entry.unit.id || entry.unit.name.trim().toLowerCase();
      if (seen.has(key)) {
        return false;
      }
      seen.add(key);
      return true;
    });
  }
}

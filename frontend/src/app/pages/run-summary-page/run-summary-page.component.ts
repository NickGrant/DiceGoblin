import { Component, computed, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DiceRecord, RunSummaryPayload, UnitRecord } from '../../core/models/api.models';
import { DialogueChoiceSelection, DialogueScript } from '../../core/dialogue/dialogue.models';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';
import {
  UnitGridObjectComponent,
  UnitGridObjectProgressBar,
} from '../../shared/ui/unit-grid-object/unit-grid-object.component';

type RewardUnitCard = {
  id: string;
  unit: UnitRecord;
};

type RewardDiceCard = {
  id: string;
  die: DiceRecord;
};

type ProgressionCard = {
  id: string;
  unit: UnitRecord;
  xpGained: number;
  progressPercent: number;
  progressText: string;
  levelGainCount: number;
};

type ProgressionDetailEntry = NonNullable<RunSummaryPayload['progression_detail']>[number];
type SquadOutcomeCard = {
  id: string;
  name: string;
  role: string;
  level: number;
  xpGained: number;
  defeated: boolean;
  spriteUrl: string;
};

@Component({
  selector: 'app-run-summary-page',
  standalone: true,
  imports: [
    DgAlertComponent,
    DgCommandBtnDirective,
    DgDialogueStageComponent,
    PageFrameComponent,
    RouterLink,
    UnitGridObjectComponent,
  ],
  templateUrl: './run-summary-page.component.html',
  styleUrl: './run-summary-page.component.scss',
})
export class RunSummaryPageComponent {
  private static readonly SHOP_UNLOCK_DIALOGUE_ID = 'farm-shop-unlock';
  private static readonly PLAYER_DIALOGUE_PORTRAIT =
    '/assets/dialogue/portraits/goblin/base_frame_0.png';

  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);
  private lastDialogueSummaryKey: string | null = null;

  readonly summary = this.runService.summary;
  readonly units = this.sessionService.units;
  readonly dice = this.sessionService.dice;
  readonly profileData = this.sessionService.profileData;
  readonly session = this.sessionService.session;
  readonly summaryDialogue = signal<DialogueScript | null>(null);
  readonly rewardUnitLabels = computed<string[]>(() => {
    const structured = this.rewardUnits().map((card) => card.unit.name);
    return this.uniqueLabels([...structured, ...this.rewardUnitFallbackLabels()]);
  });
  readonly rewardDiceLabels = computed<string[]>(() => {
    const structured = this.rewardDice().map((card) => this.diceLabel(card.die));
    return this.uniqueLabels([...structured, ...this.rewardDiceFallbackLabels()]);
  });
  readonly rewardUnitCount = computed(() => this.rewardUnitLabels().length);
  readonly rewardDiceCount = computed(() => this.rewardDiceLabels().length);
  readonly resultTitle = computed(() => {
    const status = this.summary()?.status ?? '';
    if (status.includes('abandon')) {
      return 'Run Abandoned';
    }
    if (status.includes('fail') || status.includes('defeat')) {
      return 'Run Failed';
    }
    return 'Run Complete';
  });
  readonly squadOutcomeCards = computed<SquadOutcomeCard[]>(() => {
    const summary = this.summary();
    if (!summary) {
      return [];
    }

    const unitsById = new Map(this.units().map((unit) => [unit.id, unit]));
    if (summary.progressionDetail.length > 0) {
      return summary.progressionDetail.map((entry, index) => {
        const unit = this.progressionUnitForEntry(entry, unitsById, index);
        return {
          id: entry.unit_instance_id || `outcome-${index}`,
          name: unit?.name ?? entry.label,
          role: unit?.unit_type_name ?? entry.unit_type_name ?? 'Raider',
          level: unit?.level ?? entry.final_level ?? 1,
          xpGained: entry.xp_gained,
          defeated: !!entry.is_defeated,
          spriteUrl: resolvePrototypeUnitSpriteUrl(unit ?? entry.label),
        };
      });
    }

    const survivors = summary.survivors.map((name, index) => ({
      id: `survivor-${index}-${name}`,
      name,
      role: this.findUnitByName(name)?.unit_type_name ?? 'Raider',
      level: this.findUnitByName(name)?.level ?? 1,
      xpGained: 0,
      defeated: false,
      spriteUrl: resolvePrototypeUnitSpriteUrl(this.findUnitByName(name) ?? name),
    }));
    const defeated = summary.defeated.map((name, index) => ({
      id: `defeated-${index}-${name}`,
      name,
      role: this.findUnitByName(name)?.unit_type_name ?? 'Raider',
      level: this.findUnitByName(name)?.level ?? 1,
      xpGained: 0,
      defeated: true,
      spriteUrl: resolvePrototypeUnitSpriteUrl(this.findUnitByName(name) ?? name),
    }));

    return [...survivors, ...defeated];
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

  readonly progressionCards = computed<ProgressionCard[]>(() => {
    const summary = this.summary();
    if (!summary) {
      return [];
    }

    const unitsById = new Map(this.units().map((unit) => [unit.id, unit]));
    if (summary.progressionDetail.length > 0) {
      return summary.progressionDetail
        .map((entry, index) => {
          const unit = this.progressionUnitForEntry(entry, unitsById, index);
          if (!unit) {
            return null;
          }

          return this.buildProgressionCard(
            `progression-${entry.unit_instance_id || unit.id || index}-${index}`,
            unit,
            entry.xp_gained,
            typeof entry.level_gain_count === 'number'
              ? Math.max(0, entry.level_gain_count)
              : undefined,
          );
        })
        .filter((entry): entry is ProgressionCard => entry !== null);
    }

    return summary.progression
      .map((line, index) => {
        const parsed = this.parseProgressionLine(line);
        if (!parsed) {
          return null;
        }

        const unit = this.findUnitByName(parsed.unitName);
        if (!unit) {
          return null;
        }

        return this.buildProgressionCard(`progression-${unit.id}-${index}`, unit, parsed.xpGained);
      })
      .filter((entry): entry is ProgressionCard => entry !== null);
  });

  readonly rewardSectionsEmpty = computed(
    () =>
      this.rewardCurrency() <= 0 &&
      this.rewardUnits().length === 0 &&
      this.rewardDice().length === 0 &&
      this.rewardUnitFallbackLabels().length === 0 &&
      this.rewardDiceFallbackLabels().length === 0,
  );

  constructor() {
    effect(() => {
      const summary = this.summary();
      const profileData = this.profileData();
      if (!summary || !profileData) {
        this.lastDialogueSummaryKey = null;
        return;
      }

      const summaryKey = [
        summary.title,
        summary.status,
        summary.meta?.completed_region_slug ?? '',
        (summary.meta?.new_feature_unlocks ?? []).join(','),
      ].join('|');
      if (summaryKey === this.lastDialogueSummaryKey) {
        return;
      }
      this.lastDialogueSummaryKey = summaryKey;

      if (
        summary.meta?.completed_region_slug !== 'the_farm' ||
        !(summary.meta?.new_feature_unlocks ?? []).includes('shop') ||
        (profileData.seen_dialogues ?? []).includes(RunSummaryPageComponent.SHOP_UNLOCK_DIALOGUE_ID)
      ) {
        return;
      }

      void this.loadShopUnlockDialogue();
    });
  }

  progressBar(card: ProgressionCard): UnitGridObjectProgressBar {
    return {
      percent: card.progressPercent,
      title: `${card.progressText}, +${card.xpGained} XP${card.levelGainCount > 0 ? `, ${card.levelGainCount === 1 ? 'level gained' : `${card.levelGainCount} levels gained`}` : ''}`,
      leftLabel: card.progressText === 'Max Level' ? card.progressText : `XP ${card.progressText}`,
      tone: 'xp',
      celebrationLabel:
        card.levelGainCount > 0
          ? card.levelGainCount === 1
            ? 'Level Up'
            : `+${card.levelGainCount} Levels`
          : null,
      showLabels: false,
    };
  }

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

  private buildProgressionCard(
    id: string,
    unit: UnitRecord,
    xpGained: number,
    explicitLevelGainCount?: number,
  ): ProgressionCard {
    const progress = this.buildProgressMetrics(unit, xpGained, explicitLevelGainCount);
    return {
      id,
      unit,
      xpGained,
      progressPercent: progress.progressPercent,
      progressText: progress.progressText,
      levelGainCount: progress.levelGainCount,
    };
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

  private buildProgressMetrics(
    unit: UnitRecord,
    xpGained: number,
    explicitLevelGainCount?: number,
  ): { progressPercent: number; progressText: string; levelGainCount: number } {
    const level = Math.max(1, unit.level ?? 1);
    const maxLevel = Math.max(1, unit.max_level ?? 1);
    const tier = Math.max(1, unit.tier ?? 1);
    const currentXp = Math.max(0, unit.xp ?? 0);
    const xpToNextLevel = Math.max(0, unit.xp_to_next_level ?? 0);

    if (level >= maxLevel || xpToNextLevel === 0) {
      return {
        progressPercent: 100,
        progressText: 'Max Level',
        levelGainCount:
          explicitLevelGainCount ?? this.estimateLevelGainCount(level, currentXp, xpGained, tier),
      };
    }

    const threshold = currentXp + xpToNextLevel;
    const progressPercent =
      threshold > 0 ? Math.max(0, Math.min(100, (currentXp / threshold) * 100)) : 0;

    return {
      progressPercent,
      progressText: `${currentXp}/${threshold} XP`,
      levelGainCount:
        explicitLevelGainCount ?? this.estimateLevelGainCount(level, currentXp, xpGained, tier),
    };
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
    };
  }

  private estimateLevelGainCount(
    finalLevel: number,
    finalXp: number,
    xpGained: number,
    tier: number,
  ): number {
    let level = Math.max(1, finalLevel);
    let remaining = Math.max(0, xpGained);
    const xp = Math.max(0, finalXp);
    let levelGainCount = 0;

    if (remaining <= xp) {
      return 0;
    }

    remaining -= xp;
    while (remaining > 0 && level > 1) {
      level--;
      levelGainCount++;
      const threshold = this.levelThreshold(level, tier);
      if (remaining <= threshold) {
        break;
      }

      remaining -= threshold;
    }

    return levelGainCount;
  }

  private levelThreshold(level: number, tier: number): number {
    return Math.max(1, Math.max(1, tier) * (Math.max(1, level) + 1) * 50);
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

  private uniqueLabels(labels: string[]): string[] {
    const seen = new Set<string>();
    return labels.filter((label) => {
      const normalized = label.trim().toLowerCase();
      if (!normalized || seen.has(normalized)) {
        return false;
      }
      seen.add(normalized);
      return true;
    });
  }

  async handleSummaryDialogueComplete(_choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    this.summaryDialogue.set(null);

    try {
      await this.dialogueService.markDialogueSeen(RunSummaryPageComponent.SHOP_UNLOCK_DIALOGUE_ID);
      await this.sessionService.refreshProfile({ force: true });
    } catch {
      // Keep the summary usable if dialogue persistence fails.
    }
  }

  private async loadShopUnlockDialogue(): Promise<void> {
    try {
      const dialogue = await this.dialogueService.getDialogue({
        scene: 'run-summary',
        regionSlug: 'the_farm',
        tags: ['shop-unlocked'],
        playerName: this.session().displayName,
        playerPortraitUrl: RunSummaryPageComponent.PLAYER_DIALOGUE_PORTRAIT,
      });

      if (dialogue) {
        this.summaryDialogue.set(dialogue);
      }
    } catch {
      // Keep the summary usable if dialogue assets fail to load.
    }
  }
}

import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { DiceAffixRecord, ResolveNodeData, RewardPreviewDice, RewardPreviewUnit } from '../../core/models/api.models';
import { resolveRegionTheme } from '../../core/regions/region-catalog';
import { RunService } from '../../core/services/run/run.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { resolveUnitSilhouetteUrl, resolveUnitThumbnailUrl } from '../../shared/ui/unit-art/unit-art';
import { formatSpliceVariantLabel } from '../../shared/utils/unit-formatters';

type LootRewardSummary = {
  teeth: number;
  dice: RewardPreviewDice[];
  units: RewardPreviewUnit[];
};

type LootDisplayCard = {
  id: string;
  label: string;
  meta: string | null;
  imageUrl: string;
  stats?: Array<{ label: string; value: string }>;
};

@Component({
  selector: 'app-run-loot-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent],
  templateUrl: './run-loot-page.component.html',
  styleUrl: './run-loot-page.component.scss',
})
export class RunLootPageComponent {
  private static readonly LOOT_TITLE = 'A respectable acquisition of wealth';
  private static readonly LOOT_SUBTITLE = 'No heroism required, just strong knees and stronger pockets.';

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly regionTheme = signal<string | null>(null);

  readonly pageTitle = computed(() => RunLootPageComponent.LOOT_TITLE);
  readonly pageSubtitle = computed(() => this.result() ? RunLootPageComponent.LOOT_SUBTITLE : '');
  readonly battleStatusLabel = computed(() => this.humanizeId(this.result()?.battle.status ?? 'pending'));
  readonly unlockedNodeCount = computed(() => this.result()?.next.unlocked_node_ids.length ?? 0);
  readonly claimButtonLabel = computed(() => this.busy() ? 'Working...' : 'Claim Treasure');
  readonly lootBackgroundImage = computed(() =>
    `linear-gradient(180deg, rgba(24, 18, 12, 0.38), rgba(24, 18, 12, 0.74)), url('${this.lootBackgroundUrl()}')`,
  );
  readonly lootRewards = computed<LootRewardSummary | null>(() => {
    const rewards = this.result()?.battle.reward_preview;
    if (!rewards || rewards.node_type !== 'loot') {
      return null;
    }

    return {
      teeth: rewards.currency_soft,
      dice: rewards.dice?.length
        ? rewards.dice
        : rewards.new_dice_labels.map((label) => this.diceRewardFromLabel(label)),
      units: rewards.units?.length
        ? rewards.units
        : rewards.new_unit_labels.map((label) => this.unitRewardFromLabel(label)),
    };
  });
  readonly lootUnitCards = computed<LootDisplayCard[]>(() => {
    return (this.lootRewards()?.units ?? []).map((unit, index) => ({
      id: unit.unit_instance_id ?? `unit-${index}-${unit.name}`,
      label: unit.name,
      meta: this.unitRewardMeta(unit),
      imageUrl: resolveUnitThumbnailUrl(unit.unit_type_slug ?? unit.unit_type_name) ?? resolveUnitSilhouetteUrl(),
      stats: this.unitRewardStats(unit),
    }));
  });
  readonly lootDiceCards = computed<LootDisplayCard[]>(() => {
    return (this.lootRewards()?.dice ?? []).map((die, index) => ({
      id: die.dice_instance_id ?? `die-${index}-${die.label}`,
      label: die.label,
      meta: this.formatAffixNames(die.affixes),
      imageUrl: this.lootDieImage(die),
    }));
  });
  readonly hasLootRewards = computed(() =>
    (this.lootRewards()?.teeth ?? 0) > 0 || this.lootDiceCards().length > 0 || this.lootUnitCards().length > 0,
  );

  constructor() {
    void this.loadRun();
  }

  async loadRun(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const current = await this.runService.getCurrentRun();
      if (!current.ok || !current.data.run) {
        this.error.set(current.ok ? 'No active run.' : current.error.message);
        return;
      }

      const currentNode = current.data.map?.nodes.find((node) => node.id === this.nodeId) ?? null;
      this.runId.set(current.data.run.run_id);
      this.regionTheme.set(resolveRegionTheme(current.data.run.region_slug ?? null, current.data.run.region_theme ?? null));

      if (currentNode?.node_type !== 'loot') {
        await this.router.navigate(['/run/node', this.nodeId]);
        return;
      }

      await this.resolveNode();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load loot.');
    } finally {
      this.loading.set(false);
    }
  }

  async resolveNode(): Promise<void> {
    if (!this.runId()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.resolveNode(this.runId()!, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.result.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to resolve loot.');
    } finally {
      this.busy.set(false);
    }
  }

  async claimRewards(): Promise<void> {
    const battleId = this.result()?.battle.battle_id;
    if (!battleId) {
      await this.router.navigateByUrl('/run/map');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.claimBattleRewards(battleId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      if (response.data.run_resolution?.status && response.data.run_resolution.status !== 'active') {
        await this.router.navigateByUrl('/run/summary');
      } else {
        await this.router.navigateByUrl('/run/map');
      }
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to claim treasure.');
    } finally {
      this.busy.set(false);
    }
  }

  private lootDieImage(die: RewardPreviewDice): string {
    if (die.material || die.sides) {
      const material = this.normalizeDiceMaterial(die.material);
      const sides = this.normalizeDiceSides(Number(die.sides));
      return `/assets/ui/dice/${material}_d${sides}.png`;
    }

    const label = die.label;
    const match = label.trim().match(/^([a-z]+)\s+d(\d+)\b/i);
    if (!match) {
      return resolveDiceArtStyles('common', 6, 96).imageUrl;
    }

    const material = this.normalizeDiceMaterial(match[1]);
    const sides = this.normalizeDiceSides(Number(match[2]));
    return `/assets/ui/dice/${material}_d${sides}.png`;
  }

  private formatAffixNames(affixes: DiceAffixRecord[]): string | null {
    const names = affixes
      .map((affix) => (affix.name ?? this.humanizeId(affix.affix_slug ?? '')).trim())
      .filter((name) => name.length > 0);

    return names.length > 0 ? names.join(' ') : null;
  }

  private diceRewardFromLabel(label: string): RewardPreviewDice {
    const match = label.trim().match(/^([a-z]+)\s+d(\d+)\b/i);
    const material = this.normalizeDiceMaterial(match?.[1] ?? 'cardboard');
    const sides = this.normalizeDiceSides(Number(match?.[2] ?? 6));

    return {
      dice_instance_id: null,
      label,
      rarity: 'common',
      material,
      sides,
      affixes: [],
    };
  }

  private unitRewardFromLabel(label: string): RewardPreviewUnit {
    return {
      unit_instance_id: null,
      name: label,
      unit_type_slug: null,
      unit_type_name: label,
      splice_variant_slug: null,
      splice_variant_name: null,
      tier: 1,
      level: 1,
    };
  }

  private unitRewardMeta(unit: RewardPreviewUnit): string {
    const typeName = unit.unit_type_name || 'Unit';
    const spliceName = formatSpliceVariantLabel(unit.splice_variant_name, unit.splice_variant_slug);
    return `${typeName} - ${spliceName}`;
  }

  private unitRewardStats(unit: RewardPreviewUnit): Array<{ label: string; value: string }> {
    return [
      { label: 'ATK', value: this.statValue(unit.total_attack) },
      { label: 'DEF', value: this.statValue(unit.total_defense) },
      { label: 'PRC', value: this.statValue(unit.total_precision) },
      { label: 'RES', value: this.statValue(unit.total_resolve) },
      { label: 'HP', value: this.statValue(unit.max_hp) },
    ];
  }

  private statValue(value: number | null | undefined): string {
    return typeof value === 'number' ? `${value}` : '-';
  }

  private normalizeDiceMaterial(value: string | null | undefined): string {
    const material = (value ?? '').trim().toLowerCase();
    if (['cardboard', 'wood', 'bone', 'metal', 'gemstone'].includes(material)) {
      return material;
    }

    const rarityToMaterial: Record<string, string> = {
      common: 'cardboard',
      uncommon: 'wood',
      rare: 'bone',
      epic: 'metal',
      legendary: 'gemstone',
    };

    return rarityToMaterial[material] ?? 'cardboard';
  }

  private normalizeDiceSides(value: number): number {
    if (value <= 4) {
      return 4;
    }
    if (value <= 6) {
      return 6;
    }
    if (value <= 8) {
      return 8;
    }
    if (value <= 10) {
      return 10;
    }
    if (value <= 12) {
      return 12;
    }

    return 20;
  }

  private lootBackgroundUrl(): string {
    const theme = this.regionTheme();
    if (theme === 'farm') {
      return '/assets/ui/biome/farm.png';
    }

    if (theme === 'mountain') {
      return '/assets/ui/biome/mountain.png';
    }

    if (theme === 'swamp') {
      return '/assets/ui/biome/swamp.png';
    }

    return '/assets/ui/biome/mystic_cave.png';
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }
}

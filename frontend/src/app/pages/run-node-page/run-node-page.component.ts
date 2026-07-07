import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ResolveNodeData, UnitRecord } from '../../core/models/api.models';
import { DialogueChoiceSelection, DialogueScript, DialogueTriggerContext } from '../../core/dialogue/dialogue.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveUnitImageUrl } from '../../shared/ui/unit-art/unit-art';
import { UnitGridObjectComponent, UnitGridObjectProgressBar } from '../../shared/ui/unit-grid-object/unit-grid-object.component';

const AUTO_RESOLVE_NODE_TYPES = new Set(['combat', 'boss', 'loot']);
const REGION_THEME_BY_SLUG: Record<string, string> = {
  the_farm: 'farm',
  mountains: 'mountain',
  swamps: 'swamp',
};

type BattleLogActionViewModel = {
  round: number;
  tick: number;
  side: string;
  actorName: string;
  actorCard: BattleLogUnitCardViewModel;
  abilityName: string;
  diceSummary: string;
  targetName: string;
  targetCard: BattleLogUnitCardViewModel;
  resultSummary: string;
  resultSegments: BattleLogResultSegmentViewModel[];
};

type BattleLogUnitCardViewModel = {
  unit: UnitRecord;
  subtitle: string;
  tone: 'default' | 'enemy';
  progressBar: UnitGridObjectProgressBar | null;
  showLockBadge: boolean;
};

type LootRewardSummary = {
  teeth: number;
  diceLabels: string[];
  unitLabels: string[];
};

type BattleLogResultSegmentViewModel = {
  text: string;
  tooltip: string | null;
};

type ConditionDefinition = {
  aliases: string[];
  tooltip: string;
};

@Component({
  selector: 'app-run-node-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgDialogueStageComponent, PageFrameComponent, UnitGridObjectComponent],
  templateUrl: './run-node-page.component.html',
  styleUrl: './run-node-page.component.scss',
})
export class RunNodePageComponent {
  private static readonly BATTLE_TITLE = 'BATTLE!';
  private static readonly LOOT_TITLE = 'A respectable acquisition of wealth';
  private static readonly BATTLE_SUBTITLE = 'Several goblins have volunteered to be an educational example.';
  private static readonly LOOT_SUBTITLE = 'No heroism required, just strong knees and stronger pockets.';
  private static readonly CONDITION_DEFINITIONS: ConditionDefinition[] = [
    {
      aliases: ['poisoned', 'poison'],
      tooltip: 'Poison deals damage during status phases. It does not stack, and reapplying it refreshes duration.',
    },
    {
      aliases: ['bleeding', 'bleed'],
      tooltip: 'Bleeding increases damage received for a limited duration.',
    },
    {
      aliases: ['bolstered', 'bolster'],
      tooltip: 'Bolstered is a defensive buff that increases Defense by a percentage for a limited duration.',
    },
    {
      aliases: ['sleep', 'sleeping', 'asleep'],
      tooltip: 'Sleep prevents a unit from acting until it expires or the unit takes damage.',
    },
  ];

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly dialogue = signal<DialogueScript | null>(null);
  readonly dialogueChoiceHistory = signal<DialogueChoiceSelection[]>([]);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly nodeType = signal<string | null>(null);
  readonly shouldAutoResolve = computed(() => AUTO_RESOLVE_NODE_TYPES.has(this.nodeType() ?? ''));
  readonly abilityCatalogError = this.abilityCatalogService.error;
  readonly abilityCatalog = this.abilityCatalogService.abilityMap;
  readonly resolvedNodeType = computed(() => {
    const previewType = this.result()?.battle.reward_preview?.node_type;
    if (typeof previewType === 'string' && previewType.length > 0) {
      return previewType;
    }

    const metaNodeType = this.result()?.battle.log?.meta?.['node_type'];
    return typeof metaNodeType === 'string' ? metaNodeType : 'combat';
  });
  readonly isLootNode = computed(() => this.resolvedNodeType() === 'loot');
  readonly pageTitle = computed(() => {
    if (this.isLootNode() || this.nodeType() === 'loot') {
      return RunNodePageComponent.LOOT_TITLE;
    }

    if (this.shouldAutoResolve()) {
      return RunNodePageComponent.BATTLE_TITLE;
    }

    return `Node ${this.nodeId}`;
  });
  readonly pageSubtitle = computed(() => {
    if (!this.result()) {
      return '';
    }

    return this.isLootNode()
      ? RunNodePageComponent.LOOT_SUBTITLE
      : RunNodePageComponent.BATTLE_SUBTITLE;
  });
  readonly unlockedNodeCount = computed(() => this.result()?.next.unlocked_node_ids.length ?? 0);
  readonly claimButtonLabel = computed(() => {
    if (this.busy()) {
      return 'Working...';
    }

    return this.isLootNode() ? 'Claim Treasure' : 'Claim Rewards';
  });
  readonly lootRewards = computed<LootRewardSummary | null>(() => {
    const preview = this.result()?.battle.reward_preview;
    if (!preview || this.resolvedNodeType() !== 'loot') {
      return null;
    }

    return {
      teeth: this.numberValue(preview.currency_soft),
      diceLabels: Array.isArray(preview.new_dice_labels) ? preview.new_dice_labels : [],
      unitLabels: Array.isArray(preview.new_unit_labels) ? preview.new_unit_labels : [],
    };
  });
  readonly actionLog = computed<BattleLogActionViewModel[]>(() => {
    const log = this.result()?.battle.log;
    const events = Array.isArray(log?.events) ? log.events : [];

    return events
      .filter((event): event is Record<string, unknown> => event !== null && typeof event === 'object' && event['type'] === 'action')
      .map((event) => {
        const round = this.numberValue(event['round']);
        const tick = this.numberValue(event['tick']);
        const abilityId = this.stringValue(event['ability_id']);
        const resultParts = this.resultParts(event);
        return {
          round,
          tick,
          side: this.stringValue(event['side']) || 'player',
          actorName: this.resolveActorName(event),
          actorCard: this.resolveActorCard(event),
          abilityName: this.resolveAbilityName(abilityId),
          diceSummary: this.describeDice(event),
          targetName: this.resolveTargetName(event),
          targetCard: this.resolveTargetCard(event),
          resultSummary: resultParts.join(' | '),
          resultSegments: this.buildResultSegments(resultParts),
        };
      });
  });
  readonly battleOutcomeLabel = computed(() => this.humanizeId(this.result()?.battle.outcome ?? 'pending'));
  readonly battleStatusLabel = computed(() => this.humanizeId(this.result()?.battle.status ?? 'pending'));

  constructor() {
    void this.abilityCatalogService.load();
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
      this.nodeType.set(currentNode?.node_type ?? null);
      this.runId.set(current.data.run.run_id);

      if (currentNode && AUTO_RESOLVE_NODE_TYPES.has(currentNode.node_type)) {
        const dialogue = await this.lookupDialogue(current.data.run.region_id, currentNode);
        if (dialogue) {
          this.dialogue.set(dialogue);
        } else {
          await this.resolveNode();
        }
      }
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load node.');
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
      this.error.set(error instanceof Error ? error.message : 'Unable to resolve node.');
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
      this.error.set(error instanceof Error ? error.message : 'Unable to claim rewards.');
    } finally {
      this.busy.set(false);
    }
  }

  private resolveActorName(event: Record<string, unknown>): string {
    const side = this.stringValue(event['side']);
    if (side === 'player') {
      const unitId = this.stringValue(event['actor_unit_instance_id']);
      return this.sessionService.units().find((unit) => unit.id === unitId)?.name ?? `Unit ${unitId}`;
    }

    return this.humanizeId(this.stringValue(event['actor_enemy_slug']) || 'enemy');
  }

  private resolveTargetName(event: Record<string, unknown>): string {
    const side = this.stringValue(event['side']);
    if (side === 'player') {
      const allyTargetId = this.stringValue(event['target_unit_instance_id']);
      if (allyTargetId.length > 0) {
        return this.sessionService.units().find((unit) => unit.id === allyTargetId)?.name ?? `Unit ${allyTargetId}`;
      }

      return this.humanizeId(this.stringValue(event['target_enemy_slug']) || 'enemy');
    }

    const enemyTargetSlug = this.stringValue(event['target_enemy_slug']);
    if (enemyTargetSlug.length > 0) {
      return this.humanizeId(enemyTargetSlug);
    }

    const unitId = this.stringValue(event['target_unit_instance_id']);
    return this.sessionService.units().find((unit) => unit.id === unitId)?.name ?? `Unit ${unitId}`;
  }

  private resolveAbilityName(abilityId: string): string {
    return this.abilityCatalog().get(abilityId)?.display_name ?? this.humanizeId(abilityId || 'ability');
  }

  async handleDialogueComplete(choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    this.dialogueChoiceHistory.set(choiceHistory);
    this.dialogue.set(null);
    await this.resolveNode();
  }

  private describeDice(event: Record<string, unknown>): string {
    const slotTraces = Array.isArray(event['slot_traces']) ? event['slot_traces'] : [];
    if (!slotTraces.length) {
      return 'No dice';
    }

    return slotTraces
      .map((trace, index) => {
        const slot = trace as Record<string, unknown>;
        const diceId = this.stringValue(slot['dice_instance_id']);
        const sides = this.numberValue(slot['sides']) || 1;
        const emptySlot = !!slot['empty_slot'];
        const die = diceId ? this.sessionService.dice().find((entry) => entry.id === diceId) : null;
        const rarity = die?.rarity ? `${die.rarity} ` : '';
        const kindLabel = emptySlot ? 'empty slot' : `${rarity}d${sides}`.trim();
        const rolls = Array.isArray(slot['rolls'])
          ? slot['rolls']
              .map((entry) => this.numberValue((entry as Record<string, unknown>)['roll']))
              .filter((roll) => roll > 0)
          : [];
        const rollLabel = rolls.length ? rolls.join(' + ') : '0';
        return `S${index + 1}: ${kindLabel} -> ${rollLabel}`;
      })
      .join(' | ');
  }

  private resultParts(event: Record<string, unknown>): string[] {
    const primaryOutcome = this.stringValue(event['ability_outcome']);
    if (primaryOutcome.length > 0) {
      return [primaryOutcome];
    }

    return [this.humanizeId(this.stringValue(event['outcome']) || 'resolved')];
  }

  private buildResultSegments(parts: string[]): BattleLogResultSegmentViewModel[] {
    return parts.flatMap((part, index) => {
      const segments = this.parseConditionSegments(part);
      if (index === parts.length - 1) {
        return segments;
      }

      return [...segments, { text: ' | ', tooltip: null }];
    });
  }

  private parseConditionSegments(value: string): BattleLogResultSegmentViewModel[] {
    const matches = RunNodePageComponent.CONDITION_DEFINITIONS.flatMap((definition) =>
      definition.aliases.flatMap((alias) => {
        const pattern = new RegExp(`\\b${this.escapeRegex(alias)}\\b`, 'gi');
        return Array.from(value.matchAll(pattern)).map((match) => ({
          start: match.index ?? 0,
          end: (match.index ?? 0) + match[0].length,
          text: match[0],
          tooltip: definition.tooltip,
        }));
      }),
    ).sort((left, right) => left.start - right.start || right.end - left.end);

    const filteredMatches = matches.filter((match, index) => {
      const previous = matches[index - 1];
      return !previous || match.start >= previous.end;
    });

    if (!filteredMatches.length) {
      return [{ text: value, tooltip: null }];
    }

    const segments: BattleLogResultSegmentViewModel[] = [];
    let cursor = 0;

    for (const match of filteredMatches) {
      if (match.start > cursor) {
        segments.push({ text: value.slice(cursor, match.start), tooltip: null });
      }

      segments.push({ text: match.text, tooltip: match.tooltip });
      cursor = match.end;
    }

    if (cursor < value.length) {
      segments.push({ text: value.slice(cursor), tooltip: null });
    }

    return segments;
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }

  private stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
  }

  private numberValue(value: unknown): number {
    return typeof value === 'number' ? value : (typeof value === 'string' && value !== '' ? Number(value) : 0);
  }

  private escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  private playerCard(unitId: string, fallbackName: string): BattleLogUnitCardViewModel {
    const unit = this.sessionService.units().find((entry) => entry.id === unitId);
    return {
      unit: unit ?? { id: unitId || fallbackName.toLowerCase().replace(/\s+/g, '-'), name: fallbackName, level: 0 },
      subtitle: unit?.unit_type_name || unit?.unit_type_slug
        ? `${unit.unit_type_name || unit.unit_type_slug} Level ${unit.level}`
        : 'Warband Unit',
      tone: 'default',
      progressBar: null,
      showLockBadge: false,
    };
  }

  private enemyCard(enemySlug: string): BattleLogUnitCardViewModel {
    return {
      unit: {
        id: `enemy-${enemySlug}`,
        name: this.humanizeId(enemySlug || 'enemy'),
        unit_type_slug: enemySlug || 'enemy',
        unit_type_name: 'Enemy',
        level: 0,
      },
      subtitle: 'Enemy Unit',
      tone: 'enemy',
      progressBar: null,
      showLockBadge: false,
    };
  }

  private resolveActorCard(event: Record<string, unknown>): BattleLogUnitCardViewModel {
    const card = this.stringValue(event['side']) === 'enemy'
      ? this.enemyCard(this.stringValue(event['actor_enemy_slug']) || 'enemy')
      : this.playerCard(this.stringValue(event['actor_unit_instance_id']), 'Unknown Unit');

    card.progressBar = this.hpProgressBar(
      this.resolveHpValue(event['actor_hp_after'], card.unit.current_hp),
      this.resolveHpValue(event['actor_max_hp'], card.unit.max_hp),
    );
    return card;
  }

  private resolveTargetCard(event: Record<string, unknown>): BattleLogUnitCardViewModel {
    const side = this.stringValue(event['side']);
    const card = side === 'enemy'
      ? this.playerCard(this.stringValue(event['target_unit_instance_id']), 'Unknown Unit')
      : this.stringValue(event['target_unit_instance_id']).length > 0
        ? this.playerCard(this.stringValue(event['target_unit_instance_id']), 'Unknown Unit')
        : this.enemyCard(this.stringValue(event['target_enemy_slug']) || 'enemy');

    card.progressBar = this.hpProgressBar(
      this.resolveHpValue(event['target_hp_after'], card.unit.current_hp),
      this.resolveHpValue(event['target_max_hp'], card.unit.max_hp),
    );
    return card;
  }

  private hpProgressBar(currentHp: number, maxHp: number): UnitGridObjectProgressBar | null {
    if (maxHp <= 0) {
      return null;
    }

    const clampedCurrentHp = Math.max(0, Math.min(currentHp, maxHp));
    const percent = (clampedCurrentHp / maxHp) * 100;
    return {
      percent,
      title: `HP ${clampedCurrentHp}/${maxHp}`,
      leftLabel: `HP ${clampedCurrentHp}/${maxHp}`,
      tone: percent <= 25 ? 'hp-critical' : 'hp-healthy',
      showLabels: false,
    };
  }

  private resolveHpValue(eventValue: unknown, fallbackValue: unknown): number {
    if (typeof eventValue === 'number') {
      return eventValue;
    }

    return this.numberValue(fallbackValue);
  }

  private async lookupDialogue(
    regionId: string,
    currentNode: { node_type: string; meta?: Record<string, unknown> | null },
  ): Promise<DialogueScript | null> {
    const regionSlug = this.sessionService
      .profileData()
      ?.region_unlocks.find((entry) => entry.region_id === regionId)?.region_slug ?? null;
    const leadUnit = this.resolveLeadUnit();
    const playerPortraitUrl = resolveUnitImageUrl(leadUnit?.unit_type_slug || leadUnit?.unit_type_name || 'bruiser');
    const fallbackTheme = regionSlug ? REGION_THEME_BY_SLUG[regionSlug] : null;
    const context: DialogueTriggerContext = {
      scene: 'run-node',
      nodeType: currentNode.node_type,
      regionId,
      regionSlug,
      encounterTemplateId: this.stringValue(currentNode.meta?.['encounter_template_id']),
      playerName: leadUnit?.name ?? this.sessionService.session().displayName,
      playerPortraitUrl,
      tags: fallbackTheme ? [fallbackTheme] : [],
    };

    try {
      return await this.dialogueService.getDialogue(context);
    } catch {
      return null;
    }
  }

  private resolveLeadUnit(): UnitRecord | null {
    const activeSquad = this.sessionService.activeSquad();
    const leadUnitId = activeSquad?.formation.find((entry) => entry.unit_instance_id)?.unit_instance_id
      ?? activeSquad?.unit_ids.find((unitId) => typeof unitId === 'string' && unitId.length > 0)
      ?? null;

    if (leadUnitId) {
      return this.sessionService.units().find((unit) => unit.id === leadUnitId) ?? null;
    }

    return this.sessionService.units()[0] ?? null;
  }
}


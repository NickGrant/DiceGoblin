import { Component, OnDestroy, computed, effect, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BattlePlaybackActionStep, BattlePlaybackParticipant, BattlePlaybackSnapshot } from '../../core/battle-playback/battle-playback.models';
import { CurrentRunRecord, ResolveNodeData, UnitRecord } from '../../core/models/api.models';
import { DialogueChoiceSelection, DialogueScript, DialogueTriggerContext } from '../../core/dialogue/dialogue.models';
import { resolveRegionTheme } from '../../core/regions/region-catalog';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { BattlePlaybackAdapterService } from '../../core/services/battle-playback/battle-playback-adapter.service';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitGridObjectProgressBar } from '../../shared/ui/unit-grid-object/unit-grid-object.component';
import { resolvePrototypeEnemySpriteUrl, resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';

const AUTO_RESOLVE_NODE_TYPES = new Set(['combat', 'boss', 'loot']);

type BattleViewMode = 'acted' | 'log';

type BattleLogActionViewModel = {
  round: number;
  tick: number;
  side: string;
  actorName: string;
  actorCard: BattleLogUnitCardViewModel;
  actorSpriteUrl: string;
  abilityName: string;
  diceSummary: string;
  targetName: string;
  targetCard: BattleLogUnitCardViewModel;
  targetSpriteUrl: string;
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

type BattlePlaybackParticipantStateViewModel = {
  participantId: string;
  side: 'player' | 'enemy';
  name: string;
  spriteUrl: string;
  currentHp: number;
  maxHp: number;
  hpPercent: number;
  statusLabel: string;
  isActor: boolean;
  isTarget: boolean;
};

type BattleLootDisplayCard = {
  id: string;
  label: string;
  meta: string;
  imageUrl: string;
};

@Component({
  selector: 'app-run-node-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgDialogueStageComponent, PageFrameComponent],
  templateUrl: './run-node-page.component.html',
  styleUrl: './run-node-page.component.scss',
})
export class RunNodePageComponent implements OnDestroy {
  private static readonly BATTLE_TITLE = 'BATTLE!';
  private static readonly LOOT_TITLE = 'A respectable acquisition of wealth';
  private static readonly BATTLE_SUBTITLE = 'Several goblins have volunteered to be an educational example.';
  private static readonly LOOT_SUBTITLE = 'No heroism required, just strong knees and stronger pockets.';
  private static readonly PLAYER_DIALOGUE_PORTRAIT = '/assets/dialogue/portraits/goblin/base_frame_0.png';
  private static readonly PLAYBACK_INTERVAL_MS = 1250;

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);
  private readonly battlePlaybackAdapter = inject(BattlePlaybackAdapterService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly dialogue = signal<DialogueScript | null>(null);
  readonly dialogueChoiceHistory = signal<DialogueChoiceSelection[]>([]);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly nodeType = signal<string | null>(null);
  readonly battleViewMode = signal<BattleViewMode>('acted');
  readonly playbackIndex = signal(0);
  readonly playbackPaused = signal(false);
  readonly playbackSpeed = signal(1);
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
    const rewards = this.battlePlaybackSnapshot()?.rewards;
    if (!rewards || this.resolvedNodeType() !== 'loot') {
      return null;
    }

    return {
      teeth: rewards.currencySoft,
      diceLabels: rewards.newDiceLabels,
      unitLabels: rewards.newUnitLabels,
    };
  });
  readonly battlePlaybackSnapshot = computed(() =>
    this.battlePlaybackAdapter.createSnapshot({
      runId: this.runId(),
      nodeId: this.nodeId,
      result: this.result(),
      playerUnits: this.sessionService.units(),
      diceInventory: this.sessionService.dice(),
      abilityNames: new Map(
        Array.from(this.abilityCatalog().entries()).map(([abilityId, entry]) => [abilityId, entry.display_name ?? this.humanizeId(abilityId)]),
      ),
    }),
  );
  readonly playbackTimeline = computed(() => this.battlePlaybackSnapshot()?.timeline ?? []);
  readonly playbackStep = computed(() => this.playbackTimeline()[this.playbackIndex()] ?? null);
  readonly playbackStepCount = computed(() => this.playbackTimeline().length);
  readonly playbackMaxIndex = computed(() => Math.max(0, this.playbackStepCount() - 1));
  readonly playbackProgressPercent = computed(() => {
    const stepCount = this.playbackStepCount();
    if (stepCount <= 1) {
      return stepCount === 1 ? 100 : 0;
    }

    return Math.round((this.playbackIndex() / (stepCount - 1)) * 100);
  });
  readonly battleResultHeadline = computed(() => this.battleOutcomeLabel().toUpperCase());
  readonly battleResultCopy = computed(() => {
    return this.result()?.battle.outcome === 'victory'
      ? 'The crew held the line. Review the sequence or skip straight to the payout.'
      : 'The squad got broken. Study the sequence, then decide how to regroup.';
  });
  readonly playbackStepLabel = computed(() => {
    const step = this.playbackStep();
    if (!step) {
      return 'No battle ticks recorded.';
    }

    return `Round ${step.round} • Tick ${step.tick}`;
  });
  readonly battleViewTabs = [
    { id: 'acted' as const, label: 'Acted Out' },
    { id: 'log' as const, label: 'Log View' },
  ] as const;
  readonly playbackSpeedOptions = [0.5, 1, 2, 4] as const;
  readonly actionLog = computed<BattleLogActionViewModel[]>(() => {
    return (this.battlePlaybackSnapshot()?.timeline ?? []).map((step) => this.mapActionStep(step));
  });
  readonly playerPlaybackParticipants = computed(() => this.buildParticipantStates('player'));
  readonly enemyPlaybackParticipants = computed(() => this.buildParticipantStates('enemy'));
  readonly lootUnitCards = computed(() => {
    return (this.lootRewards()?.unitLabels ?? []).map((label, index) => ({
      id: `unit-${index}-${label}`,
      label,
      meta: 'Unit Loot',
      imageUrl: resolvePrototypeUnitSpriteUrl(label),
    }));
  });
  readonly lootDiceCards = computed(() => {
    return (this.lootRewards()?.diceLabels ?? []).map((label, index) => ({
      id: `die-${index}-${label}`,
      label,
      meta: this.formatLootDieMeta(label),
      imageUrl: this.lootDieImage(label),
    }));
  });
  readonly battleOutcomeLabel = computed(() => this.humanizeId(this.result()?.battle.outcome ?? 'pending'));
  readonly battleStatusLabel = computed(() => this.humanizeId(this.result()?.battle.status ?? 'pending'));
  private playbackTimer: ReturnType<typeof window.setTimeout> | null = null;
  private lastPlaybackBattleId: string | null = null;

  constructor() {
    void this.abilityCatalogService.load();
    void this.loadRun();

    effect(() => {
      const battleId = this.battlePlaybackSnapshot()?.metadata.battleId ?? null;
      if (!battleId || battleId === this.lastPlaybackBattleId) {
        return;
      }

      this.lastPlaybackBattleId = battleId;
      this.restartPlayback();
    });
  }

  ngOnDestroy(): void {
    this.clearPlaybackTimer();
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
        const dialogue = await this.lookupDialogue(current.data.run, currentNode);
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

  async handleDialogueComplete(choiceHistory: DialogueChoiceSelection[]): Promise<void> {
    this.dialogueChoiceHistory.set(choiceHistory);
    this.dialogue.set(null);
    await this.resolveNode();
  }

  setBattleView(mode: BattleViewMode): void {
    this.battleViewMode.set(mode);
    if (mode === 'acted') {
      this.schedulePlayback();
      return;
    }

    this.clearPlaybackTimer();
  }

  togglePlayback(): void {
    this.playbackPaused.update((paused) => !paused);
    this.schedulePlayback();
  }

  restartPlayback(): void {
    this.playbackIndex.set(0);
    this.playbackPaused.set(false);
    this.schedulePlayback();
  }

  setPlaybackSpeed(speed: number): void {
    this.playbackSpeed.set(speed);
    this.schedulePlayback();
  }

  seekPlayback(indexValue: string | number): void {
    const maxIndex = Math.max(0, this.playbackStepCount() - 1);
    const parsed = typeof indexValue === 'number' ? indexValue : Number(indexValue);
    const nextIndex = Number.isFinite(parsed) ? Math.max(0, Math.min(maxIndex, Math.round(parsed))) : 0;
    this.playbackIndex.set(nextIndex);
    this.schedulePlayback();
  }

  private mapActionStep(step: BattlePlaybackActionStep): BattleLogActionViewModel {
    const actorCard = this.resolveCard(step.actor.unitId, step.actor.enemySlug, step.actor.name, step.actor.currentHp, step.actor.maxHp);
    const targetCard = this.resolveCard(step.target.unitId, step.target.enemySlug, step.target.name, step.target.currentHp, step.target.maxHp);

    return {
      round: step.round,
      tick: step.tick,
      side: step.side,
      actorName: step.actor.name,
      actorCard,
      actorSpriteUrl: this.resolveSpriteUrl(step.actor.unitId ? actorCard.unit : step.actor.enemySlug),
      abilityName: step.abilityName,
      diceSummary: step.diceSummary,
      targetName: step.target.name,
      targetCard,
      targetSpriteUrl: this.resolveSpriteUrl(step.target.unitId ? targetCard.unit : step.target.enemySlug),
      resultSummary: step.resultSummary,
      resultSegments: step.resultSegments,
    };
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

  private resolveCard(
    unitId: string | null,
    enemySlug: string | null,
    fallbackName: string,
    currentHp: number,
    maxHp: number,
  ): BattleLogUnitCardViewModel {
    const card = unitId ? this.playerCard(unitId, fallbackName) : this.enemyCard(enemySlug ?? 'enemy');
    card.progressBar = this.hpProgressBar(
      this.resolveHpValue(currentHp, card.unit.current_hp),
      this.resolveHpValue(maxHp, card.unit.max_hp),
    );
    return card;
  }

  private buildParticipantStates(side: 'player' | 'enemy'): BattlePlaybackParticipantStateViewModel[] {
    const snapshot = this.battlePlaybackSnapshot();
    if (!snapshot) {
      return [];
    }

    const participants = side === 'player' ? snapshot.participants.player : snapshot.participants.enemy;
    const hpByParticipant = new Map<string, number>();

    for (const participant of participants) {
      hpByParticipant.set(participant.participantId, participant.startingHp || participant.maxHp);
    }

    const currentIndex = this.playbackIndex();
    for (let index = 0; index <= currentIndex; index += 1) {
      const step = snapshot.timeline[index];
      if (!step) {
        break;
      }

      if (step.actor.participantId) {
        hpByParticipant.set(step.actor.participantId, step.actor.currentHp);
      }

      if (step.target.participantId) {
        hpByParticipant.set(step.target.participantId, step.target.currentHp);
      }
    }

    const activeStep = snapshot.timeline[currentIndex] ?? null;
    return participants.map((participant) => this.participantStateViewModel(participant, hpByParticipant, activeStep));
  }

  private participantStateViewModel(
    participant: BattlePlaybackParticipant,
    hpByParticipant: Map<string, number>,
    activeStep: BattlePlaybackActionStep | null,
  ): BattlePlaybackParticipantStateViewModel {
    const currentHp = hpByParticipant.get(participant.participantId) ?? participant.startingHp ?? participant.maxHp;
    const maxHp = Math.max(1, participant.maxHp);
    const hpPercent = Math.round((Math.max(0, Math.min(currentHp, maxHp)) / maxHp) * 100);

    return {
      participantId: participant.participantId,
      side: participant.side,
      name: participant.name,
      spriteUrl: participant.side === 'player'
        ? resolvePrototypeUnitSpriteUrl(participant.unitId ?? participant.spriteKey)
        : resolvePrototypeEnemySpriteUrl(participant.enemySlug ?? participant.spriteKey),
      currentHp,
      maxHp,
      hpPercent,
      statusLabel: currentHp <= 0 ? 'Down' : `HP ${currentHp}/${maxHp}`,
      isActor: activeStep?.actor.participantId === participant.participantId,
      isTarget: activeStep?.target.participantId === participant.participantId,
    };
  }

  private schedulePlayback(): void {
    this.clearPlaybackTimer();

    if (this.battleViewMode() !== 'acted' || this.playbackPaused()) {
      return;
    }

    const stepCount = this.playbackStepCount();
    if (stepCount <= 1) {
      return;
    }

    if (this.playbackIndex() >= stepCount - 1) {
      this.playbackPaused.set(true);
      return;
    }

    if (typeof window === 'undefined') {
      return;
    }

    this.playbackTimer = window.setTimeout(() => {
      const maxIndex = Math.max(0, this.playbackStepCount() - 1);
      this.playbackIndex.update((index) => Math.min(index + 1, maxIndex));
      this.schedulePlayback();
    }, RunNodePageComponent.PLAYBACK_INTERVAL_MS / this.playbackSpeed());
  }

  private clearPlaybackTimer(): void {
    if (this.playbackTimer !== null) {
      clearTimeout(this.playbackTimer);
      this.playbackTimer = null;
    }
  }

  private resolveSpriteUrl(source: UnitRecord | string | null | undefined): string {
    if (typeof source === 'string') {
      return resolvePrototypeEnemySpriteUrl(source);
    }

    return resolvePrototypeUnitSpriteUrl(source);
  }

  private lootDieImage(label: string): string {
    const match = label.trim().match(/^([a-z]+)\s+d(\d+)$/i);
    if (!match) {
      return resolveDiceArtStyles('common', 6, 96).imageUrl;
    }

    return resolveDiceArtStyles(match[1], Number(match[2]), 96).imageUrl;
  }

  private formatLootDieMeta(label: string): string {
    const match = label.trim().match(/^([a-z]+)\s+d(\d+)$/i);
    if (!match) {
      return 'Dice Loot';
    }

    return `${match[1].charAt(0).toUpperCase() + match[1].slice(1).toLowerCase()} d${match[2]}`;
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
    run: CurrentRunRecord,
    currentNode: { node_type: string; meta?: Record<string, unknown> | null },
  ): Promise<DialogueScript | null> {
    const regionSlug = run.region_slug ?? null;
    const fallbackTheme = resolveRegionTheme(regionSlug, run.region_theme ?? null);
    const dialogueTags = [
      ...(fallbackTheme ? [fallbackTheme] : []),
      ...(this.sessionService.profileData()?.feature_unlocks?.includes('shop') ? ['shop-unlocked'] : []),
    ];
    const context: DialogueTriggerContext = {
      scene: 'run-node',
      nodeType: currentNode.node_type,
      regionId: run.region_id,
      regionSlug,
      encounterTemplateId: this.stringValue(currentNode.meta?.['encounter_template_id']),
      playerName: this.sessionService.session().displayName ?? this.resolveLeadUnit()?.name,
      playerPortraitUrl: RunNodePageComponent.PLAYER_DIALOGUE_PORTRAIT,
      tags: dialogueTags,
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


import { Component, OnDestroy, computed, effect, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { BattlePlaybackActionStep, BattlePlaybackParticipant, BattlePlaybackSnapshot } from '../../core/battle-playback/battle-playback.models';
import { ResolveNodeData, UnitRecord } from '../../core/models/api.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { BattlePlaybackAdapterService } from '../../core/services/battle-playback/battle-playback-adapter.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitGridObjectProgressBar } from '../../shared/ui/unit-grid-object/unit-grid-object.component';
import { resolvePrototypeEnemySpriteUrl, resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';
import { resolveUnitAnimationFrameUrls } from '../../shared/ui/unit-art/unit-art';

const AUTO_RESOLVE_NODE_TYPES = new Set(['combat', 'boss']);

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

type BattleLogResultSegmentViewModel = {
  text: string;
  tooltip: string | null;
};

type BattlePlaybackParticipantStateViewModel = {
  participantId: string;
  side: 'player' | 'enemy';
  name: string;
  spriteUrl: string;
  spriteFrameUrls: string[];
  currentHp: number;
  maxHp: number;
  hpPercent: number;
  statusLabel: string;
  isActor: boolean;
  isTarget: boolean;
};

@Component({
  selector: 'app-run-node-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent],
  templateUrl: './run-node-page.component.html',
  styleUrl: './run-node-page.component.scss',
})
export class RunNodePageComponent implements OnDestroy {
  private static readonly BATTLE_TITLE = 'BATTLE!';
  private static readonly BATTLE_SUBTITLE = 'Several goblins have volunteered to be an educational example.';
  private static readonly PLAYBACK_INTERVAL_MS = 1250;
  private static readonly SPRITE_ANIMATION_INTERVAL_MS = 240;

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);
  private readonly battlePlaybackAdapter = inject(BattlePlaybackAdapterService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly nodeType = signal<string | null>(null);
  readonly battleViewMode = signal<BattleViewMode>('acted');
  readonly playbackIndex = signal(0);
  readonly playbackPaused = signal(false);
  readonly playbackSpeed = signal(1);
  readonly combatAnimationFrameIndex = signal(0);
  readonly shouldAutoResolve = computed(() => AUTO_RESOLVE_NODE_TYPES.has(this.nodeType() ?? ''));
  readonly abilityCatalogError = this.abilityCatalogService.error;
  readonly abilityCatalog = this.abilityCatalogService.abilityMap;
  readonly resolvedNodeType = computed(() => {
    const metaNodeType = this.result()?.battle.log?.meta?.['node_type'];
    return typeof metaNodeType === 'string' ? metaNodeType : (this.nodeType() ?? 'combat');
  });
  readonly pageTitle = computed(() => {
    if (this.shouldAutoResolve()) {
      return RunNodePageComponent.BATTLE_TITLE;
    }

    return `Node ${this.nodeId}`;
  });
  readonly pageSubtitle = computed(() => {
    if (!this.result()) {
      return '';
    }

    return RunNodePageComponent.BATTLE_SUBTITLE;
  });
  readonly claimButtonLabel = computed(() => {
    if (this.busy()) {
      return 'Working...';
    }

    return 'Claim Rewards';
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
  readonly battleOutcomeLabel = computed(() => this.humanizeId(this.result()?.battle.outcome ?? 'pending'));
  readonly battleStatusLabel = computed(() => this.humanizeId(this.result()?.battle.status ?? 'pending'));
  private playbackTimer: ReturnType<typeof window.setTimeout> | null = null;
  private combatAnimationTimer: ReturnType<typeof window.setInterval> | null = null;
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
      this.restartCombatAnimation();
    });
  }

  ngOnDestroy(): void {
    this.clearPlaybackTimer();
    this.clearCombatAnimationTimer();
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

      if (currentNode?.node_type === 'loot') {
        await this.router.navigate(['/run/loot', this.nodeId]);
        return;
      }

      if (currentNode && AUTO_RESOLVE_NODE_TYPES.has(currentNode.node_type)) {
        await this.resolveNode();
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

  setBattleView(mode: BattleViewMode): void {
    this.battleViewMode.set(mode);
    if (mode === 'acted') {
      this.schedulePlayback();
      this.restartCombatAnimation();
      return;
    }

    this.clearPlaybackTimer();
    this.clearCombatAnimationTimer();
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

  combatSpriteUrl(participant: BattlePlaybackParticipantStateViewModel): string {
    const frames = participant.spriteFrameUrls.length ? participant.spriteFrameUrls : [participant.spriteUrl];
    if (!participant.isActor) {
      return frames[0] ?? participant.spriteUrl;
    }

    return frames[this.combatAnimationFrameIndex() % frames.length] ?? participant.spriteUrl;
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
      spriteFrameUrls: this.resolveParticipantFrameUrls(participant),
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

  private restartCombatAnimation(): void {
    this.clearCombatAnimationTimer();
    this.combatAnimationFrameIndex.set(0);

    if (typeof window === 'undefined' || this.battleViewMode() !== 'acted') {
      return;
    }

    this.combatAnimationTimer = window.setInterval(() => {
      this.combatAnimationFrameIndex.update((index) => (index + 1) % 4);
    }, RunNodePageComponent.SPRITE_ANIMATION_INTERVAL_MS);
  }

  private clearCombatAnimationTimer(): void {
    if (this.combatAnimationTimer !== null) {
      clearInterval(this.combatAnimationTimer);
      this.combatAnimationTimer = null;
    }
  }

  private resolveParticipantFrameUrls(participant: BattlePlaybackParticipant): string[] {
    if (participant.side === 'player') {
      const unit = this.sessionService.units().find((entry) => entry.id === participant.unitId) ?? null;
      return resolveUnitAnimationFrameUrls(unit?.unit_type_slug ?? unit?.unit_type_name ?? participant.spriteKey ?? participant.name);
    }

    return resolveUnitAnimationFrameUrls(participant.enemySlug ?? participant.spriteKey ?? participant.name);
  }

  private resolveSpriteUrl(source: UnitRecord | string | null | undefined): string {
    if (typeof source === 'string') {
      return resolvePrototypeEnemySpriteUrl(source);
    }

    return resolvePrototypeUnitSpriteUrl(source);
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

}


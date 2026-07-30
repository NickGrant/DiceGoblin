import { TitleCasePipe } from '@angular/common';
import { Component, OnDestroy, computed, effect, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { BattlePlaybackActionStep, BattlePlaybackParticipant, BattlePlaybackSnapshot } from '../../core/battle-playback/battle-playback.models';
import { ChaosEncounterData, ChaosFinalizeData, CurrentRunNode, ResolveNodeData, UnitRecord } from '../../core/models/api.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { BattlePlaybackAdapterService } from '../../core/services/battle-playback/battle-playback-adapter.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { resolveRegionBackgroundUrl } from '../../core/regions/region-catalog';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitGridObjectProgressBar } from '../../shared/ui/unit-grid-object/unit-grid-object.component';
import { resolvePrototypeEnemySpriteUrl, resolvePrototypeUnitSpriteUrl } from '../../shared/ui/prototype-art/prototype-art';
import { resolveUnitAnimationFrameUrls } from '../../shared/ui/unit-art/unit-art';
import { resolveNodeArtUrl } from '../../shared/ui/node-art/node-art';

const AUTO_RESOLVE_NODE_TYPES = new Set(['combat', 'boss', 'shrine', 'hazard']);

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

type ShrineOfferViewModel = {
  title: string;
  resultCopy: string;
  costCopy: string;
  declineable: boolean;
};

type ShrineEffectSummaryViewModel = {
  label: string;
  detail: string;
};

type BattleRunEffectViewModel = {
  id: string;
  source: string;
  label: string;
  detail: string;
};

@Component({
  selector: 'app-run-node-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, RouterLink, TitleCasePipe],
  templateUrl: './run-node-page.component.html',
  styleUrl: './run-node-page.component.scss',
})
export class RunNodePageComponent implements OnDestroy {
  private static readonly BATTLE_TITLE = 'BATTLE!';
  private static readonly BATTLE_SUBTITLE = 'Several goblins have volunteered to be an educational example.';
  private static readonly PLAYBACK_INTERVAL_MS = 1250;
  private static readonly ACTION_TRANSITION_GAP_MS = 260;
  private static readonly SPRITE_ANIMATION_INTERVAL_MS = 240;

  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);
  private readonly battlePlaybackAdapter = inject(BattlePlaybackAdapterService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly runRegionSlug = signal<string | null>(null);
  readonly runRegionTheme = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly chaosResult = signal<ChaosEncounterData['chaos_result'] | null>(null);
  readonly chaosCompletion = signal<ChaosFinalizeData['completion'] | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly nodeType = signal<string | null>(null);
  readonly currentNode = signal<CurrentRunNode | null>(null);
  readonly battleViewMode = signal<BattleViewMode>('acted');
  readonly playbackIndex = signal(0);
  readonly playbackPaused = signal(false);
  readonly playbackSpeed = signal(1);
  readonly actionTransitioning = signal(false);
  readonly combatAnimationFrameIndex = signal(0);
  readonly shouldAutoResolve = computed(() => AUTO_RESOLVE_NODE_TYPES.has(this.nodeType() ?? ''));
  readonly isChaosNode = computed(() => this.nodeType() === 'chaos');
  readonly canManuallyResolve = computed(() => {
    const nodeType = this.nodeType();
    return !!nodeType
      && !this.shouldAutoResolve()
      && !this.isChaosNode()
      && !['dialogue', 'exit', 'loot'].includes(nodeType);
  });
  readonly manualResolveButtonLabel = computed(() => {
    if (this.busy()) {
      return 'Resolving...';
    }

    switch (this.nodeType()) {
      case 'shrine':
        return 'Resolve Shrine';
      case 'hazard':
        return 'Resolve Hazard';
      case 'rest':
        return 'Rest Here';
      default:
        return 'Resolve Encounter';
    }
  });
  readonly chaosRewards = computed(() => this.chaosResult()?.finalized_rewards ?? null);
  readonly chaosIsFinalized = computed(() => this.chaosResult()?.status === 'confirmed');
  readonly abilityCatalogError = this.abilityCatalogService.error;
  readonly abilityCatalog = this.abilityCatalogService.abilityMap;
  readonly resolvedNodeType = computed(() => {
    const metaNodeType = this.result()?.battle.log?.meta?.['node_type'];
    return typeof metaNodeType === 'string' ? metaNodeType : (this.nodeType() ?? 'combat');
  });
  readonly pageTitle = computed(() => {
    if (this.isChaosNode()) {
      return 'Chaos Encounter';
    }

    if (this.shouldAutoResolve()) {
      return RunNodePageComponent.BATTLE_TITLE;
    }

    return `${this.humanizeId(this.nodeType() ?? 'encounter')} Encounter`;
  });
  readonly pageSubtitle = computed(() => {
    if (this.isChaosNode()) {
      if (this.result()) {
        return RunNodePageComponent.BATTLE_SUBTITLE;
      }
      if (this.chaosIsFinalized()) {
        return 'The reels are locked. Watch the fight play out, then claim the result.';
      }
      if (this.chaosResult()) {
        return 'The machine has settled. Reroll one reel or carry the risk forward.';
      }
      return '';
    }

    if (!this.result()) {
      return '';
    }

    if (!this.isCombatLikeNodeType(this.resolvedNodeType())) {
      switch (this.resolvedNodeType()) {
        case 'shrine':
          return 'The shrine answered. Claim favor to return to the route.';
        case 'hazard':
          return 'The hazard is handled. Continue when the path is clear.';
        default:
          return 'Review the result, then continue the route.';
      }
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
      regionTheme: this.runRegionTheme(),
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
  readonly battleSceneBackgroundImage = computed(() =>
    `url('${resolveRegionBackgroundUrl(this.runRegionSlug(), this.runRegionTheme()) ?? '/assets/ui/biome/mystic_cave.png'}')`,
  );
  readonly battleResultCopy = computed(() => {
    if (!this.isCombatLikeNodeType(this.resolvedNodeType())) {
      switch (this.resolvedNodeType()) {
        case 'shrine':
          return this.nodeResultEventDetail() || 'A favor is ready. Claim it, then choose the next path.';
        case 'hazard':
          return this.nodeResultEventDetail() || 'The route is passable again. Continue from the map.';
        default:
          return 'The path opened without a fight. Claim the result and keep moving.';
      }
    }

    return this.result()?.battle.outcome === 'victory'
      ? 'The crew held the line. Review the sequence or skip straight to the payout.'
      : 'The squad got broken. Study the sequence, then decide how to regroup.';
  });
  readonly nodeResultActionTitle = computed(() => {
    switch (this.resolvedNodeType()) {
      case 'shrine':
        return this.isDeclineableShrineOffer() ? 'Shrine Bargain' : 'Claim Favor';
      case 'hazard':
        return 'Continue Path';
      default:
        return 'Claim Rewards';
    }
  });
  readonly nodeResultEyebrow = computed(() => {
    switch (this.resolvedNodeType()) {
      case 'shrine':
        return 'Favor Ready';
      case 'hazard':
        return 'Path Cleared';
      default:
        return `${this.humanizeId(this.resolvedNodeType())} Result`;
    }
  });
  readonly nodeResultDetailCopy = computed(() => {
    switch (this.resolvedNodeType()) {
      case 'shrine':
        if (this.isDeclineableShrineOffer()) {
          return 'This shrine asks for something in return. Accept the bargain to apply both sides, or decline and leave it untouched.';
        }
        return 'This favor is now visible in the result before you return to the route.';
      case 'hazard':
        return 'This hazard result is now visible before you return to the route.';
      default:
        return 'Claim the result and keep moving.';
    }
  });
  readonly nodeResultClaimLabel = computed(() => {
    if (this.busy()) {
      return 'Working...';
    }

    if (this.isDeclineableShrineOffer()) {
      return 'Accept Bargain';
    }

    return this.nodeResultActionTitle();
  });
  readonly shrineOffer = computed<ShrineOfferViewModel | null>(() => {
    const result = this.shrineResultPayload();
    if (!result) {
      return null;
    }

    const title = typeof result['title'] === 'string' && result['title'].trim()
      ? result['title'].trim()
      : this.nodeResultEventLabel();
    const resultCopy = typeof result['result_copy'] === 'string' && result['result_copy'].trim()
      ? result['result_copy'].trim()
      : this.nodeResultEventDetail();
    const cost = result['cost'];
    const costRecord = cost && typeof cost === 'object' && !Array.isArray(cost)
      ? cost as Record<string, unknown>
      : {};
    const declineable = Boolean(result['declineable']) || Boolean(costRecord['declineable']);

    return {
      title,
      resultCopy,
      costCopy: this.describeShrineCost(result, costRecord),
      declineable,
    };
  });
  readonly isDeclineableShrineOffer = computed(() => Boolean(this.shrineOffer()?.declineable));
  readonly shrineEffectSummary = computed<ShrineEffectSummaryViewModel | null>(() => {
    if (this.resolvedNodeType() !== 'shrine') {
      return null;
    }

    const result = this.shrineResultPayload();
    const effect = result?.['effect'];
    const effectRecord = effect && typeof effect === 'object' && !Array.isArray(effect)
      ? effect as Record<string, unknown>
      : {};
    const type = typeof effectRecord['type'] === 'string' ? effectRecord['type'] : '';

    switch (type) {
      case 'grant_teeth':
        return {
          label: 'Teeth',
          detail: this.nodeResultRewardLabel(),
        };
      case 'heal_random_unit':
        return {
          label: 'Healing',
          detail: `Heals one wounded unit for ${this.percentLabel(effectRecord['amount_pct'], 35)} of max life.`,
        };
      case 'drain_highest_life_heal_rest':
        return {
          label: 'Bargain',
          detail: `Fully heals the squad after the healthiest unit pays ${this.percentLabel(effectRecord['drain_pct'], 50)} life.`,
        };
      case 'squad_damage_next_combat':
        return {
          label: 'Next Combat',
          detail: `${this.multiplierBonusLabel(effectRecord['damage_multiplier'], 1.10)} damage for the squad.`,
        };
      case 'run_stat_modifier_next_combat':
      case 'stat_modifier_next_combat':
      case 'squad_stat_modifier_next_combat':
        return {
          label: 'Next Combat',
          detail: this.describeShrineStatModifier(effectRecord),
        };
      case 'double_run_teeth':
        return {
          label: 'Teeth',
          detail: 'Doubles teeth already claimed during this run.',
        };
      case 'upgrade_run_unit_tier':
        return {
          label: 'Recruit Upgrade',
          detail: 'Upgrades one unit gained earlier in this run to a higher tier.',
        };
      case 'clear_random_combat_node':
        return {
          label: 'Route',
          detail: 'Clears one available combat node and opens any newly reachable paths.',
        };
      default:
        return null;
    }
  });
  readonly nodeResultArtUrl = computed(() => {
    if (this.resolvedNodeType() === 'shrine') {
      return resolveNodeArtUrl(this.currentNode(), 'shrine');
    }

    return resolveRegionBackgroundUrl(this.runRegionSlug(), this.runRegionTheme()) ?? '/assets/ui/biome/mystic_cave.png';
  });
  readonly nodeResultEventLabel = computed(() => {
    const event = this.result()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const label = typeof event?.['label'] === 'string' ? event['label'] : '';
    if (label) {
      return label;
    }
    const message = typeof event?.['message'] === 'string' ? event['message'] : '';
    return message ? this.humanizeId(message) : this.humanizeId(this.resolvedNodeType());
  });
  readonly nodeResultEventDetail = computed(() => {
    const event = this.result()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const detail = typeof event?.['detail'] === 'string' ? event['detail'] : '';
    if (detail) {
      return detail;
    }

    const shrineResult = event?.['shrine_result'];
    if (shrineResult && typeof shrineResult === 'object' && !Array.isArray(shrineResult)) {
      const favor = typeof (shrineResult as Record<string, unknown>)['favor'] === 'string'
        ? (shrineResult as Record<string, unknown>)['favor'] as string
        : '';
      if (favor) {
        return `${this.humanizeId(favor)} settled over the warband.`;
      }
    }

    return '';
  });
  readonly nodeResultRewardLabel = computed(() => {
    const preview = this.result()?.battle.reward_preview;
    if (!preview) {
      return 'No reward preview available.';
    }

    const labels: string[] = [];
    if ((preview.currency_soft ?? 0) > 0) {
      labels.push(`${preview.currency_soft} teeth`);
    }
    const diceCount = preview.dice?.length ?? preview.new_dice_labels?.length ?? 0;
    if (diceCount > 0) {
      labels.push(`${diceCount} dice`);
    }
    const unitCount = preview.units?.length ?? preview.new_unit_labels?.length ?? 0;
    if (unitCount > 0) {
      labels.push(`${unitCount} units`);
    }

    return labels.length ? labels.join(', ') : 'No material reward.';
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
  readonly battleRunEffects = computed<BattleRunEffectViewModel[]>(() => this.buildBattleRunEffects());
  private playbackTimer: ReturnType<typeof window.setTimeout> | null = null;
  private actionTransitionTimer: ReturnType<typeof window.setTimeout> | null = null;
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
    this.clearActionTransitionTimer();
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
      this.currentNode.set(currentNode);
      this.nodeType.set(currentNode?.node_type ?? null);
      this.runId.set(current.data.run.run_id);
      this.runRegionSlug.set(current.data.run.region_slug ?? null);
      this.runRegionTheme.set(current.data.run.region_theme ?? null);

      if (currentNode?.node_type === 'loot') {
        await this.router.navigate(['/run/loot', this.nodeId]);
        return;
      }

      if (currentNode?.node_type === 'chaos') {
        await this.generateChaosResult();
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

  async generateChaosResult(): Promise<void> {
    if (!this.runId()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.generateChaosEncounter(this.runId()!, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.chaosResult.set(response.data.chaos_result);
      if (response.data.chaos_result.status === 'confirmed') {
        this.chaosCompletion.set({
          title: 'Chaos Settled',
          message: `${response.data.chaos_result.summary.title} is locked in. The fight is ready.`,
        });
        await this.resolveNode();
      }
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to generate chaos encounter.');
    } finally {
      this.busy.set(false);
    }
  }

  async rerollChaosReel(reelIndex: number): Promise<void> {
    if (!this.runId() || this.chaosIsFinalized() || !this.chaosResult()?.manipulation.available) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.rerollChaosEncounter(this.runId()!, this.nodeId, reelIndex);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.chaosResult.set(response.data.chaos_result);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to reroll chaos reel.');
    } finally {
      this.busy.set(false);
    }
  }

  async finalizeChaosEncounter(): Promise<void> {
    if (!this.runId() || !this.chaosResult()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.finalizeChaosEncounter(this.runId()!, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.chaosResult.set(response.data.chaos_result);
      this.chaosCompletion.set(response.data.completion);
      await this.resolveNode();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to finalize chaos encounter.');
    } finally {
      this.busy.set(false);
    }
  }

  async claimRewards(action: 'accept' | 'decline' = 'accept'): Promise<void> {
    const battleId = this.result()?.battle.battle_id;
    if (!battleId) {
      await this.router.navigateByUrl('/run/map');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.claimBattleRewards(battleId, action);
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

  async acceptShrineOffer(): Promise<void> {
    await this.claimRewards('accept');
  }

  async declineShrineOffer(): Promise<void> {
    await this.claimRewards('decline');
  }

  setBattleView(mode: BattleViewMode): void {
    this.battleViewMode.set(mode);
    if (mode === 'acted') {
      this.schedulePlayback();
      this.restartCombatAnimation();
      return;
    }

    this.clearPlaybackTimer();
    this.clearActionTransitionTimer();
    this.clearCombatAnimationTimer();
    this.actionTransitioning.set(false);
  }

  togglePlayback(): void {
    this.playbackPaused.update((paused) => !paused);
    if (this.playbackPaused()) {
      this.clearCombatAnimationTimer();
    } else {
      this.restartCombatAnimation();
    }
    this.schedulePlayback();
  }

  restartPlayback(): void {
    this.clearActionTransitionTimer();
    this.actionTransitioning.set(false);
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
    this.clearActionTransitionTimer();
    this.actionTransitioning.set(false);
    this.playbackIndex.set(nextIndex);
    this.restartCombatAnimation();
    this.schedulePlayback();
  }

  combatSpriteUrl(participant: BattlePlaybackParticipantStateViewModel): string {
    const frames = participant.spriteFrameUrls.length ? participant.spriteFrameUrls : [participant.spriteUrl];
    if (!participant.isActor) {
      return frames[0] ?? participant.spriteUrl;
    }

    return frames[this.combatAnimationFrameIndex() % frames.length] ?? participant.spriteUrl;
  }

  private shrineResultPayload(): Record<string, unknown> | null {
    const previewResult = this.result()?.battle.reward_preview?.encounter_result;
    if (previewResult && typeof previewResult === 'object' && !Array.isArray(previewResult)) {
      const nested = previewResult['result'];
      if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
        return nested as Record<string, unknown>;
      }
    }

    const event = this.result()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const shrineResult = event?.['shrine_result'];
    return shrineResult && typeof shrineResult === 'object' && !Array.isArray(shrineResult)
      ? shrineResult as Record<string, unknown>
      : null;
  }

  private describeShrineCost(result: Record<string, unknown>, cost: Record<string, unknown>): string {
    const effect = result['effect'];
    const effectRecord = effect && typeof effect === 'object' && !Array.isArray(effect)
      ? effect as Record<string, unknown>
      : {};
    const effectType = typeof effectRecord['type'] === 'string' ? effectRecord['type'] : '';

    if (effectType === 'drain_highest_life_heal_rest') {
      const drainPct = Number(effectRecord['drain_pct'] ?? 0);
      return drainPct > 0
        ? `Cost: the healthiest unit loses ${drainPct}% life.`
        : 'Cost: the healthiest unit pays life.';
    }

    if (typeof cost['copy'] === 'string' && cost['copy'].trim()) {
      return cost['copy'].trim();
    }

    return 'Cost: this shrine has a negative side effect.';
  }

  private describeShrineStatModifier(effect: Record<string, unknown>): string {
    const parts: string[] = [];
    const multipliers = this.recordValue(effect['stat_multipliers']);
    const adders = this.recordValue(effect['stat_adders']);
    for (const [stat, raw] of Object.entries(multipliers)) {
      const multiplier = Number(raw);
      if (Number.isFinite(multiplier) && multiplier > 0 && Math.abs(multiplier - 1) > 0.0001) {
        parts.push(`${this.multiplierBonusLabel(multiplier, 1)} ${this.humanizeId(stat)}`);
      }
    }
    for (const [stat, raw] of Object.entries(adders)) {
      const amount = Number(raw);
      if (Number.isFinite(amount) && amount !== 0) {
        parts.push(`${amount > 0 ? '+' : ''}${amount} ${this.humanizeId(stat)}`);
      }
    }

    return parts.length ? `${parts.join(', ')} for the squad.` : 'Improves the squad for the next combat.';
  }

  private recordValue(value: unknown): Record<string, unknown> {
    return value && typeof value === 'object' && !Array.isArray(value)
      ? value as Record<string, unknown>
      : {};
  }

  private percentLabel(value: unknown, fallback: number): string {
    const numeric = Number(value ?? fallback);
    return `${Number.isFinite(numeric) ? numeric : fallback}%`;
  }

  private multiplierBonusLabel(value: unknown, fallback: number): string {
    const multiplier = Number(value ?? fallback);
    const safeMultiplier = Number.isFinite(multiplier) ? multiplier : fallback;
    const bonus = Math.round((safeMultiplier - 1) * 100);
    return bonus >= 0 ? `+${bonus}%` : `${bonus}%`;
  }

  private buildBattleRunEffects(): BattleRunEffectViewModel[] {
    const participants = this.recordValue(this.result()?.battle.log?.meta?.['participants']);
    const playerRows = Array.isArray(participants['player']) ? participants['player'] : [];
    const summaries = new Map<string, { source: string; type: string; detail: string; unitIds: Set<string> }>();

    for (const row of playerRows) {
      if (!row || typeof row !== 'object' || Array.isArray(row)) {
        continue;
      }
      const record = row as Record<string, unknown>;
      const unitId = String(record['unit_instance_id'] ?? record['participant_id'] ?? '');
      const modifiers = Array.isArray(record['run_combat_modifiers']) ? record['run_combat_modifiers'] : [];
      for (const modifier of modifiers) {
        if (!modifier || typeof modifier !== 'object' || Array.isArray(modifier)) {
          continue;
        }
        const effect = modifier as Record<string, unknown>;
        const type = typeof effect['type'] === 'string' ? effect['type'] : 'run_modifier';
        const source = typeof effect['source'] === 'string' && effect['source'].trim() ? effect['source'].trim() : 'run';
        const detail = this.describeBattleRunEffect(effect);
        const key = JSON.stringify({
          type,
          source,
          detail,
        });
        if (!summaries.has(key)) {
          summaries.set(key, { source, type, detail, unitIds: new Set<string>() });
        }
        if (unitId) {
          summaries.get(key)?.unitIds.add(unitId);
        }
      }
    }

    return Array.from(summaries.entries()).map(([key, summary]) => {
      const unitCount = summary.unitIds.size;
      const unitCopy = unitCount === 1 ? '1 unit' : `${unitCount} units`;
      return {
        id: key,
        source: this.humanizeId(summary.source),
        label: `${this.humanizeId(summary.source)} Battle Effect`,
        detail: `${summary.detail} affecting ${unitCopy}.`,
      };
    });
  }

  private describeBattleRunEffect(effect: Record<string, unknown>): string {
    const parts: string[] = [];
    const multipliers = this.recordValue(effect['stat_multipliers']);
    const adders = this.recordValue(effect['stat_adders']);
    for (const [stat, raw] of Object.entries(multipliers)) {
      const multiplier = Number(raw);
      if (Number.isFinite(multiplier) && Math.abs(multiplier - 1) > 0.0001) {
        parts.push(`${this.multiplierBonusLabel(multiplier, 1)} ${this.humanizeId(stat)}`);
      }
    }
    for (const [stat, raw] of Object.entries(adders)) {
      const amount = Number(raw);
      if (Number.isFinite(amount) && amount !== 0) {
        parts.push(`${amount > 0 ? '+' : ''}${amount} ${this.humanizeId(stat)}`);
      }
    }

    return parts.length ? parts.join(', ') : 'Combat modifier';
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

  isCombatLikeNodeType(nodeType: string | null | undefined): boolean {
    return nodeType === 'combat' || nodeType === 'boss' || nodeType === 'chaos';
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
    this.clearActionTransitionTimer();
    this.actionTransitioning.set(false);

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
      this.actionTransitioning.set(true);
      this.actionTransitionTimer = window.setTimeout(() => {
        const maxIndex = Math.max(0, this.playbackStepCount() - 1);
        this.playbackIndex.update((index) => Math.min(index + 1, maxIndex));
        this.actionTransitioning.set(false);
        this.restartCombatAnimation();
        this.schedulePlayback();
      }, RunNodePageComponent.ACTION_TRANSITION_GAP_MS / this.playbackSpeed());
    }, RunNodePageComponent.PLAYBACK_INTERVAL_MS / this.playbackSpeed());
  }

  private clearPlaybackTimer(): void {
    if (this.playbackTimer !== null) {
      clearTimeout(this.playbackTimer);
      this.playbackTimer = null;
    }
  }

  private clearActionTransitionTimer(): void {
    if (this.actionTransitionTimer !== null) {
      clearTimeout(this.actionTransitionTimer);
      this.actionTransitionTimer = null;
    }
  }

  private restartCombatAnimation(): void {
    this.clearCombatAnimationTimer();
    this.combatAnimationFrameIndex.set(0);

    if (typeof window === 'undefined' || this.battleViewMode() !== 'acted' || this.playbackPaused()) {
      return;
    }

    this.combatAnimationTimer = window.setInterval(() => {
      this.combatAnimationFrameIndex.update((index) => {
        const nextIndex = Math.min(index + 1, 3);
        if (nextIndex >= 3) {
          this.clearCombatAnimationTimer();
        }

        return nextIndex;
      });
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


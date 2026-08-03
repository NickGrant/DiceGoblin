import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { isDevPanelEnabled } from '../../core/config/runtime-config';
import { DialogueScript, DialogueTriggerContext } from '../../core/dialogue/dialogue.models';
import {
  CurrentRunData,
  CurrentRunEdge,
  CurrentRunNode,
  ItemRecord,
  ResolveNodeData,
  RestOpenData,
  RewardPreviewDice,
  RewardPreviewUnit,
} from '../../core/models/api.models';
import { resolveRunRegionBackgroundUrl } from '../../core/regions/region-catalog';
import { DialogueService } from '../../core/services/dialogue/dialogue.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { buildFormationGrid } from '../../shared/formation/formation';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgDialogueStageComponent } from '../../shared/ui/dg-dialogue-stage/dg-dialogue-stage.component';
import { resolveNodeArtUrl } from '../../shared/ui/node-art/node-art';
import { formatUnitKinLabel } from '../../shared/utils/unit-formatters';

type RunEncounterModalKind = 'loot' | 'rest' | 'dialogue' | 'shrine' | 'hazard';

type RunModalLootItem = {
  id: string;
  icon: string;
  tone: 'teeth' | 'dice' | 'unit' | 'item';
  label: string;
  detail: string | null;
};

type RunModalUnitStatus = {
  id: string;
  name: string;
  currentHp: number;
  maxHp: number;
  hpPercent: number;
  recoveryLabel: string;
  recoveryTone: 'healed' | 'full';
};

@Component({
  selector: 'app-run-map-page',
  standalone: true,
  imports: [
    DgAlertComponent,
    DgDialogueStageComponent,
  ],
  templateUrl: './run-map-page.component.html',
  styleUrl: './run-map-page.component.scss',
  host: {
    '[attr.data-page]': "'run-map'",
  },
})
export class RunMapPageComponent {
  private static readonly PLAYER_DIALOGUE_PORTRAIT =
    '/assets/ui/units/animated/goblin/base/frame_0.png';
  private static readonly MAP_MIN_WIDTH = 920;
  private static readonly MAP_MIN_HEIGHT = 320;
  private static readonly MAP_NODE_RADIUS = 34;
  private static readonly MAP_HORIZONTAL_PADDING = 120;
  private static readonly MAP_VERTICAL_PADDING = 90;
  private static readonly MAP_NODE_HORIZONTAL_GAP = 140;
  private static readonly MAP_ROW_VERTICAL_GAP = 132;
  private static readonly MAP_LONG_EDGE_VERTICAL_SPREAD = 18;
  private static readonly MAP_LONG_EDGE_VERTICAL_SPREAD_MAX = 54;
  private static readonly MAP_EDGE_FAN_OFFSET = 18;
  private static readonly MAP_EDGE_CURVE_LEAD_MIN = 42;
  private static readonly MAP_EDGE_CURVE_LEAD_MAX = 84;

  private static readonly ENCOUNTER_ICON_MAP: Record<string, string> = {
    combat: '/assets/ui/icons/icon_encounter_combat.png',
    loot: '/assets/ui/icons/icon_encounter_loot.png',
    rest: '/assets/ui/icons/icon_encounter_rest.png',
    boss: '/assets/ui/icons/icon_encounter_boss.png',
    exit: '/assets/ui/icons/icon_home.png',
    dialogue: '/assets/ui/icons/icon_guide.png',
    shrine: '/assets/ui/node-art/shrines/good_a.png',
    chaos: '/assets/ui/icons/icon_encounter_boss.png',
    hazard: '/assets/ui/icons/icon_encounter_locked.png',
  };

  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly dialogueService = inject(DialogueService);

  readonly runData = signal<CurrentRunData | null>(null);
  readonly loading = signal(true);
  readonly working = signal(false);
  readonly healingAction = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly statusMessage = signal<string | null>(null);
  readonly modalKind = signal<RunEncounterModalKind | null>(null);
  readonly modalNode = signal<CurrentRunNode | null>(null);
  readonly modalResult = signal<ResolveNodeData | null>(null);
  readonly modalRestData = signal<RestOpenData | null>(null);
  readonly modalDialogueScript = signal<DialogueScript | null>(null);
  readonly modalLoading = signal(false);
  readonly showGenerationDebug = isDevPanelEnabled();

  readonly nodes = computed(() => this.runData()?.map?.nodes ?? []);
  readonly edges = computed(() => this.runData()?.map?.edges ?? []);
  readonly run = computed(() => this.runData()?.run ?? null);
  readonly regionName = computed(() => {
    const run = this.run();
    if (!run) {
      return null;
    }

    if (run.region_name) {
      return run.region_name;
    }

    const profile = this.sessionService.profileData();
    const region = profile?.regions?.find(
      (candidate) =>
        String(candidate.id) === String(run.region_id) ||
        candidate.slug === run.region_slug,
    );

    return region?.name ?? null;
  });
  readonly pageTitle = computed(() => {
    const regionName = this.regionName();
    return regionName ?? 'Current Expedition';
  });
  readonly runProgressPercent = computed(() => {
    const nodes = this.nodes();
    if (!nodes.length) {
      return 0;
    }

    return Math.round((nodes.filter((node) => node.status === 'cleared').length / nodes.length) * 100);
  });
  readonly activeSquad = this.sessionService.activeSquad;
  readonly mapBackgroundUrl = computed(() => resolveRunRegionBackgroundUrl(this.run()));
  readonly nodeLayoutBounds = computed(() => {
    const xs = this.nodes().map((node) => this.nodeX(node));
    const ys = this.nodes().map((node) => this.nodeY(node));
    const radius = RunMapPageComponent.MAP_NODE_RADIUS;
    const horizontalPadding = RunMapPageComponent.MAP_HORIZONTAL_PADDING;
    const verticalPadding = RunMapPageComponent.MAP_VERTICAL_PADDING;

    if (!xs.length || !ys.length) {
      return {
        width: RunMapPageComponent.MAP_MIN_WIDTH,
        height: RunMapPageComponent.MAP_MIN_HEIGHT,
      };
    }

    const maxX = Math.max(...xs);
    const maxY = Math.max(...ys);

    return {
      width: Math.max(RunMapPageComponent.MAP_MIN_WIDTH, maxX + radius + horizontalPadding),
      height: Math.max(RunMapPageComponent.MAP_MIN_HEIGHT, maxY + radius + verticalPadding),
    };
  });
  readonly mapWidth = computed(() => this.nodeLayoutBounds().width);
  readonly mapHeight = computed(() => this.nodeLayoutBounds().height);
  readonly generationGridColumns = computed(() => {
    if (!this.showGenerationDebug) {
      return [];
    }

    return this.uniqueSortedCoordinates(this.nodes().map((node) => this.nodeMetaColumn(node)))
      .map((column) => ({
        value: column,
        x: RunMapPageComponent.MAP_HORIZONTAL_PADDING +
          column * RunMapPageComponent.MAP_NODE_HORIZONTAL_GAP,
      }));
  });
  readonly generationGridRows = computed(() => {
    if (!this.showGenerationDebug) {
      return [];
    }

    return this.uniqueSortedCoordinates(this.nodes().map((node) => this.nodeMetaRow(node)))
      .map((row) => ({
        value: row,
        y: RunMapPageComponent.MAP_VERTICAL_PADDING +
          row * RunMapPageComponent.MAP_ROW_VERTICAL_GAP,
      }));
  });
  readonly runUnits = computed(() => {
    const unitsById = new Map(this.sessionService.units().map((unit) => [unit.id, unit]));
    return (this.runData()?.run_unit_state ?? []).map((state) => ({
      ...state,
      unit: unitsById.get(state.unit_instance_id) ?? null,
      currentHp: state.current_hp ?? state.hp ?? 0,
      maxHp: unitsById.get(state.unit_instance_id)?.max_hp ?? state.current_hp ?? state.hp ?? 0,
      defeated: state.is_defeated || (state.current_hp ?? state.hp ?? 0) <= 0,
    }));
  });
  readonly runUnitById = computed(
    () => new Map(this.runUnits().map((entry) => [entry.unit_instance_id, entry])),
  );
  readonly woundedRunUnits = computed(() =>
    this.runUnits().filter((entry) => entry.currentHp < entry.maxHp),
  );
  readonly healingConsumables = computed(() =>
    (this.sessionService.profileData()?.items ?? [])
      .map((item) => this.mapHealingConsumable(item))
      .filter((item): item is { item_slug: string; name: string; quantity: number; amount: number } => item !== null),
  );
  readonly formationGrid = computed(() => {
    return buildFormationGrid(this.activeSquad()?.formation, this.runUnitById());
  });
  readonly sidebarUnits = computed(() =>
    this.formationGrid().map((cell) => {
      const entry = cell.entry;
      if (!entry) {
        return { cell: cell.cell, empty: true as const };
      }

      return {
        cell: cell.cell,
        empty: false as const,
        name: entry.unit?.name ?? 'Unit',
        status: entry.defeated ? 'DOWN' : `HP ${this.percentFromHp(entry.currentHp, entry.maxHp)}%`,
      };
    }),
  );
  readonly renderedEdges = computed(() => this.buildRenderedEdges(this.nodes(), this.edges()));
  readonly activeRunEffects = computed(() => this.runData()?.active_run_effects ?? []);
  readonly generationSummary = computed(() => this.run()?.generation_summary ?? null);
  readonly patternDebugRows = computed(() => {
    if (!this.showGenerationDebug) {
      return [];
    }

    const run = this.run();
    const summary = this.generationSummary();
    if (!run?.generator_version && !summary) {
      return [];
    }

    return [
      ['Generator', run?.generator_version ?? this.summaryString(summary, 'generator_version') ?? 'Unknown'],
      ['Profile', this.summaryString(summary, 'profile_version') ?? this.optionalNumberLabel(run?.generation_profile_version)],
      ['Nodes', this.summaryString(summary, 'node_count')],
      ['Branches', this.summaryString(summary, 'branch_count')],
      ['Spine', this.summaryString(summary, 'spine_depth')],
      ['Boss route', this.bossRouteLabel(summary)],
      ['Attempt', this.optionalNumberLabel(run?.generation_attempt)],
      ['Catalog', this.catalogHashLabel(run?.pattern_catalog_hash ?? this.summaryString(summary, 'catalog_hash'))],
    ].filter((row): row is [string, string] => typeof row[1] === 'string' && row[1].trim() !== '');
  });
  readonly patternNodeRows = computed(() => {
    if (!this.showGenerationDebug) {
      return [];
    }

    return this.nodes()
      .map((node) => this.patternNodeRow(node))
      .filter((row): row is { id: string; label: string; detail: string } => row !== null);
  });
  readonly modalLootItems = computed<RunModalLootItem[]>(() => {
    const preview = this.modalResult()?.battle.reward_preview;
    if (!preview) {
      return [];
    }

    const items: RunModalLootItem[] = [];
    if ((preview.currency_soft ?? 0) > 0) {
      items.push({
        id: 'teeth',
        icon: 'tooth',
        tone: 'teeth',
        label: `${preview.currency_soft} Teeth`,
        detail: 'Dental currency, regrettably spendable.',
      });
    }

    const dice = preview.dice?.length
      ? preview.dice
      : preview.new_dice_labels.map((label) => this.diceRewardFromLabel(label));
    for (const [index, die] of dice.entries()) {
      items.push({
        id: die.dice_instance_id ?? `die-${index}-${die.label}`,
        icon: `d${die.sides || this.diceSidesFromLabel(die.label)}`,
        tone: 'dice',
        label: die.label,
        detail: this.formatAffixNames(die.affixes),
      });
    }

    const units = preview.units?.length
      ? preview.units
      : preview.new_unit_labels.map((label) => this.unitRewardFromLabel(label));
    for (const [index, unit] of units.entries()) {
      items.push({
        id: unit.unit_instance_id ?? `unit-${index}-${unit.name}`,
        icon: 'sword',
        tone: 'unit',
        label: `${unit.name} (Unit)`,
        detail: `${unit.unit_type_name || 'Unit'} - ${formatUnitKinLabel(unit)}`,
      });
    }

    for (const [index, item] of (preview.items ?? []).entries()) {
      items.push({
        id: `${item.item_slug}-${index}`,
        icon: '+',
        tone: 'item',
        label: `${item.quantity} ${item.name}`,
        detail: item.rarity ? this.humanizeId(item.rarity) : null,
      });
    }

    for (const [index, label] of (preview.new_item_labels ?? []).entries()) {
      items.push({
        id: `item-label-${index}-${label}`,
        icon: '+',
        tone: 'item',
        label,
        detail: null,
      });
    }

    return items;
  });
  readonly modalRestUnits = computed<RunModalUnitStatus[]>(() => {
    const unitsById = new Map(this.sessionService.units().map((unit) => [unit.id, unit]));
    return (this.modalRestData()?.run_unit_state ?? []).map((state) => {
      const unit = unitsById.get(state.unit_instance_id);
      const currentHp = state.current_hp ?? state.hp ?? 0;
      const maxHp = unit?.max_hp ?? state.current_hp ?? state.hp ?? 0;
      const recovery = Math.max(0, maxHp - currentHp);
      return {
        id: state.unit_instance_id,
        name: unit?.name ?? 'Unit',
        currentHp,
        maxHp,
        hpPercent: this.percentFromHp(currentHp, maxHp),
        recoveryLabel: recovery > 0 ? `+${recovery}HP` : 'FULL',
        recoveryTone: recovery > 0 ? 'healed' : 'full',
      };
    });
  });
  readonly modalTitle = computed(() => {
    switch (this.modalKind()) {
      case 'loot':
        return 'Hidden Cache';
      case 'rest':
        return 'Goblin Campfire';
      case 'dialogue':
        return this.modalDialogueScript()?.title ?? 'Roadside Conversation';
      case 'shrine':
        return this.nodeResultEventLabel() || 'Ancient Shrine';
      case 'hazard':
        return this.nodeResultEventLabel() || 'Path Hazard';
      default:
        return 'Encounter';
    }
  });
  readonly modalEyebrow = computed(() => {
    switch (this.modalKind()) {
      case 'loot':
        return 'Treasure Found';
      case 'rest':
        return 'Rest Stop';
      case 'dialogue':
        return 'Conversation';
      case 'shrine':
        return 'Shrine Encountered';
      case 'hazard':
        return 'Hazard Encountered';
      default:
        return 'Encounter';
    }
  });
  readonly modalDescription = computed(() => {
    switch (this.modalKind()) {
      case 'loot':
        return 'A goblin stash, barely hidden beneath a pile of suspicious mushrooms. The shiny loot spills out invitingly as you kick it open.';
      case 'rest':
        return 'The logs crackle warmly as the warband rests from their tedious journey.';
      case 'dialogue':
        return this.modalDialogueScript()?.summary ?? 'The path pauses for words before blades.';
      case 'shrine':
        return this.nodeResultEventDetail() || 'The ancient stone totem hums with forgotten power as you approach.';
      case 'hazard':
        return this.nodeResultEventDetail() || 'The path bites back, but the warband can push through.';
      default:
        return '';
    }
  });
  readonly modalArtUrl = computed(() => {
    const node = this.modalNode();
    switch (this.modalKind()) {
      case 'loot':
        return resolveNodeArtUrl(node, 'loot');
      case 'rest':
        return resolveNodeArtUrl(node, 'rest');
      case 'shrine':
        return resolveNodeArtUrl(node, 'shrine');
      case 'hazard':
        return resolveNodeArtUrl(node, 'hazard');
      default:
        return this.mapBackgroundUrl() ?? '/assets/ui/biome/mystic_cave.png';
    }
  });

  constructor() {
    void this.load();
  }

  async load(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.getCurrentRun();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.runData.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load current run.');
    } finally {
      this.loading.set(false);
    }
  }

  nodeX(node: CurrentRunNode): number {
    const column = this.nodeMetaColumn(node);
    return (
      RunMapPageComponent.MAP_HORIZONTAL_PADDING +
      column * RunMapPageComponent.MAP_NODE_HORIZONTAL_GAP
    );
  }

  nodeY(node: CurrentRunNode): number {
    const row = this.nodeMetaRow(node);
    return (
      RunMapPageComponent.MAP_VERTICAL_PADDING +
      row * RunMapPageComponent.MAP_ROW_VERTICAL_GAP +
      this.nodeVerticalSpread(node)
    );
  }

  nodeById(nodeId: string): CurrentRunNode | undefined {
    return this.nodes().find((node) => node.id === nodeId);
  }

  edgeState(edge: CurrentRunEdge): 'available' | 'cleared' | 'locked' {
    const fromNode = this.nodeById(edge.from_node_id);
    const toNode = this.nodeById(edge.to_node_id);

    const fromStatus = fromNode?.status ?? 'locked';
    const toStatus = toNode?.status ?? 'locked';

    if (fromStatus === 'cleared' && toStatus === 'cleared') {
      return 'cleared';
    }
    if (
      (fromStatus === 'cleared' && toStatus === 'available') ||
      (fromStatus === 'available' && toStatus === 'cleared')
    ) {
      return 'available';
    }

    return 'locked';
  }

  iconForNode(node: CurrentRunNode): string {
    if (node.node_type === 'loot') {
      return resolveNodeArtUrl(node, 'loot');
    }
    if (node.node_type === 'shrine') {
      return resolveNodeArtUrl(node, 'shrine');
    }

    return this.iconForNodeType(node.node_type);
  }

  iconForNodeType(nodeType: string): string {
    return (
      RunMapPageComponent.ENCOUNTER_ICON_MAP[nodeType] ??
      '/assets/ui/icons/icon_encounter_locked.png'
    );
  }

  patternNodeDepthLabel(node: CurrentRunNode): string | null {
    if (!this.showGenerationDebug) {
      return null;
    }

    const generation = this.nodeGenerationMeta(node);
    if (!generation) {
      return null;
    }

    return String(this.numberFromUnknown(generation['depth']) ?? node.node_index);
  }

  nodeTypeLabel(nodeType: string): string {
    return nodeType.charAt(0).toUpperCase() + nodeType.slice(1);
  }

  async openNode(node: CurrentRunNode): Promise<void> {
    if (node.status !== 'available' || !this.run()) {
      return;
    }

    if (node.node_type === 'rest') {
      await this.openRestModal(node);
      return;
    }

    if (node.node_type === 'loot') {
      await this.openResolvedNodeModal(node, 'loot');
      return;
    }

    if (node.node_type === 'dialogue') {
      await this.openDialogueModal(node);
      return;
    }

    if (node.node_type === 'shrine' || node.node_type === 'hazard') {
      await this.openResolvedNodeModal(node, node.node_type);
      return;
    }

    if (node.node_type === 'exit') {
      await this.finishRun();
      return;
    }

    await this.router.navigate(['/run/node', node.id]);
  }

  closeModal(): void {
    if (this.modalLoading() || this.working()) {
      return;
    }

    this.clearModal();
  }

  async claimModalRewards(action: 'accept' | 'decline' = 'accept'): Promise<void> {
    const battleId = this.modalResult()?.battle.battle_id;
    if (!battleId) {
      this.clearModal();
      return;
    }

    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.claimBattleRewards(battleId, action);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      if (response.data.run_resolution?.status && response.data.run_resolution.status !== 'active') {
        await this.router.navigateByUrl('/run/summary');
        return;
      }

      this.clearModal();
      await this.load();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to claim encounter.');
    } finally {
      this.working.set(false);
    }
  }

  async finalizeRestModal(): Promise<void> {
    const run = this.run();
    const node = this.modalNode();
    if (!run || !node) {
      return;
    }

    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.finalizeRest(run.run_id, node.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.clearModal();
      await this.load();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to make camp.');
    } finally {
      this.working.set(false);
    }
  }

  async completeDialogueModal(): Promise<void> {
    const run = this.run();
    const node = this.modalNode();
    if (!run || !node || this.working()) {
      return;
    }

    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.completeDialogueNode(run.run_id, node.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.clearModal();
      await this.load();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to complete dialogue.');
    } finally {
      this.working.set(false);
    }
  }

  async returnHome(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.abandonRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to return home.');
    } finally {
      this.working.set(false);
    }
  }

  healingActionKey(unitInstanceId: string, itemSlug: string): string {
    return `${unitInstanceId}:${itemSlug}`;
  }

  async healUnit(unitInstanceId: string, itemSlug: string): Promise<void> {
    const run = this.run();
    if (!run) {
      return;
    }

    const actionKey = this.healingActionKey(unitInstanceId, itemSlug);
    this.healingAction.set(actionKey);
    this.error.set(null);
    this.statusMessage.set(null);

    try {
      const response = await this.runService.healRunUnit(run.run_id, unitInstanceId, itemSlug);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      const healing = response.data.healing;
      this.runData.update((current) => {
        if (!current) {
          return current;
        }

        return {
          ...current,
          run_unit_state: (current.run_unit_state ?? []).map((state) =>
            state.unit_instance_id === unitInstanceId
              ? {
                  ...state,
                  hp: healing.hp_after,
                  current_hp: healing.hp_after,
                  is_defeated: healing.is_defeated,
                }
              : state,
          ),
        };
      });

      const unitName = this.runUnits().find((entry) => entry.unit_instance_id === unitInstanceId)?.unit?.name ?? 'Unit';
      this.statusMessage.set(`${unitName} healed to ${healing.hp_after}/${healing.max_hp}.`);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to use healing item.');
    } finally {
      this.healingAction.set(null);
    }
  }

  async finishRun(): Promise<void> {
    if (!this.run()) {
      return;
    }
    this.working.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.exitRun(this.run()!.run_id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      await this.router.navigateByUrl('/run/summary');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to exit run.');
    } finally {
      this.working.set(false);
    }
  }

  nodeResultEventLabel(): string {
    const event = this.modalResult()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const label = typeof event?.['label'] === 'string' ? event['label'] : '';
    if (label.trim()) {
      return label.trim();
    }

    const message = typeof event?.['message'] === 'string' ? event['message'] : '';
    if (message.trim()) {
      return this.humanizeId(message);
    }

    return this.humanizeId(this.modalNode()?.node_type ?? 'encounter');
  }

  nodeResultEventDetail(): string {
    const event = this.modalResult()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const detail = typeof event?.['detail'] === 'string' ? event['detail'] : '';
    if (detail.trim()) {
      return detail.trim();
    }

    const result = this.nodeResultPayload();
    const resultCopy = typeof result?.['result_copy'] === 'string' ? result['result_copy'] : '';
    if (resultCopy.trim()) {
      return resultCopy.trim();
    }

    const favor = typeof result?.['favor'] === 'string' ? result['favor'] : '';
    return favor.trim() ? `${this.humanizeId(favor)} settled over the warband.` : '';
  }

  nodeResultEffectLabel(): string {
    const result = this.nodeResultPayload();
    const effect = result?.['effect'];
    const effectRecord = effect && typeof effect === 'object' && !Array.isArray(effect)
      ? effect as Record<string, unknown>
      : {};
    const type = typeof effectRecord['type'] === 'string' ? effectRecord['type'] : '';

    if (this.modalKind() === 'hazard') {
      return this.humanizeId(type || 'hazard effect');
    }

    const title = typeof result?.['title'] === 'string' ? result['title'] : '';
    return title.trim() ? title.trim() : this.humanizeId(type || 'blessing');
  }

  nodeResultEffectDetail(): string {
    const result = this.nodeResultPayload();
    const effect = result?.['effect'];
    const effectRecord = effect && typeof effect === 'object' && !Array.isArray(effect)
      ? effect as Record<string, unknown>
      : {};
    const type = typeof effectRecord['type'] === 'string' ? effectRecord['type'] : '';

    switch (type) {
      case 'grant_teeth':
        return this.modalResultRewardLabel();
      case 'heal_random_unit':
        return `Heals one wounded unit for ${this.percentLabel(effectRecord['amount_pct'], 35)} of max life.`;
      case 'drain_highest_life_heal_rest':
        return 'Fully heals the squad.';
      case 'damage_random_unit':
        return `Damages one unit for ${this.numberLabel(effectRecord['damage'], 1)} life.`;
      case 'damage_squad':
        return `Damages each living unit for ${this.numberLabel(effectRecord['damage'], 1)} life.`;
      case 'lose_teeth':
        return `Loses up to ${this.numberLabel(effectRecord['amount'], 1)} teeth.`;
      case 'squad_damage_next_combat':
        return `${this.multiplierBonusLabel(effectRecord['damage_multiplier'], 1.10)} damage for the squad.`;
      case 'run_stat_modifier_next_combat':
      case 'stat_modifier_next_combat':
      case 'squad_stat_modifier_next_combat':
        return this.describeStatModifier(effectRecord);
      default:
        return this.modalKind() === 'hazard'
          ? 'The route is cleared and the warband can continue.'
          : 'The shrine favor is ready to claim.';
    }
  }

  shrineCostLabel(): string | null {
    if (this.modalKind() !== 'shrine') {
      return null;
    }

    const result = this.nodeResultPayload();
    const effect = result?.['effect'];
    const effectRecord = effect && typeof effect === 'object' && !Array.isArray(effect)
      ? effect as Record<string, unknown>
      : {};
    const effectType = typeof effectRecord['type'] === 'string' ? effectRecord['type'] : '';

    if (effectType === 'drain_highest_life_heal_rest') {
      const drainPct = Number(effectRecord['drain_pct'] ?? 0);
      return drainPct > 0
        ? `The healthiest unit loses ${drainPct}% life.`
        : 'The healthiest unit pays life.';
    }

    const cost = result?.['cost'];
    const costRecord = cost && typeof cost === 'object' && !Array.isArray(cost)
      ? cost as Record<string, unknown>
      : null;
    if (!costRecord) {
      return null;
    }

    const copy = typeof costRecord['copy'] === 'string' ? costRecord['copy'].trim() : '';
    if (copy) {
      return copy.replace(/^Cost:\s*/i, '');
    }

    return 'This shrine has a negative side effect.';
  }

  modalResultRewardLabel(): string {
    const preview = this.modalResult()?.battle.reward_preview;
    if (!preview) {
      return 'No material reward.';
    }

    const labels: string[] = [];
    if ((preview.currency_soft ?? 0) > 0) {
      labels.push(`${preview.currency_soft} teeth`);
    }
    if ((preview.dice?.length ?? preview.new_dice_labels.length) > 0) {
      labels.push(`${preview.dice?.length ?? preview.new_dice_labels.length} dice`);
    }
    if ((preview.units?.length ?? preview.new_unit_labels.length) > 0) {
      labels.push(`${preview.units?.length ?? preview.new_unit_labels.length} units`);
    }
    if ((preview.items?.length ?? preview.new_item_labels?.length ?? 0) > 0) {
      labels.push(`${preview.items?.length ?? preview.new_item_labels?.length ?? 0} items`);
    }

    return labels.length ? labels.join(', ') : 'No material reward.';
  }

  private async openResolvedNodeModal(node: CurrentRunNode, kind: 'loot' | 'shrine' | 'hazard'): Promise<void> {
    const run = this.run();
    if (!run) {
      return;
    }

    this.prepareModal(node, kind);
    try {
      const response = await this.runService.resolveNode(run.run_id, node.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.modalResult.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to resolve encounter.');
    } finally {
      this.modalLoading.set(false);
    }
  }

  private async openRestModal(node: CurrentRunNode): Promise<void> {
    const run = this.run();
    if (!run) {
      return;
    }

    this.prepareModal(node, 'rest');
    try {
      const response = await this.runService.openRest(run.run_id, node.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.modalRestData.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to open rest stop.');
    } finally {
      this.modalLoading.set(false);
    }
  }

  private async openDialogueModal(node: CurrentRunNode): Promise<void> {
    const run = this.run();
    if (!run) {
      return;
    }

    this.prepareModal(node, 'dialogue');
    try {
      const dialogueId = this.dialogueIdForNode(node);
      if (!dialogueId) {
        this.error.set('Dialogue node is missing its script id.');
        return;
      }

      const script = await this.dialogueService.getDialogueById(dialogueId, {
        scene: 'run-dialogue',
        nodeType: node.node_type,
        regionSlug: run.region_slug ?? null,
        regionId: run.region_id ?? null,
        tags: this.dialogueTagsForNode(node),
        playerName: this.sessionService.session().displayName,
        playerPortraitUrl: RunMapPageComponent.PLAYER_DIALOGUE_PORTRAIT,
      } satisfies DialogueTriggerContext);
      if (!script) {
        this.error.set(`Dialogue script "${dialogueId}" could not be loaded.`);
        return;
      }

      this.modalDialogueScript.set(script);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load dialogue.');
    } finally {
      this.modalLoading.set(false);
    }
  }

  private prepareModal(node: CurrentRunNode, kind: RunEncounterModalKind): void {
    this.error.set(null);
    this.statusMessage.set(null);
    this.modalKind.set(kind);
    this.modalNode.set(node);
    this.modalResult.set(null);
    this.modalRestData.set(null);
    this.modalDialogueScript.set(null);
    this.modalLoading.set(true);
  }

  private clearModal(): void {
    this.modalKind.set(null);
    this.modalNode.set(null);
    this.modalResult.set(null);
    this.modalRestData.set(null);
    this.modalDialogueScript.set(null);
    this.modalLoading.set(false);
  }

  private nodeResultPayload(): Record<string, unknown> | null {
    const previewResult = this.modalResult()?.battle.reward_preview?.encounter_result;
    if (previewResult && typeof previewResult === 'object' && !Array.isArray(previewResult)) {
      const nested = previewResult['result'];
      if (nested && typeof nested === 'object' && !Array.isArray(nested)) {
        return nested as Record<string, unknown>;
      }
    }

    const event = this.modalResult()?.battle.log?.events?.[0] as Record<string, unknown> | undefined;
    const key = this.modalKind() === 'hazard' ? 'hazard_result' : 'shrine_result';
    const result = event?.[key];
    return result && typeof result === 'object' && !Array.isArray(result)
      ? result as Record<string, unknown>
      : null;
  }

  private dialogueIdForNode(node: CurrentRunNode): string | null {
    const value = node.meta?.['dialogue_id'];
    return typeof value === 'string' && value.trim() ? value.trim() : null;
  }

  private dialogueTagsForNode(node: CurrentRunNode): string[] {
    const value = node.meta?.['tags'];
    if (!Array.isArray(value)) {
      return [];
    }

    return value.filter((tag): tag is string => typeof tag === 'string' && tag.trim().length > 0);
  }

  private percentFromHp(currentHp: number, maxHp: number): number {
    if (maxHp <= 0) {
      return 0;
    }

    return Math.round((Math.max(0, Math.min(currentHp, maxHp)) / maxHp) * 100);
  }

  private diceRewardFromLabel(label: string): RewardPreviewDice {
    const sides = this.diceSidesFromLabel(label);
    const material = label.trim().split(/\s+/)[0]?.toLowerCase() ?? 'cardboard';

    return {
      dice_instance_id: null,
      label,
      rarity: 'common',
      material,
      sides,
      affixes: [],
    };
  }

  private diceSidesFromLabel(label: string): number {
    const match = label.trim().match(/\bd(\d+)\b/i);
    const sides = Number(match?.[1] ?? 6);
    return Number.isFinite(sides) ? sides : 6;
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

  private formatAffixNames(affixes: RewardPreviewDice['affixes']): string | null {
    const names = affixes
      .map((affix) => (affix.name ?? this.humanizeId(affix.affix_slug ?? '')).trim())
      .filter((name) => name.length > 0);

    return names.length > 0 ? names.join(' ') : null;
  }

  private describeStatModifier(effect: Record<string, unknown>): string {
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

  private numberLabel(value: unknown, fallback: number): string {
    const numeric = Number(value ?? fallback);
    return String(Number.isFinite(numeric) ? numeric : fallback);
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }

  private nodeMetaColumn(node: CurrentRunNode): number {
    const generationX = this.numberFromUnknown(this.nodeGenerationMeta(node)?.['x']);
    if (generationX !== null) {
      return generationX;
    }

    const raw = node.meta?.['col'];
    const column = typeof raw === 'number' ? raw : Number(raw);
    return Number.isFinite(column) ? column : node.node_index;
  }

  private nodeMetaRow(node: CurrentRunNode): number {
    const generationY = this.numberFromUnknown(this.nodeGenerationMeta(node)?.['y']);
    if (generationY !== null) {
      return generationY;
    }

    const raw = node.meta?.['row'];
    const row = typeof raw === 'number' ? raw : Number(raw);
    if (Number.isFinite(row)) {
      return row;
    }

    return node.node_index % 2 === 0 ? 1 : 2;
  }

  private nodeVerticalSpread(node: CurrentRunNode): number {
    const row = this.nodeMetaRow(node);
    if (row === 1) {
      return 0;
    }

    const span = this.longestIncidentEdgeSpan(node.id);
    if (span <= 1) {
      return 0;
    }

    const spread = Math.min(
      RunMapPageComponent.MAP_LONG_EDGE_VERTICAL_SPREAD_MAX,
      (span - 1) * RunMapPageComponent.MAP_LONG_EDGE_VERTICAL_SPREAD,
    );

    return row === 0 ? -spread : spread;
  }

  private buildRenderedEdges(
    nodes: CurrentRunNode[],
    edges: CurrentRunEdge[],
  ): Array<{
    edgeId: string;
    path: string;
    state: 'available' | 'cleared' | 'locked';
  }> {
    const nodeById = new Map(nodes.map((node) => [node.id, node]));
    const outgoingByNode = new Map<string, CurrentRunEdge[]>();
    const incomingByNode = new Map<string, CurrentRunEdge[]>();

    for (const edge of edges) {
      const outgoing = outgoingByNode.get(edge.from_node_id) ?? [];
      outgoing.push(edge);
      outgoingByNode.set(edge.from_node_id, outgoing);

      const incoming = incomingByNode.get(edge.to_node_id) ?? [];
      incoming.push(edge);
      incomingByNode.set(edge.to_node_id, incoming);
    }

    for (const siblingEdges of outgoingByNode.values()) {
      siblingEdges.sort(
        (left, right) =>
          this.edgeSortValue(nodeById.get(left.to_node_id)) -
          this.edgeSortValue(nodeById.get(right.to_node_id)),
      );
    }

    for (const siblingEdges of incomingByNode.values()) {
      siblingEdges.sort(
        (left, right) =>
          this.edgeSortValue(nodeById.get(left.from_node_id)) -
          this.edgeSortValue(nodeById.get(right.from_node_id)),
      );
    }

    return edges.map((edge) => {
      const fromNode = nodeById.get(edge.from_node_id);
      const toNode = nodeById.get(edge.to_node_id);
      if (!fromNode || !toNode) {
        return {
          edgeId: edge.edge_id ?? `${edge.from_node_id}->${edge.to_node_id}`,
          path: '',
          state: 'locked' as const,
        };
      }

      const sourceOffset = this.siblingOffset(edge, outgoingByNode.get(edge.from_node_id) ?? []);
      const targetOffset = this.siblingOffset(edge, incomingByNode.get(edge.to_node_id) ?? []);
      const x1 = this.nodeX(fromNode);
      const y1 = this.nodeY(fromNode);
      const x2 = this.nodeX(toNode);
      const y2 = this.nodeY(toNode);
      const curveLead = Math.min(
        RunMapPageComponent.MAP_EDGE_CURVE_LEAD_MAX,
        Math.max(RunMapPageComponent.MAP_EDGE_CURVE_LEAD_MIN, Math.round((x2 - x1) * 0.35)),
      );
      const waypoints = this.edgeWaypoints(edge);
      const path = waypoints.length
        ? [
            `M ${x1} ${y1 + sourceOffset}`,
            ...waypoints.map((point) => `L ${point.x} ${point.y}`),
            `L ${x2} ${y2 + targetOffset}`,
          ].join(' ')
        : sourceOffset === 0 && targetOffset === 0 && y1 === y2
          ? `M ${x1} ${y1} L ${x2} ${y2}`
          : `M ${x1} ${y1} C ${x1 + curveLead} ${y1 + sourceOffset}, ${x2 - curveLead} ${y2 + targetOffset}, ${x2} ${y2}`;

      return {
        edgeId: edge.edge_id ?? `${edge.from_node_id}->${edge.to_node_id}`,
        path,
        state: this.edgeState(edge),
      };
    });
  }

  private siblingOffset(edge: CurrentRunEdge, siblings: CurrentRunEdge[]): number {
    if (siblings.length <= 1) {
      return 0;
    }

    const edgeIndex = siblings.findIndex((candidate) => candidate.edge_id === edge.edge_id);
    if (edgeIndex === -1) {
      return 0;
    }

    return (edgeIndex - (siblings.length - 1) / 2) * RunMapPageComponent.MAP_EDGE_FAN_OFFSET;
  }

  private edgeWaypoints(edge: CurrentRunEdge): Array<{ x: number; y: number }> {
    const through = edge.meta?.['through'];
    if (!Array.isArray(through)) {
      return [];
    }

    return through
      .map((point) => {
        if (!point || typeof point !== 'object' || Array.isArray(point)) {
          return null;
        }

        const values = point as Record<string, unknown>;
        const gridX = this.numberFromUnknown(values['x'] ?? values['col']);
        const gridY = this.numberFromUnknown(values['y'] ?? values['row']);
        if (gridX === null || gridY === null) {
          return null;
        }

        return {
          x: RunMapPageComponent.MAP_HORIZONTAL_PADDING + gridX * RunMapPageComponent.MAP_NODE_HORIZONTAL_GAP,
          y: RunMapPageComponent.MAP_VERTICAL_PADDING + gridY * RunMapPageComponent.MAP_ROW_VERTICAL_GAP,
        };
      })
      .filter((point): point is { x: number; y: number } => point !== null);
  }

  private edgeSortValue(node: CurrentRunNode | undefined): number {
    if (!node) {
      return Number.MAX_SAFE_INTEGER;
    }

    return this.nodeMetaRow(node) * 100 + this.nodeMetaColumn(node);
  }

  private longestIncidentEdgeSpan(nodeId: string): number {
    const node = this.nodeById(nodeId);
    if (!node) {
      return 0;
    }

    const nodeColumn = this.nodeMetaColumn(node);
    let longestSpan = 0;

    for (const edge of this.edges()) {
      if (edge.from_node_id !== nodeId && edge.to_node_id !== nodeId) {
        continue;
      }

      const otherNode = this.nodeById(
        edge.from_node_id === nodeId ? edge.to_node_id : edge.from_node_id,
      );
      if (!otherNode) {
        continue;
      }

      longestSpan = Math.max(longestSpan, Math.abs(this.nodeMetaColumn(otherNode) - nodeColumn));
    }

    return longestSpan;
  }

  private mapHealingConsumable(
    item: ItemRecord,
  ): { item_slug: string; name: string; quantity: number; amount: number } | null {
    const effect = item.meta?.['effect'];
    if (!effect || typeof effect !== 'object' || Array.isArray(effect)) {
      return null;
    }

    const type = String((effect as Record<string, unknown>)['type'] ?? '');
    const amount = Number((effect as Record<string, unknown>)['amount'] ?? 0);
    if (
      item.category !== 'consumable' ||
      !item.is_spendable ||
      type !== 'heal_run_unit_hp' ||
      !Number.isFinite(amount) ||
      amount <= 0 ||
      item.quantity <= 0
    ) {
      return null;
    }

    return {
      item_slug: item.item_slug,
      name: item.name,
      quantity: item.quantity,
      amount,
    };
  }

  private patternNodeRow(node: CurrentRunNode): { id: string; label: string; detail: string } | null {
    const generation = this.nodeGenerationMeta(node);
    if (!generation) {
      return null;
    }

    const role = String(generation['path_role'] ?? '').trim();
    const depth = this.numberFromUnknown(generation['depth']);
    const patternKey = String(generation['pattern_key'] ?? '').trim();
    const labelParts = [this.nodeTypeLabel(node.node_type)];
    if (role) {
      labelParts.push(role);
    }
    if (depth !== null) {
      labelParts.push(`depth ${depth}`);
    }

    return {
      id: node.id,
      label: labelParts.join(' · '),
      detail: patternKey || 'pattern-v1',
    };
  }

  private nodeGenerationMeta(node: CurrentRunNode): Record<string, unknown> | null {
    const generation = node.meta?.['generation'];
    return generation && typeof generation === 'object' && !Array.isArray(generation)
      ? generation as Record<string, unknown>
      : null;
  }

  private summaryString(summary: Record<string, unknown> | null, key: string): string | null {
    if (!summary || !(key in summary)) {
      return null;
    }

    const value = summary[key];
    if (typeof value === 'string') {
      return value;
    }
    if (typeof value === 'number' && Number.isFinite(value)) {
      return String(value);
    }

    return null;
  }

  private optionalNumberLabel(value: number | null | undefined): string | null {
    return typeof value === 'number' && Number.isFinite(value) ? String(value) : null;
  }

  private bossRouteLabel(summary: Record<string, unknown> | null): string | null {
    const bossPath = summary?.['boss_path'];
    if (!bossPath || typeof bossPath !== 'object' || Array.isArray(bossPath)) {
      return null;
    }

    const values = bossPath as Record<string, unknown>;
    const startToBoss = this.numberFromUnknown(values['start_to_boss']);
    const bossToExit = this.numberFromUnknown(values['boss_to_exit']);
    if (startToBoss === null && bossToExit === null) {
      return null;
    }

    return `${startToBoss ?? '?'} to boss, ${bossToExit ?? '?'} to exit`;
  }

  private catalogHashLabel(value: string | null | undefined): string | null {
    if (!value) {
      return null;
    }

    return value.length > 12 ? value.slice(0, 12) : value;
  }

  private numberFromUnknown(value: unknown): number | null {
    const numeric = typeof value === 'number' ? value : Number(value);
    return Number.isFinite(numeric) ? numeric : null;
  }

  private uniqueSortedCoordinates(values: number[]): number[] {
    return [...new Set(values.filter((value) => Number.isFinite(value)))]
      .sort((left, right) => left - right);
  }
}

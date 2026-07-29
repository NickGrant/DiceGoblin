import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { isDevPanelEnabled } from '../../core/config/runtime-config';
import { CurrentRunData, CurrentRunEdge, CurrentRunNode, ItemRecord } from '../../core/models/api.models';
import { resolveRunRegionBackgroundUrl } from '../../core/regions/region-catalog';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { buildFormationGrid } from '../../shared/formation/formation';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { RunUnitFormationGridComponent } from '../../shared/ui/run-unit-formation-grid/run-unit-formation-grid.component';
import { resolveNodeArtUrl } from '../../shared/ui/node-art/node-art';

@Component({
  selector: 'app-run-map-page',
  standalone: true,
  imports: [
    DgAlertComponent,
    DgCommandBtnDirective,
    PageFrameComponent,
    RunUnitFormationGridComponent,
  ],
  templateUrl: './run-map-page.component.html',
  styleUrl: './run-map-page.component.scss',
})
export class RunMapPageComponent {
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

  readonly runData = signal<CurrentRunData | null>(null);
  readonly loading = signal(true);
  readonly working = signal(false);
  readonly healingAction = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly statusMessage = signal<string | null>(null);
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
    return regionName ? `Continue Run - ${regionName}` : 'Continue Run';
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

    if (fromNode?.status === 'cleared' && toNode?.status === 'cleared') {
      return 'cleared';
    }
    if (fromNode?.status === 'cleared' && toNode?.status === 'available') {
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
      await this.router.navigate(['/run/rest', node.id]);
      return;
    }

    if (node.node_type === 'loot') {
      await this.router.navigate(['/run/loot', node.id]);
      return;
    }

    if (node.node_type === 'dialogue') {
      await this.router.navigate(['/run/dialogue', node.id]);
      return;
    }

    if (node.node_type === 'exit') {
      await this.finishRun();
      return;
    }

    await this.router.navigate(['/run/node', node.id]);
  }

  async abandonRun(): Promise<void> {
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
      this.error.set(error instanceof Error ? error.message : 'Unable to abandon run.');
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
      const path =
        sourceOffset === 0 && targetOffset === 0 && y1 === y2
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
}

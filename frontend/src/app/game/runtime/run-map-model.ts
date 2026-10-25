import { ClientContentRegistry } from './client-content-registry';
import { CurrentRun, CurrentRunNodeStatus, RunContractError, RunNodeInteraction } from './run-contracts';
import { Bounds, RuntimeViewportSnapshot } from './runtime-viewport';

export interface RunMapNodePresentation {
  readonly id: string;
  readonly nodeIndex: number;
  readonly nodeTypeId: string;
  readonly battleId: string | null;
  readonly interaction?: RunNodeInteraction;
  readonly name: string;
  readonly description: string;
  readonly iconKey: string;
  readonly status: CurrentRunNodeStatus;
  readonly statusLabel: 'Locked' | 'Ready' | 'Completed';
  readonly isReachable: boolean;
  readonly position: { readonly column: number; readonly row: number };
}

export interface RunMapPresentation {
  readonly runId: string;
  readonly squadId: string;
  readonly regionName: string;
  readonly nodes: readonly RunMapNodePresentation[];
  readonly edges: readonly { readonly fromNodeId: string; readonly toNodeId: string }[];
}

export interface RunMapLayoutNode extends RunMapNodePresentation {
  readonly centerX: number;
  readonly centerY: number;
  readonly radius: number;
}

export interface RunMapLayoutEdge {
  readonly fromNodeId: string;
  readonly toNodeId: string;
  readonly startX: number;
  readonly startY: number;
  readonly endX: number;
  readonly endY: number;
}

export interface RunMapLayout {
  readonly panel: Bounds;
  readonly map: Bounds;
  readonly nodes: readonly RunMapLayoutNode[];
  readonly edges: readonly RunMapLayoutEdge[];
  readonly returnButton: Bounds;
  readonly abandonButton: Bounds;
  readonly combatButton: Bounds;
  readonly interactionButtons: readonly Bounds[];
  readonly confirmation: Bounds;
  readonly titleY: number;
  readonly identityY: number;
  readonly detailY: number;
}

const statusLabels: Record<CurrentRunNodeStatus, RunMapNodePresentation['statusLabel']> = {
  locked: 'Locked',
  available: 'Ready',
  completed: 'Completed',
};

export const runNodeColors: Record<CurrentRunNodeStatus, { fill: number; border: number; text: string }> = {
  locked: { fill: 0x494b48, border: 0x777b75, text: '#c5c8c1' },
  available: { fill: 0x365f3f, border: 0xe3b43b, text: '#fff2bd' },
  completed: { fill: 0x315d68, border: 0x79c9c0, text: '#dcfff8' },
};

export function createRunMapPresentation(run: CurrentRun, content: ClientContentRegistry): RunMapPresentation {
  const region = content.getRegion(run.regionId);
  if (!region) throw new RunContractError('Run map region is not projected authored content.');
  const nodes = run.nodes.map((node): RunMapNodePresentation => {
    const authored = content.getRunNodeType(node.nodeTypeId);
    if (!authored) throw new RunContractError('Run map node type is not projected authored content.');
    return Object.freeze({
      id: node.id,
      nodeIndex: node.nodeIndex,
      nodeTypeId: node.nodeTypeId,
      battleId: node.battleId,
      ...(node.interaction ? { interaction: node.interaction } : {}),
      name: node.interaction?.title ?? authored.display_name,
      description: node.interaction ? `${node.interaction.body} ${node.interaction.prompt}` : authored.description,
      iconKey: authored.icon_key,
      status: node.status,
      isReachable: run.availableNodeIds.includes(node.id),
      statusLabel: statusLabels[node.status],
      position: node.position,
    });
  });
  return Object.freeze({
    runId: run.id,
    squadId: run.squadId,
    regionName: region.display_name,
    nodes: Object.freeze(nodes),
    edges: Object.freeze(run.edges.map((edge) => Object.freeze({ ...edge }))),
  });
}

export function createRunMapLayout(
  snapshot: RuntimeViewportSnapshot,
  presentation: RunMapPresentation,
  interactionActionCount = 1,
): RunMapLayout {
  const compact = snapshot.layoutClass === 'compact';
  const columns = presentation.nodes.map((node) => node.position.column);
  const rows = presentation.nodes.map((node) => node.position.row);
  const minColumn = Math.min(...columns), maxColumn = Math.max(...columns);
  const minRow = Math.min(...rows), maxRow = Math.max(...rows);
  const denseCompact = compact && presentation.nodes.length > 9 && maxRow - minRow >= 2;
  const margin = compact ? 26 : 44;
  const panelWidth = Math.min(snapshot.layoutClass === 'wide' ? 1960 : 1510, snapshot.safeBounds.width - margin * 2);
  const panelHeight = snapshot.safeBounds.height - margin * 2;
  const panel = bounds(
    snapshot.safeBounds.x + (snapshot.safeBounds.width - panelWidth) / 2,
    snapshot.safeBounds.y + margin,
    panelWidth,
    panelHeight,
  );
  const buttonWidth = compact ? 330 : 280;
  const buttonHeight = compact ? 92 : 58;
  const buttonY = panel.bottom - buttonHeight - (compact ? 24 : 28);
  const actionColumns = Math.min(Math.max(interactionActionCount, 1), 3);
  const actionRows = Math.ceil(interactionActionCount / actionColumns);
  const multipleActions = interactionActionCount > 1;
  const actionGap = compact ? 18 : 16;
  const actionTop = buttonY - (multipleActions ? actionRows * buttonHeight + (actionRows - 1) * actionGap + actionGap : 0);
  const mapTop = panel.y + (denseCompact ? 95 : compact ? 142 : 138);
  const mapBottom = multipleActions ? actionTop - (compact ? 120 : 100)
    : panel.bottom - (denseCompact ? 244 : compact ? 314 : 202);
  const map = bounds(panel.x + (compact ? 74 : 86), mapTop,
    panel.width - (compact ? 148 : 172), Math.max(180, mapBottom - mapTop));
  const radius = denseCompact ? 42 : compact ? 55 : 48;
  const usableWidth = Math.max(0, map.width - (radius + 45) * 2);
  const usableHeight = Math.max(0, map.height - (radius + 38) * 2) * (compact && !denseCompact ? 0.75 : 1);
  const nodes = presentation.nodes.map((node): RunMapLayoutNode => Object.freeze({
    ...node,
    centerX: map.x + radius + 45 + normalized(node.position.column, minColumn, maxColumn) * usableWidth,
    centerY: map.y + radius + 38 + normalized(node.position.row, minRow, maxRow) * usableHeight,
    radius,
  }));
  const nodesById = new Map(nodes.map((node) => [node.id, node]));
  const edges = presentation.edges.map((edge): RunMapLayoutEdge => {
    const from = nodesById.get(edge.fromNodeId);
    const to = nodesById.get(edge.toNodeId);
    if (!from || !to) throw new RunContractError('Run map edge references a missing node.');
    const dx = to.centerX - from.centerX, dy = to.centerY - from.centerY;
    const distance = Math.hypot(dx, dy);
    const inset = Math.min(radius + 7, distance / 3);
    const ux = distance === 0 ? 0 : dx / distance, uy = distance === 0 ? 0 : dy / distance;
    return Object.freeze({ fromNodeId: edge.fromNodeId, toNodeId: edge.toNodeId,
      startX: from.centerX + ux * inset, startY: from.centerY + uy * inset,
      endX: to.centerX - ux * inset, endY: to.centerY - uy * inset });
  });
  const actionWidth = Math.min(buttonWidth, (panel.width - 96 - (actionColumns - 1) * actionGap) / actionColumns);
  const actionRowWidth = actionColumns * actionWidth + (actionColumns - 1) * actionGap;
  const interactionButtons = Array.from({ length: interactionActionCount }, (_, index) => bounds(
    panel.x + (panel.width - actionRowWidth) / 2 + (index % actionColumns) * (actionWidth + actionGap),
    actionTop + Math.floor(index / actionColumns) * (buttonHeight + actionGap), actionWidth, buttonHeight));
  return Object.freeze({ panel, map, nodes: Object.freeze(nodes), edges: Object.freeze(edges),
    returnButton: bounds(panel.x + 38, buttonY, buttonWidth, buttonHeight),
    abandonButton: bounds(panel.right - buttonWidth - 38, buttonY, buttonWidth, buttonHeight),
    combatButton: bounds(panel.x + (panel.width - buttonWidth) / 2, buttonY, buttonWidth, buttonHeight),
    interactionButtons: Object.freeze(interactionButtons),
    confirmation: bounds(panel.x + (panel.width - Math.min(760, panel.width - 100)) / 2,
      panel.y + (panel.height - Math.min(390, panel.height - 80)) / 2,
      Math.min(760, panel.width - 100), Math.min(390, panel.height - 80)),
    titleY: panel.y + (compact ? 48 : 44), identityY: panel.y + (compact ? 100 : 91),
    detailY: multipleActions ? actionTop - (compact ? 55 : 48)
      : denseCompact ? buttonY - 55 : map.bottom + (compact ? 50 : 30) });
}

function normalized(value: number, minimum: number, maximum: number): number {
  return minimum === maximum ? 0.5 : (value - minimum) / (maximum - minimum);
}

function bounds(x: number, y: number, width: number, height: number): Bounds {
  return Object.freeze({ x, y, width, height, right: x + width, bottom: y + height });
}

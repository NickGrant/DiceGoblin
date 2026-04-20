import type { TeamFormationCell, UnitRecord } from "../types/ApiResponse";

export type FormationCellId = "A1" | "A2" | "A3" | "B1" | "B2" | "B3" | "C1" | "C2" | "C3";
export type FormationFootprint = { w: number; h: number };
export type FormationOccupancyMap = Record<FormationCellId, string | null>;

export const FORMATION_CELLS: FormationCellId[] = ["A1", "A2", "A3", "B1", "B2", "B3", "C1", "C2", "C3"];

export function emptyFormationMap(): FormationOccupancyMap {
  return { A1: null, A2: null, A3: null, B1: null, B2: null, B3: null, C1: null, C2: null, C3: null };
}

export function unitFootprint(unit: Pick<UnitRecord, "formation_width" | "formation_height"> | null | undefined): FormationFootprint {
  const width = clampSpan(unit?.formation_width);
  const height = clampSpan(unit?.formation_height);
  return { w: width, h: height };
}

export function formationRowsToMap(rows: TeamFormationCell[] | undefined | null): FormationOccupancyMap {
  const map = emptyFormationMap();
  for (const row of rows ?? []) {
    if (!isFormationCell(row?.cell)) continue;
    map[row.cell] = row.unit_instance_id ?? null;
  }
  return map;
}

export function formationMapToRows(map: FormationOccupancyMap): TeamFormationCell[] {
  return FORMATION_CELLS.map((cell) => ({
    cell,
    unit_instance_id: map[cell] ?? null,
  }));
}

export function clearUnitFromFormation(map: FormationOccupancyMap, unitId: string): FormationOccupancyMap {
  const next = { ...map };
  for (const cell of FORMATION_CELLS) {
    if (next[cell] === unitId) {
      next[cell] = null;
    }
  }
  return next;
}

export function placedCellsForUnit(map: FormationOccupancyMap, unitId: string): FormationCellId[] {
  return FORMATION_CELLS.filter((cell) => map[cell] === unitId);
}

export function anchorCellForUnit(map: FormationOccupancyMap, unitId: string): FormationCellId | null {
  const cells = placedCellsForUnit(map, unitId);
  if (cells.length === 0) return null;
  return cells.slice().sort(compareCells)[0] ?? null;
}

export function anchorCellForCells(cells: string[]): FormationCellId | null {
  const validCells = cells.filter(isFormationCell) as FormationCellId[];
  if (validCells.length === 0) return null;
  return validCells.slice().sort(compareCells)[0] ?? null;
}

export function occupiedCellsFromAnchor(anchorCell: FormationCellId, footprint: FormationFootprint): FormationCellId[] | null {
  const anchor = cellToGrid(anchorCell);
  if (!anchor) return null;
  const cells: FormationCellId[] = [];
  for (let rowOffset = 0; rowOffset < footprint.h; rowOffset += 1) {
    for (let colOffset = 0; colOffset < footprint.w; colOffset += 1) {
      const cell = gridToCell(anchor.row + rowOffset, anchor.col + colOffset);
      if (!cell) return null;
      cells.push(cell);
    }
  }
  return cells.sort(compareCells);
}

export function canPlaceUnitAt(
  map: FormationOccupancyMap,
  unitId: string,
  anchorCell: FormationCellId,
  footprint: FormationFootprint
): boolean {
  const occupiedCells = occupiedCellsFromAnchor(anchorCell, footprint);
  if (!occupiedCells) return false;
  for (const cell of occupiedCells) {
    const occupant = map[cell];
    if (occupant !== null && occupant !== unitId) {
      return false;
    }
  }
  return true;
}

export function placeUnitAt(
  map: FormationOccupancyMap,
  unitId: string,
  anchorCell: FormationCellId,
  footprint: FormationFootprint
): FormationOccupancyMap | null {
  const occupiedCells = occupiedCellsFromAnchor(anchorCell, footprint);
  if (!occupiedCells) return null;

  const next = clearUnitFromFormation(map, unitId);
  for (const cell of occupiedCells) {
    const occupant = next[cell];
    if (occupant !== null && occupant !== unitId) {
      return null;
    }
  }
  for (const cell of occupiedCells) {
    next[cell] = unitId;
  }
  return next;
}

export function isAnchorCell(
  map: FormationOccupancyMap,
  unitId: string,
  cell: FormationCellId
): boolean {
  return anchorCellForUnit(map, unitId) === cell;
}

export function isFormationCell(value: unknown): value is FormationCellId {
  return typeof value === "string" && FORMATION_CELLS.includes(value as FormationCellId);
}

function clampSpan(value: unknown): number {
  if (typeof value !== "number" || !Number.isFinite(value)) return 1;
  return Math.max(1, Math.min(3, Math.floor(value)));
}

function cellToGrid(cell: FormationCellId): { row: number; col: number } | null {
  if (!isFormationCell(cell)) return null;
  const row = cell.charCodeAt(0) - 65;
  const col = Number(cell[1]) - 1;
  if (!Number.isFinite(row) || !Number.isFinite(col)) return null;
  if (row < 0 || row > 2 || col < 0 || col > 2) return null;
  return { row, col };
}

function gridToCell(row: number, col: number): FormationCellId | null {
  if (row < 0 || row > 2 || col < 0 || col > 2) return null;
  return `${String.fromCharCode(65 + row)}${col + 1}` as FormationCellId;
}

function compareCells(a: FormationCellId, b: FormationCellId): number {
  const rowCmp = a.charCodeAt(0) - b.charCodeAt(0);
  if (rowCmp !== 0) return rowCmp;
  return Number(a[1]) - Number(b[1]);
}

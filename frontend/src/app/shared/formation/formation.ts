export const FORMATION_CELLS = ['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3'] as const;

export type FormationAssignment = {
  cell: string;
  unit_instance_id: string | null;
};

export function buildFormationGrid<T>(
  formation: readonly FormationAssignment[] | null | undefined,
  entryById: ReadonlyMap<string, T>,
  cells: readonly string[] = FORMATION_CELLS,
): Array<{ cell: string; unitId: string | null; entry: T | null }> {
  const formationAssignments = new Map(
    (formation ?? []).map((entry) => [entry.cell, entry.unit_instance_id]),
  );

  return cells.map((cell) => {
    const unitId = formationAssignments.get(cell) ?? null;
    return {
      cell,
      unitId,
      entry: unitId ? entryById.get(unitId) ?? null : null,
    };
  });
}

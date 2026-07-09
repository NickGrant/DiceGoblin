import { buildFormationGrid, FORMATION_CELLS } from './formation';

describe('formation helpers', () => {
  it('exposes the canonical 3x3 formation cells in order', () => {
    expect(FORMATION_CELLS).toEqual(['A1', 'A2', 'A3', 'B1', 'B2', 'B3', 'C1', 'C2', 'C3']);
  });

  it('builds a formation grid from assignments and an entry map', () => {
    const grid = buildFormationGrid(
      [
        { cell: 'A1', unit_instance_id: 'u1' },
        { cell: 'B2', unit_instance_id: 'u2' },
      ],
      new Map([
        ['u1', { name: 'Fang' }],
        ['u2', { name: 'Moss' }],
      ]),
    );

    expect(grid.find((cell) => cell.cell === 'A1')).toEqual({
      cell: 'A1',
      unitId: 'u1',
      entry: { name: 'Fang' },
    });
    expect(grid.find((cell) => cell.cell === 'B2')).toEqual({
      cell: 'B2',
      unitId: 'u2',
      entry: { name: 'Moss' },
    });
    expect(grid.find((cell) => cell.cell === 'C3')).toEqual({
      cell: 'C3',
      unitId: null,
      entry: null,
    });
  });
});

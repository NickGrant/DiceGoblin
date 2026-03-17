export function computeGridVisibleCapacity(params: {
  height: number;
  columns?: number;
  gapY?: number;
  cardHeight: number;
}): number {
  const columns = Math.max(1, params.columns ?? 3);
  const gapY = params.gapY ?? 10;
  const usableHeight = Math.max(0, params.height);
  const rowHeight = Math.max(1, params.cardHeight);
  const rows = Math.max(1, Math.floor((usableHeight + gapY) / (rowHeight + gapY)));
  return rows * columns;
}

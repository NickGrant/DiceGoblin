import { Bounds, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';

export interface EconomyLayout {
  readonly mode: RuntimeViewportSnapshot['layoutClass'];
  readonly header: Bounds;
  readonly back: Bounds;
  readonly wallet: Bounds;
  readonly content: Bounds;
  readonly action: Bounds;
}

const box = (x: number, y: number, width: number, height: number): Bounds =>
  ({ x, y, width, height, right: x + width, bottom: y + height });

export function createEconomyLayout(snapshot: RuntimeViewportSnapshot): EconomyLayout {
  const margin = snapshot.layoutClass === 'compact' ? 22 : snapshot.layoutClass === 'wide' ? 60 : 44;
  const width = snapshot.safeBounds.width - margin * 2;
  const left = snapshot.safeBounds.x + margin;
  const top = snapshot.safeBounds.y + margin;
  const headerHeight = snapshot.layoutClass === 'compact' ? 106 : 92;
  return {
    mode: snapshot.layoutClass,
    header: box(left, top, width, headerHeight),
    back: box(left, top, snapshot.layoutClass === 'compact' ? 210 : 160, snapshot.layoutClass === 'compact' ? 88 : 52),
    wallet: box(left + width - 240, top, 240, snapshot.layoutClass === 'compact' ? 88 : 52),
    content: box(left, top + headerHeight + 18, width,
      Math.max(180, snapshot.safeBounds.bottom - margin - top - headerHeight - 18)),
    action: box(left + width - (snapshot.layoutClass === 'compact' ? 310 : 250),
      snapshot.safeBounds.bottom - margin - (snapshot.layoutClass === 'compact' ? 76 : 58),
      snapshot.layoutClass === 'compact' ? 310 : 250, snapshot.layoutClass === 'compact' ? 76 : 58),
  };
}

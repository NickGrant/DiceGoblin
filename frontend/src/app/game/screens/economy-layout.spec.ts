import { calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { createEconomyLayout } from './economy-layout';

describe('Package 7 economy surfaces layout', () => {
  it('keeps Shop and Supplies regions within Compact, Standard, Wide, and safe-inset bounds', () => {
    const measurements = [
      { cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 } },
      { cssWidth: 1600, cssHeight: 900, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 } },
      { cssWidth: 2560, cssHeight: 1080, safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 } },
      { cssWidth: 844, cssHeight: 390, safeInsetsCss: { top: 9, right: 31, bottom: 17, left: 41 } },
    ];
    for (const measurement of measurements) {
      const snapshot = calculateRuntimeViewport({ ...measurement, coarsePointer: true, noHover: true });
      const layout = createEconomyLayout(snapshot);
      for (const region of [layout.header, layout.back, layout.wallet, layout.content, layout.action]) {
        expect(region.x).toBeGreaterThanOrEqual(snapshot.safeBounds.x);
        expect(region.y).toBeGreaterThanOrEqual(snapshot.safeBounds.y);
        expect(region.right).toBeLessThanOrEqual(snapshot.safeBounds.right);
        expect(region.bottom).toBeLessThanOrEqual(snapshot.safeBounds.bottom);
      }
    }
  });
});

import { calculateRuntimeViewport } from '../runtime/runtime-viewport';
import { createOnboardingLayout } from './onboarding-screen';

describe('Mystic Cave onboarding layout', () => {
  for (const [width, height, expected] of [
    [844, 390, 'compact'], [1600, 900, 'standard'], [2560, 1080, 'wide'], [390, 844, 'compact'],
  ] as const) {
    it(`keeps the panel and action inside the safe viewport at ${width}x${height}`, () => {
      const viewport = calculateRuntimeViewport({ cssWidth: width, cssHeight: height,
        safeInsetsCss: { top: 0, right: 0, bottom: 0, left: 0 },
        coarsePointer: width < 1000, noHover: width < 1000 });
      expect(viewport.layoutClass).toBe(expected);
      expect(viewport.portraitGateActive).toBe(width === 390);
      const layout = createOnboardingLayout(viewport);
      expect(layout.panel.x).toBeGreaterThanOrEqual(viewport.safeBounds.x);
      expect(layout.panel.y).toBeGreaterThanOrEqual(viewport.safeBounds.y);
      expect(layout.panel.right).toBeLessThanOrEqual(viewport.safeBounds.right);
      expect(layout.panel.bottom).toBeLessThanOrEqual(viewport.safeBounds.bottom);
      expect(layout.action.x).toBeGreaterThanOrEqual(layout.panel.x);
      expect(layout.action.right).toBeLessThanOrEqual(layout.panel.right);
      expect(layout.action.bottom).toBeLessThanOrEqual(layout.panel.bottom);
      expect(layout.content.bottom).toBeLessThan(layout.action.y);
    });
  }
});

import {
  COMPACT_MAX_CSS_HEIGHT,
  COMPACT_MIN_SAFE_LOGICAL_WIDTH,
  RuntimeViewport,
  RuntimeViewportEnvironment,
  ViewportMeasurement,
  WIDE_MIN_CSS_HEIGHT,
  WIDE_MIN_SAFE_LOGICAL_WIDTH,
  calculateRuntimeViewport,
} from './runtime-viewport';

const ZERO_INSETS = { top: 0, right: 0, bottom: 0, left: 0 };

function measurement(
  cssWidth: number,
  cssHeight: number,
  overrides: Partial<ViewportMeasurement> = {},
): ViewportMeasurement {
  return {
    cssWidth,
    cssHeight,
    safeInsetsCss: ZERO_INSETS,
    coarsePointer: false,
    noHover: false,
    ...overrides,
  };
}

describe('runtime viewport calculations', () => {
  it('maps the 1600x900 reference viewport to one-to-one logical coordinates', () => {
    const viewport = calculateRuntimeViewport(measurement(1600, 900));

    expect(viewport.gameScale).toBe(1);
    expect(viewport.logicalWidth).toBe(1600);
    expect(viewport.logicalHeight).toBe(900);
    expect(viewport.referenceBounds).toEqual({
      x: 0, y: 0, width: 1600, height: 900, right: 1600, bottom: 900,
    });
  });

  it('preserves logical scale and exposes peripheral width on a wider landscape', () => {
    const viewport = calculateRuntimeViewport(measurement(2560, 1080));

    expect(viewport.gameScale).toBe(1.2);
    expect(viewport.logicalHeight).toBe(900);
    expect(viewport.logicalWidth).toBeCloseTo(2133.333, 3);
    expect(viewport.referenceBounds.x).toBeCloseTo(266.667, 3);
  });

  it('uses the minimum supported logical width when a viewport is narrower than 4:3', () => {
    const viewport = calculateRuntimeViewport(measurement(600, 900));

    expect(viewport.gameScale).toBe(0.5);
    expect(viewport.logicalWidth).toBe(1200);
    expect(viewport.logicalHeight).toBe(1800);
  });

  it('classifies a low-height wide-aspect phone as Compact', () => {
    const viewport = calculateRuntimeViewport(
      measurement(844, 390, { coarsePointer: true, noHover: true }),
    );

    expect(viewport.logicalWidth).toBeGreaterThan(WIDE_MIN_SAFE_LOGICAL_WIDTH);
    expect(viewport.layoutClass).toBe('compact');
  });

  it('uses exact Compact height and safe-width boundaries deterministically', () => {
    expect(calculateRuntimeViewport(measurement(1200, COMPACT_MAX_CSS_HEIGHT)).layoutClass)
      .toBe('compact');
    expect(calculateRuntimeViewport(measurement(1440, 900)).safeBounds.width)
      .toBe(COMPACT_MIN_SAFE_LOGICAL_WIDTH);
    expect(calculateRuntimeViewport(measurement(1440, 900)).layoutClass).toBe('standard');
    expect(calculateRuntimeViewport(measurement(1439, 900)).layoutClass).toBe('compact');
  });

  it('uses exact Wide width and height boundaries deterministically', () => {
    const exactWideCssWidth = WIDE_MIN_SAFE_LOGICAL_WIDTH * WIDE_MIN_CSS_HEIGHT / 900;
    expect(
      calculateRuntimeViewport(
        measurement(exactWideCssWidth, WIDE_MIN_CSS_HEIGHT),
      ).layoutClass,
    ).toBe('wide');
    expect(
      calculateRuntimeViewport(
        measurement(exactWideCssWidth - 1, WIDE_MIN_CSS_HEIGHT),
      ).layoutClass,
    ).toBe('standard');
    const belowHeight = WIDE_MIN_CSS_HEIGHT - 1;
    expect(
      calculateRuntimeViewport(
        measurement(WIDE_MIN_SAFE_LOGICAL_WIDTH * belowHeight / 900, belowHeight),
      ).layoutClass,
    ).toBe('standard');
  });

  it('converts CSS safe insets into logical units before calculating safe bounds', () => {
    const viewport = calculateRuntimeViewport(
      measurement(800, 450, {
        safeInsetsCss: { top: 10, right: 20, bottom: 30, left: 40 },
      }),
    );

    expect(viewport.gameScale).toBe(0.5);
    expect(viewport.safeInsets).toEqual({ top: 20, right: 40, bottom: 60, left: 80 });
    expect(viewport.safeBounds).toEqual({
      x: 80, y: 20, width: 1480, height: 820, right: 1560, bottom: 840,
    });
  });

  it('gates a touch-first phone in portrait but not landscape', () => {
    const phonePortrait = calculateRuntimeViewport(
      measurement(390, 844, { coarsePointer: true }),
    );
    const phoneLandscape = calculateRuntimeViewport(
      measurement(844, 390, { coarsePointer: true }),
    );

    expect(phonePortrait.touchFirst).toBeTrue();
    expect(phonePortrait.portraitGateActive).toBeTrue();
    expect(phoneLandscape.portraitGateActive).toBeFalse();
  });

  it('gates a coarse-pointer or no-hover tablet in portrait but not landscape', () => {
    const tabletPortrait = calculateRuntimeViewport(
      measurement(768, 1024, { noHover: true }),
    );
    const tabletLandscape = calculateRuntimeViewport(
      measurement(1024, 768, { coarsePointer: true }),
    );

    expect(tabletPortrait.touchFirst).toBeTrue();
    expect(tabletPortrait.portraitGateActive).toBeTrue();
    expect(tabletLandscape.portraitGateActive).toBeFalse();
  });

  it('does not gate desktop portrait windows with normal pointer and hover capability', () => {
    const tabletSizedDesktop = calculateRuntimeViewport(measurement(768, 1024));
    const largeDesktopPortrait = calculateRuntimeViewport(measurement(1200, 1920));

    expect(tabletSizedDesktop.touchFirst).toBeFalse();
    expect(tabletSizedDesktop.portraitGateActive).toBeFalse();
    expect(largeDesktopPortrait.portraitGateActive).toBeFalse();
  });
});

describe('RuntimeViewport lifecycle', () => {
  it('notifies subscribers on resize and removes environment listeners on destroy', () => {
    let current = measurement(1600, 900);
    let onResize: (() => void) | null = null;
    const stop = jasmine.createSpy('stop');
    const environment: RuntimeViewportEnvironment = {
      measure: () => current,
      listen: (_parent, listener) => {
        onResize = listener;
        return stop;
      },
    };
    const viewport = new RuntimeViewport(environment);
    const listener = jasmine.createSpy('listener');
    viewport.mount(document.createElement('div'));
    viewport.subscribe(listener);

    current = measurement(844, 390, { coarsePointer: true });
    (onResize as unknown as () => void)();

    expect(listener).toHaveBeenCalledOnceWith(jasmine.objectContaining({
      cssWidth: 844,
      layoutClass: 'compact',
    }));
    viewport.destroy();
    expect(stop).toHaveBeenCalledTimes(1);
  });
});

export const REFERENCE_WIDTH = 1600;
export const REFERENCE_HEIGHT = 900;
export const MINIMUM_LOGICAL_WIDTH = 1200;
export const COMPACT_MAX_CSS_HEIGHT = 599;
export const COMPACT_MIN_SAFE_LOGICAL_WIDTH = 1440;
export const WIDE_MIN_SAFE_LOGICAL_WIDTH = 1920;
export const WIDE_MIN_CSS_HEIGHT = 720;
export const PHONE_MAX_LARGEST_DIMENSION = 932;

export type ResponsiveLayoutClass = 'compact' | 'standard' | 'wide';

export interface Insets {
  readonly top: number;
  readonly right: number;
  readonly bottom: number;
  readonly left: number;
}

export interface Bounds {
  readonly x: number;
  readonly y: number;
  readonly width: number;
  readonly height: number;
  readonly right: number;
  readonly bottom: number;
}

export interface ViewportMeasurement {
  readonly cssWidth: number;
  readonly cssHeight: number;
  readonly safeInsetsCss: Insets;
  readonly coarsePointer: boolean;
  readonly noHover: boolean;
}

export interface RuntimeViewportSnapshot {
  readonly cssWidth: number;
  readonly cssHeight: number;
  readonly gameScale: number;
  readonly logicalWidth: number;
  readonly logicalHeight: number;
  readonly layoutClass: ResponsiveLayoutClass;
  readonly safeInsets: Insets;
  readonly safeBounds: Bounds;
  readonly referenceBounds: Bounds;
  readonly phoneClass: boolean;
  readonly portraitGateActive: boolean;
}

export interface RuntimeViewportEnvironment {
  measure(parent: HTMLElement): ViewportMeasurement;
  listen(parent: HTMLElement, listener: () => void): () => void;
  destroy?(): void;
}

const ZERO_INSETS: Insets = { top: 0, right: 0, bottom: 0, left: 0 };

function positiveDimension(value: number, fallback: number): number {
  return Number.isFinite(value) && value > 0 ? value : fallback;
}

function nonNegative(value: number): number {
  return Number.isFinite(value) ? Math.max(0, value) : 0;
}

function bounds(x: number, y: number, width: number, height: number): Bounds {
  return { x, y, width, height, right: x + width, bottom: y + height };
}

export function calculateRuntimeViewport(
  measurement: ViewportMeasurement,
): RuntimeViewportSnapshot {
  const cssWidth = positiveDimension(measurement.cssWidth, 1600);
  const cssHeight = positiveDimension(measurement.cssHeight, 900);
  const gameScale = Math.min(
    cssHeight / REFERENCE_HEIGHT,
    cssWidth / MINIMUM_LOGICAL_WIDTH,
  );
  const logicalWidth = cssWidth / gameScale;
  const logicalHeight = cssHeight / gameScale;
  const safeInsets = {
    top: nonNegative(measurement.safeInsetsCss.top) / gameScale,
    right: nonNegative(measurement.safeInsetsCss.right) / gameScale,
    bottom: nonNegative(measurement.safeInsetsCss.bottom) / gameScale,
    left: nonNegative(measurement.safeInsetsCss.left) / gameScale,
  };
  const safeWidth = Math.max(0, logicalWidth - safeInsets.left - safeInsets.right);
  const safeHeight = Math.max(0, logicalHeight - safeInsets.top - safeInsets.bottom);
  const safeBounds = bounds(safeInsets.left, safeInsets.top, safeWidth, safeHeight);
  const largestCssDimension = Math.max(cssWidth, cssHeight);
  const phoneClass =
    (measurement.coarsePointer || measurement.noHover) &&
    largestCssDimension <= PHONE_MAX_LARGEST_DIMENSION;
  const portraitGateActive = phoneClass && cssHeight > cssWidth;

  let layoutClass: ResponsiveLayoutClass = 'standard';
  if (
    cssHeight <= COMPACT_MAX_CSS_HEIGHT ||
    safeWidth < COMPACT_MIN_SAFE_LOGICAL_WIDTH
  ) {
    layoutClass = 'compact';
  } else if (safeWidth >= WIDE_MIN_SAFE_LOGICAL_WIDTH && cssHeight >= WIDE_MIN_CSS_HEIGHT) {
    layoutClass = 'wide';
  }

  return {
    cssWidth,
    cssHeight,
    gameScale,
    logicalWidth,
    logicalHeight,
    layoutClass,
    safeInsets,
    safeBounds,
    referenceBounds: bounds(
      (logicalWidth - REFERENCE_WIDTH) / 2,
      (logicalHeight - REFERENCE_HEIGHT) / 2,
      REFERENCE_WIDTH,
      REFERENCE_HEIGHT,
    ),
    phoneClass,
    portraitGateActive,
  };
}

export class BrowserRuntimeViewportEnvironment implements RuntimeViewportEnvironment {
  private safeAreaProbe: HTMLElement | null = null;

  measure(parent: HTMLElement): ViewportMeasurement {
    const rect = parent.getBoundingClientRect();
    const cssWidth = parent.clientWidth || rect.width || window.innerWidth;
    const cssHeight = parent.clientHeight || rect.height || window.innerHeight;
    const probe = this.getSafeAreaProbe(parent);
    const style = window.getComputedStyle(probe);

    return {
      cssWidth,
      cssHeight,
      safeInsetsCss: {
        top: Number.parseFloat(style.paddingTop) || 0,
        right: Number.parseFloat(style.paddingRight) || 0,
        bottom: Number.parseFloat(style.paddingBottom) || 0,
        left: Number.parseFloat(style.paddingLeft) || 0,
      },
      coarsePointer: window.matchMedia('(pointer: coarse)').matches,
      noHover: window.matchMedia('(hover: none)').matches,
    };
  }

  listen(parent: HTMLElement, listener: () => void): () => void {
    window.addEventListener('resize', listener, { passive: true });
    window.addEventListener('orientationchange', listener, { passive: true });
    window.visualViewport?.addEventListener('resize', listener, { passive: true });
    const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(listener);
    observer?.observe(parent);

    return () => {
      window.removeEventListener('resize', listener);
      window.removeEventListener('orientationchange', listener);
      window.visualViewport?.removeEventListener('resize', listener);
      observer?.disconnect();
    };
  }

  destroy(): void {
    this.safeAreaProbe?.remove();
    this.safeAreaProbe = null;
  }

  private getSafeAreaProbe(parent: HTMLElement): HTMLElement {
    if (this.safeAreaProbe?.isConnected) {
      return this.safeAreaProbe;
    }

    const probe = document.createElement('div');
    probe.setAttribute('aria-hidden', 'true');
    Object.assign(probe.style, {
      position: 'absolute',
      visibility: 'hidden',
      pointerEvents: 'none',
      paddingTop: 'env(safe-area-inset-top, 0px)',
      paddingRight: 'env(safe-area-inset-right, 0px)',
      paddingBottom: 'env(safe-area-inset-bottom, 0px)',
      paddingLeft: 'env(safe-area-inset-left, 0px)',
    });
    parent.appendChild(probe);
    this.safeAreaProbe = probe;
    return probe;
  }
}

export type RuntimeViewportListener = (snapshot: RuntimeViewportSnapshot) => void;

/** Runtime-lifetime responsive state shared by scenes and screens. */
export class RuntimeViewport {
  private currentSnapshot = calculateRuntimeViewport({
    cssWidth: 1600,
    cssHeight: 900,
    safeInsetsCss: ZERO_INSETS,
    coarsePointer: false,
    noHover: false,
  });
  private parent: HTMLElement | null = null;
  private stopListening: (() => void) | null = null;
  private readonly listeners = new Set<RuntimeViewportListener>();

  constructor(
    private readonly environment: RuntimeViewportEnvironment =
      new BrowserRuntimeViewportEnvironment(),
  ) {}

  get snapshot(): RuntimeViewportSnapshot {
    return this.currentSnapshot;
  }

  mount(parent: HTMLElement): RuntimeViewportSnapshot {
    if (this.parent) return this.currentSnapshot;

    this.parent = parent;
    this.refresh();
    this.stopListening = this.environment.listen(parent, () => this.refresh());
    return this.currentSnapshot;
  }

  subscribe(listener: RuntimeViewportListener): () => void {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  refresh(): void {
    if (!this.parent) return;
    this.currentSnapshot = calculateRuntimeViewport(this.environment.measure(this.parent));
    for (const listener of this.listeners) listener(this.currentSnapshot);
  }

  destroy(): void {
    this.stopListening?.();
    this.stopListening = null;
    this.listeners.clear();
    this.parent = null;
    this.environment.destroy?.();
  }
}

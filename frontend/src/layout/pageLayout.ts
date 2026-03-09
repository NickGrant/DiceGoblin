import type Phaser from "phaser";

export type LayoutRect = {
  x: number;
  y: number;
  width: number;
  height: number;
};

export type PageLayout = {
  padding: LayoutRect;
  header: LayoutRect;
  bottomStrip: LayoutRect;
  content: LayoutRect;
  buttons: LayoutRect;
};

export const PAGE_LAYOUT = {
  paddingPx: 12,
  topBandHeightPx: 218,
  columnGapPx: 24,
  buttonColumnWidthPx: 616,
  bottomStripHeightPx: 96,
  bottomStripBottomPaddingPx: 10,
  bottomStripTopGapPx: 12,
} as const;

export function getPageLayout(scene: Phaser.Scene): PageLayout {
  return getPageLayoutForSize(scene.scale.width, scene.scale.height);
}

export function getPageLayoutForSize(width: number, height: number): PageLayout {
  const pad = PAGE_LAYOUT.paddingPx;
  const safe: LayoutRect = {
    x: pad,
    y: pad,
    width: Math.max(0, width - pad * 2),
    height: Math.max(0, height - pad * 2),
  };

  const header: LayoutRect = {
    x: 0,
    y: 0,
    width,
    height: PAGE_LAYOUT.topBandHeightPx,
  };

  const bodyY = header.height;
  const reservedBottomHeight =
    PAGE_LAYOUT.bottomStripHeightPx +
    PAGE_LAYOUT.bottomStripBottomPaddingPx +
    PAGE_LAYOUT.bottomStripTopGapPx;
  const bodyHeight = Math.max(0, height - pad - bodyY - reservedBottomHeight);
  const buttonColumnWidth = Math.min(PAGE_LAYOUT.buttonColumnWidthPx, Math.max(0, width - pad * 2));

  const buttons: LayoutRect = {
    x: width - pad - buttonColumnWidth,
    y: bodyY,
    width: buttonColumnWidth,
    height: bodyHeight,
  };

  const content: LayoutRect = {
    x: pad,
    y: bodyY,
    width: Math.max(0, width - pad * 2 - buttonColumnWidth - PAGE_LAYOUT.columnGapPx),
    height: bodyHeight,
  };

  const bottomStrip: LayoutRect = {
    x: pad,
    y: height - PAGE_LAYOUT.bottomStripBottomPaddingPx - PAGE_LAYOUT.bottomStripHeightPx,
    width: Math.max(0, width - pad * 2),
    height: PAGE_LAYOUT.bottomStripHeightPx,
  };

  return {
    padding: safe,
    header,
    bottomStrip,
    content,
    buttons,
  };
}

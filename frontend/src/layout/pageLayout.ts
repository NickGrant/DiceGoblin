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
  homeIcon: LayoutRect;
  hud: LayoutRect;
  content: LayoutRect;
  buttons: LayoutRect;
};

export const PAGE_LAYOUT = {
  paddingPx: 16,
  headerHeightPx: 100,
  columnGapPx: 16,
  buttonColumnWidthPx: 320,
  topIconSizePx: 100,
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
    x: safe.x,
    y: safe.y,
    width: safe.width,
    height: PAGE_LAYOUT.headerHeightPx,
  };

  const bodyY = header.y + header.height + PAGE_LAYOUT.columnGapPx;
  const bodyHeight = Math.max(0, safe.y + safe.height - bodyY);
  const buttonColumnWidth = Math.min(PAGE_LAYOUT.buttonColumnWidthPx, safe.width);

  const buttons: LayoutRect = {
    x: safe.x + safe.width - buttonColumnWidth,
    y: bodyY,
    width: buttonColumnWidth,
    height: bodyHeight,
  };

  const content: LayoutRect = {
    x: safe.x,
    y: bodyY,
    width: Math.max(0, safe.width - buttonColumnWidth - PAGE_LAYOUT.columnGapPx),
    height: bodyHeight,
  };

  return {
    padding: safe,
    header,
    homeIcon: {
      x: header.x,
      y: header.y,
      width: PAGE_LAYOUT.topIconSizePx,
      height: PAGE_LAYOUT.topIconSizePx,
    },
    hud: {
      x: header.x + header.width - buttonColumnWidth,
      y: header.y,
      width: buttonColumnWidth,
      height: header.height,
    },
    content,
    buttons,
  };
}

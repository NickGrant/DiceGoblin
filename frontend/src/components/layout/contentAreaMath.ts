const DEFAULT_TITLE_HEIGHT = 56;
const DEFAULT_MARGIN = 12;

export function resolveContentFrameBodyRect(params: {
  width: number;
  height: number;
  titleHeight?: number;
  marginPx?: number;
  useImageEdgeToEdge?: boolean;
  bodyImageKey?: string;
}): { x: number; y: number; width: number; height: number } {
  const titleHeight = params.titleHeight ?? DEFAULT_TITLE_HEIGHT;
  const marginPx = params.marginPx ?? DEFAULT_MARGIN;
  const useEdgeToEdge = Boolean(params.bodyImageKey) && (params.useImageEdgeToEdge ?? true);

  if (useEdgeToEdge) {
    return {
      x: 0,
      y: titleHeight,
      width: Math.max(0, params.width),
      height: Math.max(0, params.height - titleHeight),
    };
  }

  return {
    x: marginPx,
    y: titleHeight + marginPx,
    width: Math.max(0, params.width - marginPx * 2),
    height: Math.max(0, params.height - titleHeight - marginPx * 2),
  };
}

export type ListContentLayout = {
  contentX: number;
  contentY: number;
  contentWidth: number;
  contentHeight: number;
};

const PAD = 12;
const TITLE_HEIGHT = 24;
const CONTENT_GAP = 8;
const PAGINATION_HEIGHT = 22;

export function resolveListContentLayout(params: {
  width: number;
  height: number;
  hasTitle: boolean;
}): ListContentLayout {
  const titleOffset = params.hasTitle ? TITLE_HEIGHT + CONTENT_GAP : 0;
  return {
    contentX: PAD,
    contentY: PAD + titleOffset,
    contentWidth: Math.max(0, params.width - PAD * 2),
    contentHeight: Math.max(0, params.height - PAD * 2 - titleOffset - PAGINATION_HEIGHT - CONTENT_GAP),
  };
}

export type ListLoadState = "loading" | "ready" | "error";

export type PaginationState = {
  pageIndex: number;
  pageSize: number;
};

export type PaginationResult = {
  totalItems: number;
  totalPages: number;
  pageIndex: number;
  pageSize: number;
  start: number;
  end: number;
  canPrev: boolean;
  canNext: boolean;
};

export function computePagination(totalItems: number, state: PaginationState): PaginationResult {
  const pageSize = Math.max(1, Math.floor(state.pageSize));
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
  const pageIndex = Math.max(0, Math.min(totalPages - 1, Math.floor(state.pageIndex)));
  const start = pageIndex * pageSize;
  const end = Math.min(totalItems, start + pageSize);
  return {
    totalItems,
    totalPages,
    pageIndex,
    pageSize,
    start,
    end,
    canPrev: pageIndex > 0,
    canNext: pageIndex < totalPages - 1,
  };
}

export function deriveListState(loadState: ListLoadState, itemCount: number): "loading" | "error" | "empty" | "ready" {
  if (loadState === "loading") return "loading";
  if (loadState === "error") return "error";
  if (itemCount <= 0) return "empty";
  return "ready";
}

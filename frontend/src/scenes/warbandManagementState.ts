import { adaptUnitRecords } from "../adapters/profileViewModels";
import type { ProfileResponse, TeamRecord, UnitRecord } from "../types/ApiResponse";

export type WarbandColumns = {
  leftX: number;
  rightX: number;
  columnWidth: number;
  splitGap: number;
};

export type WarbandHubState = {
  units: UnitRecord[];
  squads: TeamRecord[];
};

export function deriveWarbandHubState(profile: ProfileResponse): WarbandHubState {
  if (!profile.ok) {
    throw new Error(profile.error.message);
  }

  return {
    units: adaptUnitRecords(profile.data.units ?? []),
    squads: (profile.data.squads ?? []) as TeamRecord[],
  };
}

export function computeWarbandColumns(contentX: number, contentWidth: number, splitGap = 24): WarbandColumns {
  const columnWidth = Math.floor((contentWidth - splitGap) / 2);
  return {
    leftX: contentX,
    rightX: contentX + columnWidth + splitGap,
    columnWidth,
    splitGap,
  };
}

export function normalizeNewSquadName(rawName: string | null | undefined): string | null {
  const normalized = (rawName ?? "").trim();
  return normalized.length > 0 ? normalized : null;
}

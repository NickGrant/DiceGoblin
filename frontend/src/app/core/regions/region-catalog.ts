import { CurrentRunRecord, ProfileData } from '../models/api.models';

const REGION_COMPLETION_UNLOCK_MAP: Record<string, string> = {
  mystic_cave: 'the_farm',
  the_farm: 'mountains',
  mountains: 'swamps',
};

const REGION_THEME_FALLBACK_BY_SLUG: Record<string, string> = {
  mystic_cave: 'mystic_cave',
  the_farm: 'farm',
  mountains: 'mountain',
  swamps: 'swamp',
};

export const REGION_SUMMARY_BY_SLUG: Record<string, string> = {
  mystic_cave: 'A quiet two-node introduction with The Whim.',
  the_farm: 'Combat, loot, rest, boss, then exit.',
  mountains: 'Branching climbs with tougher fights and a boss reward that unlocks the swamps.',
  swamps: 'Branching marsh paths with frogman encounters, rest stops, and a final boss.',
};

export function normalizeRegionSlug(value: string): string {
  return value.trim().toLowerCase().replace(/\s+/g, '_');
}

export function resolveCompletedRegionSlugs(profileData: ProfileData | null): string[] {
  const regions = profileData?.regions ?? [];
  if (regions.length > 0) {
    return regions.filter((region) => region.is_completed).map((region) => region.slug);
  }

  const unlockedRegions = new Set((profileData?.region_unlocks ?? []).map((entry) => entry.region_slug));
  return Object.entries(REGION_COMPLETION_UNLOCK_MAP)
    .filter(([, unlockedRegionSlug]) => unlockedRegions.has(unlockedRegionSlug))
    .map(([completedRegionSlug]) => completedRegionSlug);
}

export function resolveRegionTheme(
  regionSlug: string | null | undefined,
  regionTheme?: string | null,
): string | null {
  if (typeof regionTheme === 'string' && regionTheme.trim() !== '') {
    return regionTheme;
  }

  if (!regionSlug) {
    return null;
  }

  return REGION_THEME_FALLBACK_BY_SLUG[regionSlug] ?? null;
}

export function resolveRegionBackgroundUrl(
  regionSlug: string | null | undefined,
  regionTheme?: string | null,
): string | null {
  const theme = resolveRegionTheme(regionSlug, regionTheme);
  return theme ? `/assets/ui/biome/${theme}.png` : null;
}

export function resolveRunRegionBackgroundUrl(run: CurrentRunRecord | null): string | null {
  if (!run) {
    return null;
  }

  return resolveRegionBackgroundUrl(run.region_slug ?? null, run.region_theme ?? null);
}

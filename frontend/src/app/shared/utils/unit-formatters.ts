import { AbilityCatalogEntry } from '../../core/models/api.models';

const ROMAN_NUMERALS: Array<{ value: number; symbol: string }> = [
  { value: 1000, symbol: 'M' },
  { value: 900, symbol: 'CM' },
  { value: 500, symbol: 'D' },
  { value: 400, symbol: 'CD' },
  { value: 100, symbol: 'C' },
  { value: 90, symbol: 'XC' },
  { value: 50, symbol: 'L' },
  { value: 40, symbol: 'XL' },
  { value: 10, symbol: 'X' },
  { value: 9, symbol: 'IX' },
  { value: 5, symbol: 'V' },
  { value: 4, symbol: 'IV' },
  { value: 1, symbol: 'I' },
];

export function toRomanNumeral(value: number | null | undefined): string {
  const normalized = Math.max(1, Math.floor(value || 1));
  let remaining = normalized;
  let result = '';

  for (const numeral of ROMAN_NUMERALS) {
    while (remaining >= numeral.value) {
      result += numeral.symbol;
      remaining -= numeral.value;
    }
  }

  return result;
}

export function formatTier(tier: number | null | undefined): string | null {
  return tier ? toRomanNumeral(tier) : null;
}

export function humanizeAbilityId(abilityId: string): string {
  return abilityId
    .split('_')
    .filter((segment) => segment.length)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(' ');
}

export function formatSpliceVariantLabel(
  spliceVariantName: string | null | undefined,
  spliceVariantSlug: string | null | undefined,
): string {
  const name = (spliceVariantName ?? '').trim();
  if (name.length > 0) {
    return name;
  }

  const slug = (spliceVariantSlug ?? '').trim();
  if (slug.length === 0 || slug === 'basic_goblin') {
    return 'Basic Goblin';
  }

  return slug
    .split('_')
    .filter((segment) => segment.length)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join('-')
    .replace(/-Splice$/, '-Spliced');
}

export function normalizeAbilityId(abilityId: unknown): string | null {
  const normalized = typeof abilityId === 'string' ? abilityId.trim() : '';
  return normalized.length > 0 ? normalized : null;
}

export function resolveAbilityDisplayName(
  abilityId: string | null | undefined,
  abilityCatalog?: ReadonlyMap<string, AbilityCatalogEntry> | null,
): string {
  const normalized = normalizeAbilityId(abilityId);
  if (!normalized) {
    return 'Unknown ability';
  }

  return abilityCatalog?.get(normalized)?.display_name ?? humanizeAbilityId(normalized);
}

export function summarizeAbilityNames(
  abilityIds: readonly string[] | null | undefined,
  abilityCatalog?: ReadonlyMap<string, AbilityCatalogEntry> | null,
): string {
  if (!abilityIds?.length) {
    return 'None';
  }

  return abilityIds.map((abilityId) => resolveAbilityDisplayName(abilityId, abilityCatalog)).join(', ');
}

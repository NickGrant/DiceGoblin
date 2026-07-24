import { formatSpliceVariantLabel, formatTier, humanizeAbilityId, resolveAbilityDisplayName, summarizeAbilityNames, toRomanNumeral } from './unit-formatters';

describe('unit formatters', () => {
  it('formats tiers as roman numerals', () => {
    expect(formatTier(1)).toBe('I');
    expect(formatTier(4)).toBe('IV');
    expect(formatTier(7)).toBe('VII');
    expect(formatTier(null)).toBeNull();
  });

  it('humanizes ability identifiers', () => {
    expect(humanizeAbilityId('sleep_hex')).toBe('Sleep Hex');
  });

  it('formats splice variant labels from names or slugs', () => {
    expect(formatSpliceVariantLabel('Rat-Spliced', 'rat_splice')).toBe('Rat-Spliced');
    expect(formatSpliceVariantLabel(null, 'toad_splice')).toBe('Toad-Spliced');
    expect(formatSpliceVariantLabel(null, 'basic_goblin')).toBe('Basic Goblin');
  });

  it('prefers catalog names when resolving ability labels', () => {
    const abilityCatalog = new Map([
      ['sleep_hex', { ability_id: 'sleep_hex', display_name: 'Sleep Hex', type: 'active', short_desc: '', icon_key: '', tags: [], default_params: {}, order: 1 }],
    ]);

    expect(resolveAbilityDisplayName('sleep_hex', abilityCatalog)).toBe('Sleep Hex');
    expect(resolveAbilityDisplayName('heavy_strike', abilityCatalog)).toBe('Heavy Strike');
  });

  it('summarizes ability names using shared label resolution', () => {
    const abilityCatalog = new Map([
      ['finisher', { ability_id: 'finisher', display_name: 'Finisher', type: 'passive', short_desc: '', icon_key: '', tags: [], default_params: {}, order: 1 }],
    ]);

    expect(summarizeAbilityNames(['finisher', 'sleep_hex'], abilityCatalog)).toBe('Finisher, Sleep Hex');
    expect(summarizeAbilityNames([])).toBe('None');
  });

  it('converts larger numbers to roman numerals', () => {
    expect(toRomanNumeral(12)).toBe('XII');
  });
});

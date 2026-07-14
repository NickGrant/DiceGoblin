import { resolveUnitImageSlug, resolveUnitImageUrl } from './unit-art';

describe('unit-art helpers', () => {
  it('resolves numbered enemy labels to the base portrait slug', () => {
    expect(resolveUnitImageSlug('Kobold Shieldbearer #2')).toBe('kobold_shieldbearer');
    expect(resolveUnitImageSlug('Kobold Shieldbearer 2')).toBe('kobold_shieldbearer');
    expect(resolveUnitImageUrl('Kobold Shieldbearer #2')).toBe('/assets/ui/units/animated/kobold/shieldbearer/frame_0.png');
  });

  it('still resolves canonical slugs unchanged', () => {
    expect(resolveUnitImageSlug('kobold_shieldbearer')).toBe('kobold_shieldbearer');
    expect(resolveUnitImageUrl('kobold_shieldbearer')).toBe('/assets/ui/units/animated/kobold/shieldbearer/frame_0.png');
  });
});

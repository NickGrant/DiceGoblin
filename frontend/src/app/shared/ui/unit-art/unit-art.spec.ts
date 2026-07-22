import {
  resolveUnitAnimationFrameUrls,
  resolveUnitImageSlug,
  resolveUnitImageUrl,
  resolveUnitSilhouetteUrl,
  resolveUnitThumbnailUrl,
} from './unit-art';

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

  it('resolves tier one unit thumbnails from canonical slugs and names', () => {
    expect(resolveUnitThumbnailUrl('frontline_bruiser_t1')).toBe('/assets/ui/units/thumbnails/goblin/bruiser.png');
    expect(resolveUnitThumbnailUrl('Bannerbearer')).toBe('/assets/ui/units/thumbnails/goblin/bannerbearer.png');
  });

  it('resolves allied unit static art to the class-specific animation frame', () => {
    expect(resolveUnitImageUrl('frontline_bruiser_t1')).toBe('/assets/ui/units/animated/goblin/base/bruiser/frame_0.png');
    expect(resolveUnitImageUrl('support_banner_t1')).toBe('/assets/ui/units/animated/goblin/base/bannerbearer/frame_0.png');
    expect(resolveUnitAnimationFrameUrls('control_saboteur_t1')).toEqual([
      '/assets/ui/units/animated/goblin/base/saboteur/frame_0.png',
      '/assets/ui/units/animated/goblin/base/saboteur/frame_1.png',
      '/assets/ui/units/animated/goblin/base/saboteur/frame_2.png',
      '/assets/ui/units/animated/goblin/base/saboteur/frame_3.png',
    ]);
  });

  it('resolves farm enemies to their copied animation frames', () => {
    expect(resolveUnitImageUrl('mudking')).toBe('/assets/ui/units/animated/pig/mudking/frame_0.png');
    expect(resolveUnitAnimationFrameUrls('mudslinger').length).toBe(4);
  });

  it('resolves promoted unit thumbnails when available', () => {
    expect(resolveUnitThumbnailUrl('frontline_bruiser_t2')).toBe('/assets/ui/units/thumbnails/goblin/enforcer.png');
    expect(resolveUnitThumbnailUrl('frontline_bruiser_t3')).toBe('/assets/ui/units/thumbnails/goblin/juggernaut.png');
    expect(resolveUnitThumbnailUrl('frontline_guardian_t2')).toBe('/assets/ui/units/thumbnails/goblin/bulwark.png');
    expect(resolveUnitThumbnailUrl('frontline_shieldbreaker_t2')).toBe('/assets/ui/units/thumbnails/goblin/shieldbreaker.png');
    expect(resolveUnitThumbnailUrl('frontline_pit_fighter_t2')).toBe('/assets/ui/units/thumbnails/goblin/pit_fighter.png');
    expect(resolveUnitThumbnailUrl('control_plaguehand_t2')).toBe('/assets/ui/units/thumbnails/goblin/plaguehand.png');
    expect(resolveUnitThumbnailUrl('support_banner_t2')).toBe('/assets/ui/units/thumbnails/goblin/warcaller.png');
    expect(resolveUnitThumbnailUrl('support_mascot_t2')).toBe('/assets/ui/units/thumbnails/goblin/mascot.png');
  });

  it('returns null for unit types without goblin thumbnails', () => {
    expect(resolveUnitThumbnailUrl('kobold_shieldbearer')).toBeNull();
  });

  it('resolves the generic unit silhouette', () => {
    expect(resolveUnitSilhouetteUrl()).toBe('/assets/ui/units/thumbnails/goblin/silhouette.png');
  });
});

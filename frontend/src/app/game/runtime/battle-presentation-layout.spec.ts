import { battleArtAssetPath, battleColumnX } from './battle-presentation-layout';

describe('battle presentation layout', () => {
  it('places player back, middle, and front columns progressively toward the center', () => {
    expect([0, 1, 2].map((column) => battleColumnX('player', column, 400, 100))).toEqual([300, 400, 500]);
  });

  it('mirrors enemy back, middle, and front columns so front remains nearest the center', () => {
    expect([0, 1, 2].map((column) => battleColumnX('enemy', column, 1200, 100))).toEqual([1300, 1200, 1100]);
  });

  it('selects supported assets only from the persisted historical art key', () => {
    const mutableCurrentContent = { artKey: 'goblin_mascot' };
    expect(battleArtAssetPath('goblin_bruiser')).toBe('assets/ui/units/goblin_bruiser.png');
    mutableCurrentContent.artKey = 'goblin_saboteur';
    expect(battleArtAssetPath('goblin_bruiser')).toBe('assets/ui/units/goblin_bruiser.png');
    expect(battleArtAssetPath('enemy_mudwrestler')).toBe('assets/ui/units/pig_mudwrestler.png');
    expect(battleArtAssetPath('enemy_mudslinger')).toBe('assets/ui/units/pig_mudslinger.png');
    expect(battleArtAssetPath('enemy_mudking')).toBe('assets/ui/units/pig_mudking.png');
    expect(battleArtAssetPath('retired.unknown')).toBeNull();
  });
});

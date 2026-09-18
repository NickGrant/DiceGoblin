import { BattleSide } from './battle-playback-contracts';

const directUnitArtKeys = new Set([
  'goblin_bruiser', 'goblin_enforcer', 'goblin_pit_fighter', 'goblin_juggernaut',
  'goblin_guardian', 'goblin_bulwark', 'goblin_shieldbreaker', 'goblin_ironwall',
  'goblin_marksman', 'goblin_deadeye', 'goblin_trapper', 'goblin_sharpshot',
  'goblin_bannerbearer', 'goblin_warcaller', 'goblin_mascot', 'goblin_saboteur',
  'goblin_trickshot', 'goblin_plaguehand',
]);

const farmEnemyArtPaths: Readonly<Record<string, string>> = Object.freeze({
  enemy_mudwrestler: 'assets/ui/units/pig_mudwrestler.png',
  enemy_mudslinger: 'assets/ui/units/pig_mudslinger.png',
  enemy_mudking: 'assets/ui/units/pig_mudking.png',
});

export function battleColumnX(side: BattleSide, column: number, sideCenter: number, spacing: number): number {
  if (!Number.isInteger(column) || column < 0 || column > 2 || !Number.isFinite(sideCenter)
    || !Number.isFinite(spacing) || spacing <= 0) throw new Error('Battle presentation position is invalid.');
  const towardCenter = side === 'player' ? 1 : -1;
  return sideCenter + (column - 1) * spacing * towardCenter;
}

export function battleArtAssetPath(artKey: string): string | null {
  if (directUnitArtKeys.has(artKey)) return `assets/ui/units/${artKey}.png`;
  return farmEnemyArtPaths[artKey] ?? null;
}

export function battleArtTextureKey(artKey: string): string { return `battle-art:${artKey}`; }

export function supportedBattleArtAssets(): readonly Readonly<{ artKey: string; path: string }>[] {
  return Object.freeze([
    ...[...directUnitArtKeys].map((artKey) => Object.freeze({ artKey, path: `assets/ui/units/${artKey}.png` })),
    ...Object.entries(farmEnemyArtPaths).map(([artKey, path]) => Object.freeze({ artKey, path })),
  ]);
}

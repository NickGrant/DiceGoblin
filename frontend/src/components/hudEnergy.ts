export function resolveEnergyTierIcon(current: number, max: number): string {
  const safeMax = Math.max(1, max);
  const ratio = Math.max(0, Math.min(1, current / safeMax));
  const pct = ratio * 100;
  if (pct >= 100) return "icon_energy";
  if (pct >= 75) return "icon_energy_75";
  if (pct >= 50) return "icon_energy_50";
  if (pct >= 25) return "icon_energy_25";
  return "icon_energy_0";
}

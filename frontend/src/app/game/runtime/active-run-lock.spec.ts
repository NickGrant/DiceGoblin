import { activeRunLock } from './active-run-lock';
import type { GameBootstrapData } from './game-store';

describe('active run presentation lock', () => {
  const base = {
    account: { id: '1', display_name: 'Goblin', role: 'user' },
    player: { teeth: 0, raw_chaos: 0, player_revision: 7, energy: { current: 50, normal_max: 50, regeneration_per_hour: 12, regeneration_interval_seconds: 300, last_regeneration_at: '2026-01-01T00:00:00Z', next_regeneration_at: null, fully_regenerated_at: null } },
    session: { authenticated: true as const, csrf_token: 'csrf' }, server_time: '2026-01-01T00:00:00Z', content_revision: 'a'.repeat(64), progression: { unlock_ids: [] },
    active_squad: { id: '31', name: 'Raiders', is_active: true as const,
      formation: ['11', null, '12', null, null, null, null, null, null],
      units: [
        { id: '11', display_name: 'Grub', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' as const },
        { id: '12', display_name: 'Mug', unit_type_id: 'unit_type.bruiser', kin_id: 'kin.goblin', level: 1, xp: 0, lifecycle_status: 'active' as const },
      ] },
    active_run: { id: '41', region_id: 'region.the_farm', squad_id: '31', status: 'active' as const },
  } satisfies GameBootstrapData;

  it('derives squad and participating unit IDs entirely from bootstrap formation', () => {
    const lock = activeRunLock(base);
    expect(lock?.squadId).toBe('31');
    expect([...lock!.unitIds]).toEqual(['11', '12']);
    expect(lock!.unitIds.has('99')).toBeFalse();
  });

  it('unlocks on reconciled null run and rejects contradictory active authority', () => {
    expect(activeRunLock({ ...base, active_run: null })).toBeNull();
    expect(() => activeRunLock({ ...base, active_run: { ...base.active_run, squad_id: '99' } })).toThrow();
  });
});

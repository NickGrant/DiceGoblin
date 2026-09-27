import { parseDiceSalvageEnvelope, parseDiceSellEnvelope } from './dice-lifecycle-contracts';

describe('dice lifecycle contracts', () => {
  it('parses exact request-bound sell and salvage receipts', () => {
    expect(parseDiceSellEnvelope({ ok: true, data: { dice_id: '21', lifecycle_status: 'sold',
      teeth_awarded: 19, teeth: 31, player_revision: 4 } }, '21')).toEqual({
      diceId: '21', lifecycleStatus: 'sold', teethAwarded: 19, teeth: 31, playerRevision: 4,
    });
    expect(parseDiceSalvageEnvelope({ ok: true, data: { dice_id: '22', lifecycle_status: 'salvaged',
      raw_chaos_awarded: 7, raw_chaos: 9, player_revision: 5 } }, '22')).toEqual({
      diceId: '22', lifecycleStatus: 'salvaged', rawChaosAwarded: 7, rawChaos: 9, playerRevision: 5,
    });
  });

  it('rejects wrong identities, statuses, operation fields, and expanded results', () => {
    const sell = { ok: true, data: { dice_id: '21', lifecycle_status: 'sold',
      teeth_awarded: 6, teeth: 6, player_revision: 2 } };
    expect(() => parseDiceSellEnvelope(sell, '22')).toThrow();
    expect(() => parseDiceSellEnvelope({ ok: true, data: { ...sell.data, lifecycle_status: 'salvaged' } }, '21')).toThrow();
    expect(() => parseDiceSellEnvelope({ ok: true, data: { dice_id: '21', lifecycle_status: 'sold',
      raw_chaos_awarded: 2, raw_chaos: 2, player_revision: 2 } }, '21')).toThrow();
    expect(() => parseDiceSellEnvelope({ ok: true, data: { ...sell.data, extra: true } }, '21')).toThrow();
  });

  it('rejects unsafe, nonpositive, or incoherent numeric results and noncanonical requests', () => {
    const result = (award: number, balance: number, revision: number) => ({ ok: true, data: {
      dice_id: '21', lifecycle_status: 'salvaged', raw_chaos_awarded: award, raw_chaos: balance,
      player_revision: revision,
    } });
    expect(() => parseDiceSalvageEnvelope(result(0, 0, 2), '21')).toThrow();
    expect(() => parseDiceSalvageEnvelope(result(3, 2, 2), '21')).toThrow();
    expect(() => parseDiceSalvageEnvelope(result(3, 3, Number.MAX_SAFE_INTEGER + 1), '21')).toThrow();
    expect(() => parseDiceSalvageEnvelope(result(3, 3, 2), '021')).toThrow();
  });
});

import { ClientContentRegistry } from './client-content-registry';
import { AcademyContractError, parseAcademyCatalogEnvelope, parseAcademyUpgradeEnvelope } from './academy-contracts';
import { RuntimeApiClient } from './runtime-api-client';

describe('Academy read contract', () => {
  const content = new ClientContentRegistry({
    revision: 'a'.repeat(64),
    content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: {}, unit_types: {}, abilities: {},
      dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, items: {}, shop_offers: {},
      unit_promotions: {}, academy_upgrades: {
        'academy_upgrade.one': { id: 'academy_upgrade.one', display_name: 'One', description: 'First.', category: 'energy' },
        'academy_upgrade.two': { id: 'academy_upgrade.two', display_name: 'Two', description: 'Second.', category: 'dice' },
      },
    },
  });
  const row = (id: string, owned = false, available = true) => ({
    upgrade_id: id, price: { currency_id: 'raw_chaos', amount: 5 }, owned, available,
  });
  const envelope = (upgrades: unknown) => ({ ok: true, data: { raw_chaos: 2, player_revision: 3, upgrades } });

  it('reconciles exact ordered authored identities and keeps affordability separate from availability', () => {
    const result = parseAcademyCatalogEnvelope(envelope([
      row('academy_upgrade.one'), row('academy_upgrade.two', true, false),
    ]), content);
    expect(result.rawChaos).toBe(2);
    expect(result.playerRevision).toBe(3);
    expect(result.upgrades[0].available).toBeTrue();
    expect(result.upgrades[1].owned).toBeTrue();
  });

  it('rejects malformed, missing, duplicate, and unprojected upgrades', () => {
    for (const invalid of [
      [row('academy_upgrade.one')],
      [row('academy_upgrade.one'), row('academy_upgrade.one')],
      [row('academy_upgrade.two'), row('academy_upgrade.one')],
      [row('academy_upgrade.one'), row('academy_upgrade.unknown')],
      [{ ...row('academy_upgrade.one'), price: { currency_id: 'teeth', amount: 5 } }, row('academy_upgrade.two')],
      [{ ...row('academy_upgrade.one'), owned: true }, row('academy_upgrade.two')],
    ]) {
      expect(() => parseAcademyCatalogEnvelope(envelope(invalid), content)).toThrowError(AcademyContractError);
    }
  });

  it('fetches the authenticated read route without a CSRF header', async () => {
    const fetchRequest = jasmine.createSpy('fetchRequest').and.resolveTo(new Response(JSON.stringify(envelope([
      row('academy_upgrade.one'), row('academy_upgrade.two', false, false),
    ])), { status: 200 }));
    const client = new RuntimeApiClient(fetchRequest, 'https://game.test');
    const result = await client.getAcademy(content);
    expect(result.upgrades.length).toBe(2);
    expect(fetchRequest).toHaveBeenCalledOnceWith('https://game.test/api/v1/academy',
      jasmine.objectContaining({ method: 'GET' }));
    const options = fetchRequest.calls.mostRecent().args[1] as RequestInit;
    expect(options.headers).not.toEqual(jasmine.objectContaining({ 'X-CSRF-Token': jasmine.anything() }));
  });
});

describe('Academy upgrade mutation contract', () => {
  const request = { upgrade_id: 'academy_upgrade.energy_max_75', expected_price: { currency_id: 'raw_chaos' as const, amount: 5 } };
  const data = { upgrade_id: request.upgrade_id,
    spend: { currency_id: 'raw_chaos', amount: 5, balance_before: 10, balance_after: 5 },
    grant: { unlock_id: 'unlock.capability.energy_max_75' }, energy: null, player_revision: 8 };
  const response = (value: unknown) => ({ ok: true, data: value });

  it('accepts exact nullable and authoritative Energy consequences', () => {
    expect(parseAcademyUpgradeEnvelope(response(data), request).energy).toBeNull();
    const energy = { current: 50, normal_max: 75, regeneration_per_hour: 12,
      regeneration_interval_seconds: 300, last_regeneration_at: '2026-09-28T12:00:00Z',
      next_regeneration_at: '2026-09-28T12:05:00Z', fully_regenerated_at: '2026-09-28T14:05:00Z' };
    expect(parseAcademyUpgradeEnvelope(response({ ...data, energy }), request).energy?.normalMax).toBe(75);
  });

  it('rejects malformed identity, spend, grant, revision, Energy, and envelope fields', () => {
    for (const invalid of [
      { ...data, upgrade_id: 'academy_upgrade.other' },
      { ...data, spend: { ...data.spend, currency_id: 'teeth' } },
      { ...data, spend: { ...data.spend, amount: 6 } },
      { ...data, spend: { ...data.spend, balance_after: 6 } },
      { ...data, grant: { unlock_id: 'bad' } },
      { ...data, grant: { ...data.grant, extra: true } },
      { ...data, player_revision: Number.MAX_SAFE_INTEGER + 1 },
      { ...data, energy: {} },
      { ...data, extra: true },
    ]) expect(() => parseAcademyUpgradeEnvelope(response(invalid), request)).toThrowError(AcademyContractError);
    expect(() => parseAcademyUpgradeEnvelope({ ok: true, data, extra: true }, request)).toThrowError(AcademyContractError);
  });

  it('posts the exact request with CSRF/idempotency and preserves API errors', async () => {
    const fetchRequest = jasmine.createSpy('fetchRequest').and.resolveTo(new Response(JSON.stringify(response(data)), { status: 200 }));
    const result = await new RuntimeApiClient(fetchRequest, '/root').upgradeAcademy(request, 'csrf', 'academy:upgrade:12345678');
    expect(result.grant.unlockId).toBe('unlock.capability.energy_max_75');
    const [url, init] = fetchRequest.calls.mostRecent().args;
    expect(url).toBe('/root/api/v1/academy/upgrade');
    expect(init?.method).toBe('POST');
    expect(init?.headers).toEqual(jasmine.objectContaining({ 'X-CSRF-Token': 'csrf', 'Idempotency-Key': 'academy:upgrade:12345678' }));
    expect(JSON.parse(init?.body as string)).toEqual(request);
    await expectAsync(new RuntimeApiClient(fetchRequest, '/root').upgradeAcademy(
      { ...request, extra: true } as typeof request, 'csrf', 'academy:upgrade:12345678',
    )).toBeRejectedWith(jasmine.objectContaining({ kind: 'malformed-response' }));
    fetchRequest.and.resolveTo(new Response(JSON.stringify({ ok: false, error: { code: 'academy_upgrade_owned' } }), { status: 409 }));
    await expectAsync(new RuntimeApiClient(fetchRequest, '/root').upgradeAcademy(request, 'csrf', 'academy:upgrade:12345678'))
      .toBeRejectedWith(jasmine.objectContaining({ status: 409, code: 'academy_upgrade_owned' }));
  });
});

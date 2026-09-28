import { ClientContentRegistry } from './client-content-registry';
import { AcademyContractError, parseAcademyCatalogEnvelope } from './academy-contracts';
import { RuntimeApiClient } from './runtime-api-client';

describe('Academy read contract', () => {
  const content = new ClientContentRegistry({
    revision: 'a'.repeat(64),
    content: {
      gameplay: { run_energy_cost: 10 }, regions: {}, kin: {}, unit_types: {}, abilities: {},
      dice_materials: {}, dice_aspects: {}, dice_profiles: {}, run_node_types: {}, items: {}, shop_offers: {},
      academy_upgrades: {
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

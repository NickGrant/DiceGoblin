import {
  ClientContentError,
  ClientContentLoader,
  ClientContentRegistry,
} from './client-content-registry';
import { RuntimeFetch } from './runtime-api-client';

describe('ClientContentRegistry', () => {
  const revision = 'a'.repeat(64);

  it('loads the generated projection and indexes public definitions by stable ID', async () => {
    const projection = {
      revision,
      content: {
        regions: {
          'region.the_farm': {
            id: 'region.the_farm',
            display_name: 'The Farm',
            art_key: 'farm',
          },
        },
      },
    };
    const fetchRequest = jasmine.createSpy<RuntimeFetch>('fetchRequest').and.resolveTo(
      new Response(JSON.stringify(projection), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    );
    const loader = new ClientContentLoader(fetchRequest, 'https://game.test/game-content.json');

    const registry = new ClientContentRegistry(await loader.loadProjection());

    expect(fetchRequest).toHaveBeenCalledOnceWith(
      'https://game.test/game-content.json',
      jasmine.objectContaining({ method: 'GET' }),
    );
    expect(registry.revision).toBe(revision);
    expect(registry.get('region.the_farm')).toEqual(projection.content.regions['region.the_farm']);
    expect(registry.has('region.missing')).toBeFalse();
  });

  it('rejects malformed or mismatched stable-ID definitions', () => {
    expect(
      () =>
        new ClientContentRegistry({
          revision,
          content: {
            regions: {
              'region.the_farm': {
                id: 'region.wrong',
                display_name: 'The Farm',
                art_key: 'farm',
              },
            },
          },
        }),
    ).toThrowError(ClientContentError);
  });
});

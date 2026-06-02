import { TestBed } from '@angular/core/testing';
import { ApiHttpService } from '../api-http/api-http.service';
import { AbilityCatalogService } from './ability-catalog.service';

describe('AbilityCatalogService', () => {
  let service: AbilityCatalogService;
  let apiHttp: jasmine.SpyObj<ApiHttpService>;

  beforeEach(() => {
    apiHttp = jasmine.createSpyObj<ApiHttpService>('ApiHttpService', ['get']);

    TestBed.configureTestingModule({
      providers: [
        AbilityCatalogService,
        { provide: ApiHttpService, useValue: apiHttp },
      ],
    });

    service = TestBed.inject(AbilityCatalogService);
  });

  it('loads and caches the ability catalog', async () => {
    apiHttp.get.and.resolveTo({
      ok: true,
      data: {
        catalog_version: 1,
        abilities: [
          {
            ability_id: 'heavy_strike',
            type: 'active',
            display_name: 'Heavy Strike',
            short_desc: 'Hit hard.',
            icon_key: 'heavy_strike',
            tags: [],
            default_params: {},
            order: 1,
            speed: 4,
            dice_cost: 2,
            default_target: 'enemy_front',
          },
        ],
      },
    } as const);

    await service.load();
    await service.load();

    expect(apiHttp.get).toHaveBeenCalledTimes(1);
    expect(service.abilities()[0]?.display_name).toBe('Heavy Strike');
    expect(service.abilityMap().get('heavy_strike')?.dice_cost).toBe(2);
  });
});

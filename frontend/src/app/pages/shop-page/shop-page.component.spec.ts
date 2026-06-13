import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { ShopPageComponent } from './shop-page.component';
import { ShopService } from '../../core/services/shop/shop.service';

class ShopServiceStub {
  getCatalog = jasmine.createSpy('getCatalog').and.resolveTo({
    ok: true,
    data: {
      currency_soft: 20,
      basic_dice: [
        {
          product_id: 'dice-1',
          label: 'Starter d6',
          rarity: 'common',
          sides: 6,
          cost: 10,
        },
      ],
      basic_units: [
        {
          product_id: 'unit-1',
          unit_type_slug: 'goblin_bruiser',
          name: 'Goblin Bruiser',
          role: 'Frontline',
          cost: 15,
        },
      ],
      daily_deal: {
        product_id: 'deal-1',
        shop_date: '2026-05-31',
        sides: 8,
        rarity: 'rare',
        cost: 20,
        is_purchased: false,
        affix: {
          slug: 'sharp',
          name: 'Sharp',
          description: 'Adds extra damage.',
          rarity: 'rare',
          value: 4,
        },
      },
      feature_unlocks: [
        {
          product_id: 'academy',
          name: 'Academy',
          description: 'Unlock promotions and unit-type research for your warband.',
          cost: 250,
          is_unlocked: false,
        },
      ],
    },
  });
  purchase = jasmine.createSpy('purchase').and.resolveTo({ ok: true });
}

describe('ShopPageComponent', () => {
  async function createComponent() {
    await TestBed.configureTestingModule({
      imports: [ShopPageComponent],
      providers: [provideRouter([]), { provide: ShopService, useClass: ShopServiceStub }],
    }).compileComponents();

    const fixture = TestBed.createComponent(ShopPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();
    return fixture;
  }

  it('loads catalog data on startup and evaluates affordability', async () => {
    const fixture = await createComponent();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    expect(component.loading()).toBeFalse();
    expect(component.catalog()?.currency_soft).toBe(20);
    expect(component.canAfford(10)).toBeTrue();
    expect(component.canAfford(30)).toBeFalse();
    expect(compiled.textContent).toContain('Goblin Bruiser');
    expect(compiled.textContent).toContain('Sharp');
  });

  it('shows Academy under feature unlocks and purchases it from that tab', async () => {
    const fixture = await createComponent();

    const component = fixture.componentInstance;
    const shopService = TestBed.inject(ShopService) as unknown as ShopServiceStub;

    component.activeTab.set('feature_unlocks');
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Camp Upgrades');
    expect(compiled.textContent).toContain('Academy');
    expect(compiled.textContent).toContain('250 Teeth');

    await component.purchase('feature_unlock', 'academy');
    expect(shopService.purchase).toHaveBeenCalledWith('feature_unlock', 'academy');
  });
});

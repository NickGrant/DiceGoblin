import { TestBed } from '@angular/core/testing';
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
    },
  });
  purchase = jasmine.createSpy('purchase').and.resolveTo({ ok: true });
}

describe('ShopPageComponent', () => {
  it('loads catalog data on startup and evaluates affordability', async () => {
    await TestBed.configureTestingModule({
      imports: [ShopPageComponent],
      providers: [{ provide: ShopService, useClass: ShopServiceStub }],
    }).compileComponents();

    const fixture = TestBed.createComponent(ShopPageComponent);
    await fixture.whenStable();
    fixture.detectChanges();

    const component = fixture.componentInstance;
    const compiled = fixture.nativeElement as HTMLElement;
    expect(component.loading()).toBeFalse();
    expect(component.catalog()?.currency_soft).toBe(20);
    expect(component.canAfford(10)).toBeTrue();
    expect(component.canAfford(30)).toBeFalse();
    expect(compiled.textContent).toContain('d6 · Common');
    expect(compiled.textContent).not.toContain('Starter d6');
    expect(compiled.textContent).toContain('Goblin Bruiser');
    expect(compiled.textContent).toContain('Sharp');
  });
});

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
          total_attack: 7,
          total_defense: 5,
          total_precision: 6,
          total_resolve: 4,
          max_hp: 18,
        },
      ],
      daily_deal: {
        product_id: 'daily_deal_1',
        shop_date: '2026-05-31',
        slot: 1,
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
      daily_deals: [
        {
          product_id: 'daily_deal_1',
          shop_date: '2026-05-31',
          slot: 1,
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
        {
          product_id: 'daily_deal_2',
          shop_date: '2026-05-31',
          slot: 2,
          sides: 10,
          rarity: 'rare',
          cost: 25,
          is_purchased: false,
          affix: {
            slug: 'heavy',
            name: 'Heavy',
            description: 'Hits harder.',
            rarity: 'rare',
            value: 5,
          },
        },
      ],
      feature_unlocks: [
        {
          product_id: 'academy',
          name: 'Academy',
          description: 'Unlock promotions and unit-type research for your warband.',
          cost: 250,
          is_unlocked: true,
          category: 'feature',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'bigger_squad',
          name: 'Bigger Squad',
          description: 'Raise your squad size cap from 4 units to 6.',
          cost: 500,
          is_unlocked: false,
          category: 'squad_upgrade',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'biggerest_squad',
          name: 'Biggerest Squad',
          description: 'Raise your squad size cap from 6 units to the full 9-slot formation.',
          cost: 1000,
          is_unlocked: false,
          category: 'squad_upgrade',
          requires_unlock_key: 'bigger_squad',
          is_available: false,
        },
        {
          product_id: 'shop_discount',
          name: 'Coupon Book',
          description: 'Make all future shop purchases cost 10% less.',
          cost: 500,
          is_unlocked: false,
          category: 'economy_upgrade',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'sell_bonus',
          name: 'Sharp Dealer',
          description: 'Make dice sales pay out 10% more teeth.',
          cost: 500,
          is_unlocked: false,
          category: 'economy_upgrade',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'market_mastery',
          name: 'Market Mastery',
          description: 'Improve both shop discounts and sale payouts to 20% once your traders are fully trained.',
          cost: 1000,
          is_unlocked: false,
          category: 'economy_upgrade',
          requires_unlock_key: 'shop_discount_and_sell_bonus',
          is_available: false,
        },
        {
          product_id: 'second_daily_deal',
          name: 'Second Deal',
          description: 'Add a second daily deal slot so the shop offers two rotating featured dice each day.',
          cost: 500,
          is_unlocked: false,
          category: 'feature',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'energy_cap_75',
          name: 'Deep Pantry',
          description: 'Raise your max energy from 50 to 75.',
          cost: 750,
          is_unlocked: false,
          category: 'energy_upgrade',
          requires_unlock_key: null,
          is_available: true,
        },
        {
          product_id: 'energy_cap_100',
          name: 'Bottomless Pantry',
          description: 'Raise your max energy from 75 to 100.',
          cost: 1250,
          is_unlocked: false,
          category: 'energy_upgrade',
          requires_unlock_key: 'energy_cap_75',
          is_available: false,
        },
        {
          product_id: 'explode_d4s',
          name: 'Loaded Caltrops',
          description: 'All d4s gain a one-time explode when they roll max during combat.',
          cost: 2000,
          is_unlocked: false,
          category: 'dice_upgrade',
          requires_unlock_key: null,
          is_available: true,
        },
      ],
    },
  });
  purchase = jasmine.createSpy('purchase').and.callFake((itemType: string, productId: string) => Promise.resolve({
    ok: true,
    data: {
      item_type: itemType,
      product_id: productId,
      cost: 15,
      currency_soft: 5,
      purchase: itemType === 'basic_unit'
        ? {
            unit_instance_id: 'unit-instance-1',
            unit_type_slug: 'goblin_bruiser',
            splice_variant_slug: 'rat_splice',
            tier: 1,
            level: 1,
          }
        : {
            unlock_namespace: 'feature',
            unlock_key: productId,
          },
    },
  }));
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
    expect(compiled.textContent).toContain('PRC');
    expect(compiled.textContent).toContain('RES');
    expect(compiled.textContent).toContain('Sharp');
    expect(compiled.textContent).toContain('Heavy');
    expect(compiled.textContent).not.toContain('Deal 2:');
    expect(compiled.querySelector('.shop-unit__cost')?.textContent).toContain('15');
    expect(compiled.querySelector('.shop-page__wallet')).toBeNull();
  });

  it('shows Academy under feature unlocks and purchases it from that tab', async () => {
    const fixture = await createComponent();

    const component = fixture.componentInstance;
    const shopService = TestBed.inject(ShopService) as unknown as ShopServiceStub;

    component.activeTab.set('feature_unlocks');
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Tooth Market');
    expect(compiled.textContent).toContain('Academy');
    expect(compiled.textContent).toContain('Bigger Squad');
    expect(compiled.textContent).toContain('Biggerest Squad');
    expect(compiled.textContent).toContain('Coupon Book');
    expect(compiled.textContent).toContain('Sharp Dealer');
    expect(compiled.textContent).toContain('Market Mastery');
    expect(compiled.textContent).toContain('Second Deal');
    expect(compiled.textContent).toContain('Deep Pantry');
    expect(compiled.textContent).toContain('Bottomless Pantry');
    expect(compiled.textContent).toContain('Loaded Caltrops');
    expect(compiled.textContent).toContain('Requires Bigger Squad');
    expect(compiled.textContent).toContain('Requires Coupon Book + Sharp Dealer');
    expect(compiled.textContent).toContain('Requires Deep Pantry');
    expect(compiled.textContent).toContain('500');

    const unlockedCard = compiled.querySelector('.feature-unlock-card--unlocked');
    expect(unlockedCard?.textContent).toContain('Academy');
    const featureEntries = Array.from(compiled.querySelectorAll('.feature-unlock-card'));
    expect(featureEntries[2].getAttribute('style')).toContain('--depth: 1');
    expect(featureEntries[2].classList).toContain('feature-unlock-card--unavailable');

    await component.purchase('feature_unlock', 'academy');
    expect(shopService.purchase).toHaveBeenCalledWith('feature_unlock', 'academy');
  });

  it('announces the recruited kin after buying a basic unit', async () => {
    const fixture = await createComponent();

    const component = fixture.componentInstance;
    await component.purchase('basic_unit', 'unit-1');

    expect(component.message()).toBe('Recruit joined: Rat Kin.');
  });
});

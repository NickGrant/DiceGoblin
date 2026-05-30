import { TestBed } from '@angular/core/testing';
import { ShopPageComponent } from './shop-page.component';
import { ShopService } from '../../core/services/shop/shop.service';

class ShopServiceStub {
  getCatalog = jasmine.createSpy('getCatalog').and.resolveTo({
    ok: true,
    data: {
      currency_soft: 20,
      basic_dice: [],
      basic_units: [],
      daily_deal: null,
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
    expect(component.loading()).toBeFalse();
    expect(component.catalog()?.currency_soft).toBe(20);
    expect(component.canAfford(10)).toBeTrue();
    expect(component.canAfford(30)).toBeFalse();
  });
});

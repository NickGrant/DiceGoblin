import { Component, computed, inject, signal } from '@angular/core';
import {
  ShopCatalogData,
  ShopDailyDeal,
  ShopDiceItem,
  ShopFeatureUnlockItem,
  ShopUnitItem,
} from '../../core/models/api.models';
import { ShopService } from '../../core/services/shop/shop.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ObjectGridComponent } from '../../shared/ui/object-grid/object-grid.component';
import {
  ShopDiceGridObjectComponent,
  ShopDiceGridObjectRecord,
} from '../../shared/ui/shop-dice-grid-object/shop-dice-grid-object.component';
import {
  ShopUnitGridObjectComponent,
  ShopUnitGridObjectRecord,
} from '../../shared/ui/shop-unit-grid-object/shop-unit-grid-object.component';
import { TabStripComponent, TabStripItem } from '../../shared/ui/tab-strip/tab-strip.component';

@Component({
  selector: 'app-shop-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, ObjectGridComponent, TabStripComponent],
  templateUrl: './shop-page.component.html',
  styleUrl: './shop-page.component.scss',
})
export class ShopPageComponent {
  private readonly shopService = inject(ShopService);
  private static readonly FEATURE_UNLOCK_LABELS: Record<string, string> = {
    academy: 'Feature Unlock',
    bigger_squad: 'Squad Upgrade',
    biggerest_squad: 'Squad Upgrade',
    shop_discount: 'Economy Upgrade',
    sell_bonus: 'Economy Upgrade',
    market_mastery: 'Economy Upgrade',
    second_daily_deal: 'Feature Unlock',
  };
  private static readonly FEATURE_UNLOCK_REQUIREMENT_LABELS: Record<string, string> = {
    bigger_squad: 'No prerequisite',
    biggerest_squad: 'Requires Bigger Squad',
    market_mastery: 'Requires Coupon Book + Sharp Dealer',
  };

  readonly activeTab = signal<'supplies' | 'feature_unlocks'>('supplies');
  readonly tabs: ReadonlyArray<TabStripItem> = [
    { id: 'supplies', label: 'Supplies', kicker: 'Stock' },
    { id: 'feature_unlocks', label: 'Feature Unlocks', kicker: 'Progression' },
  ];
  readonly catalog = signal<ShopCatalogData | null>(null);
  readonly loading = signal(true);
  readonly busyKey = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly shopDiceObjectComponent = ShopDiceGridObjectComponent;
  readonly shopUnitObjectComponent = ShopUnitGridObjectComponent;
  readonly basicDiceGridObjects = computed(() =>
    (this.catalog()?.basic_dice ?? []).map((item) => this.mapBasicDiceItem(item)),
  );
  readonly basicUnitGridObjects = computed(() =>
    (this.catalog()?.basic_units ?? []).map((item) => this.mapBasicUnitItem(item)),
  );
  readonly featureUnlockItems = computed(() => this.catalog()?.feature_unlocks ?? []);
  readonly dailyDealGridObjects = computed(() => {
    const deals = this.catalog()?.daily_deals;
    if (Array.isArray(deals) && deals.length > 0) {
      return deals.map((deal) => this.mapDailyDealItem(deal));
    }

    const deal = this.catalog()?.daily_deal;
    return deal ? [this.mapDailyDealItem(deal)] : [];
  });

  constructor() {
    void this.loadCatalog();
  }

  async loadCatalog(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.shopService.getCatalog();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.catalog.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load shop.');
    } finally {
      this.loading.set(false);
    }
  }

  async purchase(
    itemType: 'basic_unit' | 'basic_dice' | 'daily_deal' | 'feature_unlock',
    productId = '',
  ): Promise<void> {
    this.busyKey.set(`${itemType}:${productId}`);
    this.error.set(null);
    this.message.set(null);

    try {
      const response = await this.shopService.purchase(itemType, productId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Purchase complete.');
      await this.loadCatalog();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to complete purchase.');
    } finally {
      this.busyKey.set(null);
    }
  }

  canAfford(cost: number): boolean {
    return (this.catalog()?.currency_soft ?? 0) >= cost;
  }

  private mapBasicDiceItem(item: ShopDiceItem): ShopDiceGridObjectRecord {
    return {
      id: item.product_id,
      label: '',
      rarity: item.rarity,
      sides: item.sides,
      cost: item.cost,
      detailLines: [],
    };
  }

  private mapDailyDealItem(item: ShopDailyDeal): ShopDiceGridObjectRecord {
    return {
      id: item.product_id,
      label: item.slot && item.slot > 1 ? `Deal ${item.slot}: ${item.affix.name}` : item.affix.name,
      rarity: item.rarity,
      sides: item.sides,
      cost: item.cost,
      isPurchased: item.is_purchased,
      detailLines: [item.affix.description],
    };
  }

  private mapBasicUnitItem(item: ShopUnitItem): ShopUnitGridObjectRecord {
    return {
      id: item.product_id,
      name: item.name,
      role: item.role,
      cost: item.cost,
      tierLabel: 'Tier 1',
    };
  }

  featureUnlockBusyKey(productId: string): string {
    return `feature_unlock:${productId}`;
  }

  featureUnlockCta(item: ShopFeatureUnlockItem): string {
    if (this.busyKey() === this.featureUnlockBusyKey(item.product_id)) {
      return 'Unlocking...';
    }

    if (!this.isFeatureUnlockAvailable(item)) {
      return 'Locked';
    }

    return item.is_unlocked ? 'Unlocked' : 'Unlock';
  }

  featureUnlockEyebrow(item: ShopFeatureUnlockItem): string {
    return ShopPageComponent.FEATURE_UNLOCK_LABELS[item.product_id] ?? 'Feature Unlock';
  }

  featureUnlockRequirementLabel(item: ShopFeatureUnlockItem): string {
    return ShopPageComponent.FEATURE_UNLOCK_REQUIREMENT_LABELS[item.product_id] ?? 'Locked';
  }

  isFeatureUnlockAvailable(item: ShopFeatureUnlockItem): boolean {
    return (item.is_available ?? true) || item.is_unlocked;
  }

  isFeatureUnlockDisabled(item: ShopFeatureUnlockItem): boolean {
    return (
      item.is_unlocked
      || !this.isFeatureUnlockAvailable(item)
      || this.busyKey() === this.featureUnlockBusyKey(item.product_id)
      || !this.canAfford(item.cost)
    );
  }

  selectTab(tabId: string): void {
    this.activeTab.set(tabId === 'feature_unlocks' ? 'feature_unlocks' : 'supplies');
  }
}

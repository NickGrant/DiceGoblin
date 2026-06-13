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
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';
import { ObjectGridComponent } from '../../shared/ui/object-grid/object-grid.component';
import {
  ShopDiceGridObjectComponent,
  ShopDiceGridObjectRecord,
} from '../../shared/ui/shop-dice-grid-object/shop-dice-grid-object.component';
import {
  ShopUnitGridObjectComponent,
  ShopUnitGridObjectRecord,
} from '../../shared/ui/shop-unit-grid-object/shop-unit-grid-object.component';

@Component({
  selector: 'app-shop-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, ObjectGridComponent],
  templateUrl: './shop-page.component.html',
  styleUrl: './shop-page.component.scss',
})
export class ShopPageComponent {
  private readonly shopService = inject(ShopService);

  readonly activeTab = signal<'supplies' | 'feature_unlocks'>('supplies');
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
      label: item.affix.name,
      rarity: item.rarity,
      sides: item.sides,
      cost: item.cost,
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

    return item.is_unlocked ? 'Unlocked' : 'Unlock';
  }
}

import { Component, computed, inject, signal } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
  ShopCatalogData,
  ShopDailyDeal,
  ShopDiceItem,
  ShopFeatureUnlockItem,
  ShopUnitItem,
  UnitRecord,
} from '../../core/models/api.models';
import { ShopService } from '../../core/services/shop/shop.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { FeatureUnlockCategoryLabel, resolveFeatureUnlockCategory } from '../../core/feature-unlocks/feature-unlock-categories';
import { UnitBarComponent } from '../../shared/ui/unit-bar/unit-bar.component';
import { resolveFeatureUnlockIcon } from '../../shared/ui/category-icons/category-icons';

@Component({
  selector: 'app-shop-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, FontAwesomeModule, PageFrameComponent, UnitBarComponent],
  templateUrl: './shop-page.component.html',
  styleUrl: './shop-page.component.scss',
})
export class ShopPageComponent {
  private readonly shopService = inject(ShopService);
  private static readonly FEATURE_UNLOCK_REQUIREMENT_LABELS: Record<string, string> = {
    bigger_squad: 'No prerequisite',
    biggerest_squad: 'Requires Bigger Squad',
    market_mastery: 'Requires Coupon Book + Sharp Dealer',
    energy_cap_100: 'Requires Deep Pantry',
  };
  private static readonly FEATURE_UNLOCK_DEPTHS: Record<string, number> = {
    biggerest_squad: 1,
    market_mastery: 1,
    energy_cap_100: 1,
  };

  readonly activeTab = signal<'supplies' | 'feature_unlocks'>('supplies');
  readonly catalog = signal<ShopCatalogData | null>(null);
  readonly loading = signal(true);
  readonly busyKey = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly basicDiceGridObjects = computed(() => this.catalog()?.basic_dice ?? []);
  readonly basicUnitGridObjects = computed(() => this.catalog()?.basic_units ?? []);
  readonly featureUnlockItems = computed(() => this.catalog()?.feature_unlocks ?? []);
  readonly dailyDealGridObjects = computed(() => {
    const deals = this.catalog()?.daily_deals;
    if (Array.isArray(deals) && deals.length > 0) {
      return deals;
    }

    const deal = this.catalog()?.daily_deal;
    return deal ? [deal] : [];
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

  diceArtUrl(item: Pick<ShopDiceItem, 'rarity' | 'sides'>): string {
    return resolveDiceArtStyles(item.rarity, item.sides, 124).imageUrl;
  }

  diceTitle(item: Pick<ShopDiceItem, 'rarity' | 'sides'>): string {
    return `${item.rarity} d${item.sides}`;
  }

  unitBarRecord(item: ShopUnitItem): UnitRecord {
    return {
      id: item.product_id,
      name: item.name,
      level: 1,
      tier: 1,
      unit_type_slug: item.unit_type_slug,
      unit_type_name: item.name,
      current_hp: 10,
      max_hp: 10,
      xp: 0,
      xp_to_next_level: 100,
      is_mastered: false,
    };
  }

  dailyDealLabel(item: ShopDailyDeal): string {
    return item.slot && item.slot > 1 ? `Deal ${item.slot}: ${item.affix.name}` : item.affix.name;
  }

  purchaseBusy(itemType: 'basic_unit' | 'basic_dice' | 'daily_deal', productId: string): boolean {
    return this.busyKey() === `${itemType}:${productId}`;
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

  featureUnlockEyebrow(item: ShopFeatureUnlockItem): FeatureUnlockCategoryLabel {
    return resolveFeatureUnlockCategory(item.product_id);
  }

  featureUnlockIcon(item: ShopFeatureUnlockItem): IconDefinition {
    return resolveFeatureUnlockIcon(this.featureUnlockEyebrow(item));
  }

  featureUnlockRequirementLabel(item: ShopFeatureUnlockItem): string {
    return ShopPageComponent.FEATURE_UNLOCK_REQUIREMENT_LABELS[item.product_id] ?? 'Locked';
  }

  featureUnlockDepth(item: ShopFeatureUnlockItem): number {
    return ShopPageComponent.FEATURE_UNLOCK_DEPTHS[item.product_id] ?? 0;
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

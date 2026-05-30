import { NgFor, NgIf } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ShopCatalogData } from '../../core/models/api.models';
import { ShopService } from '../../core/services/shop/shop.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-shop-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, NgFor, NgIf],
  templateUrl: './shop-page.component.html',
  styleUrl: './shop-page.component.scss',
})
export class ShopPageComponent {
  private readonly shopService = inject(ShopService);

  readonly catalog = signal<ShopCatalogData | null>(null);
  readonly loading = signal(true);
  readonly busyKey = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

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

  async purchase(itemType: 'basic_unit' | 'basic_dice' | 'daily_deal', productId = ''): Promise<void> {
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
}


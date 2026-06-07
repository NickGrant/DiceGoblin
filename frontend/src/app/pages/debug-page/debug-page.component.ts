import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DebugCatalogData, DebugOwnedUnitRecord } from '../../core/models/api.models';
import { DebugService } from '../../core/services/debug/debug.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-debug-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, FormsModule],
  templateUrl: './debug-page.component.html',
  styleUrl: './debug-page.component.scss',
})
export class DebugPageComponent {
  private readonly debugService = inject(DebugService);

  readonly catalog = signal<DebugCatalogData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  currencySoft = 100;
  selectedUnitSlug = '';
  selectedSides = 6;
  selectedRarity = 'common';
  selectedRegionItem = '';
  selectedOwnedUnitId = '';
  selectedOwnedUnitLevel = 1;

  constructor() {
    void this.loadCatalog();
  }

  async loadCatalog(): Promise<void> {
    this.loading.set(true);
    this.error.set(null);
    try {
      const response = await this.debugService.getCatalog();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.catalog.set(response.data);
      this.selectedUnitSlug ||= response.data.unit_types[0]?.slug ?? '';
      this.selectedRegionItem ||= response.data.region_items[0]?.slug ?? '';
      this.syncOwnedUnitSelection(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load debug catalog.');
    } finally {
      this.loading.set(false);
    }
  }

  async grantCurrency(): Promise<void> {
    await this.runMutation(async () => this.debugService.grantCurrency(this.currencySoft));
  }

  async grantUnit(): Promise<void> {
    await this.runMutation(async () => this.debugService.grantUnit(this.selectedUnitSlug, 1));
  }

  async grantDie(): Promise<void> {
    await this.runMutation(async () => this.debugService.grantDie(this.selectedSides, this.selectedRarity, 1));
  }

  async grantRegionItem(): Promise<void> {
    await this.runMutation(async () => this.debugService.grantRegionItem(this.selectedRegionItem, 1));
  }

  async setUnitLevel(): Promise<void> {
    if (!this.selectedOwnedUnitId) {
      this.error.set('Choose a unit first.');
      return;
    }

    await this.runMutation(
      async () => this.debugService.setUnitLevel(this.selectedOwnedUnitId, this.selectedOwnedUnitLevel),
      'Unit level updated.',
      true,
    );
  }

  async resetAccount(): Promise<void> {
    await this.runMutation(async () => this.debugService.resetAccount(), 'Account reset.');
  }

  private async runMutation(
    callback: () => Promise<{ ok: boolean; error?: { message: string } }>,
    successMessage = 'Mutation complete.',
    reloadCatalog = false,
  ): Promise<void> {
    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);

    try {
      const response = await callback();
      if (!response.ok) {
        this.error.set(response.error?.message ?? 'Mutation failed.');
        return;
      }
      if (reloadCatalog) {
        await this.loadCatalog();
      }
      this.message.set(successMessage);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Mutation failed.');
    } finally {
      this.busy.set(false);
    }
  }

  syncOwnedUnitLevel(): void {
    const unit = this.findOwnedUnit(this.selectedOwnedUnitId);
    if (!unit) {
      this.selectedOwnedUnitLevel = 1;
      return;
    }

    this.selectedOwnedUnitLevel = unit.level;
  }

  private syncOwnedUnitSelection(catalog: DebugCatalogData): void {
    const ownedUnits = catalog.owned_units ?? [];
    if (!ownedUnits.length) {
      this.selectedOwnedUnitId = '';
      this.selectedOwnedUnitLevel = 1;
      return;
    }

    const current = ownedUnits.find((unit) => unit.id === this.selectedOwnedUnitId) ?? ownedUnits[0];
    this.selectedOwnedUnitId = current.id;
    this.selectedOwnedUnitLevel = current.level;
  }

  private findOwnedUnit(unitId: string): DebugOwnedUnitRecord | undefined {
    return this.catalog()?.owned_units.find((unit) => unit.id === unitId);
  }
}


import { TitleCasePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { DiceRecord, ItemRecord } from '../../core/models/api.models';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { ConfirmModalComponent } from '../../shared/ui/confirm-modal/confirm-modal.component';
import { FocusLayoutComponent } from '../../shared/ui/focus-layout/focus-layout.component';
import {
  buildDiceRarityOptions,
  buildDiceSizeOptions,
  DiceEquipFilter,
  DiceSortOption,
  filterAndSortDice,
} from '../../shared/ui/dice-display/dice-display.utils';

@Component({
  selector: 'app-dice-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, FormsModule, RouterLink, TitleCasePipe, ConfirmModalComponent, FocusLayoutComponent],
  templateUrl: './dice-page.component.html',
  styleUrl: './dice-page.component.scss',
})
export class DicePageComponent {
  private readonly diceService = inject(DiceService);
  private readonly sessionService = inject(SessionService);
  private readonly router = inject(Router);

  readonly profileData = this.sessionService.profileData;
  readonly wrongMachineUnlocked = this.sessionService.wrongMachineUnlocked;
  readonly dice = computed(() => this.sessionService.dice());
  readonly inventoryTab = signal<'dice' | 'consumables'>('dice');
  readonly busyDiceId = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly selectedSize = signal<number | null>(null);
  readonly selectedRarity = signal<string | null>(null);
  readonly selectedEquipFilter = signal<DiceEquipFilter>('all');
  readonly selectedSort = signal<DiceSortOption>('size-asc');
  readonly page = signal(1);
  readonly consumablePage = signal(1);
  readonly hoveredDiceId = signal<string | null>(null);
  readonly hoveredItemSlug = signal<string | null>(null);
  readonly pendingSellDiceId = signal<string | null>(null);
  readonly pendingSalvageDiceId = signal<string | null>(null);
  readonly pageSize = 12;
  readonly consumablePageSize = 8;
  readonly consumables = computed(() =>
    (this.profileData()?.items ?? [])
      .filter((item) => item.category === 'consumable')
      .sort((left, right) => left.name.localeCompare(right.name)),
  );
  readonly sizeOptions = computed(() => buildDiceSizeOptions(this.dice()));
  readonly rarityOptions = computed(() => buildDiceRarityOptions(this.dice()));
  readonly pageSubtitle = computed(() =>
    this.wrongMachineUnlocked()
      ? 'Review owned dice, consumables, and supplies for the next run.'
      : 'Review owned dice and the supplies gathered on the road.',
  );
  readonly filteredDice = computed(() =>
    filterAndSortDice(this.dice(), {
      selectedSize: this.selectedSize(),
      selectedRarity: this.selectedRarity(),
      equipFilter: this.selectedEquipFilter(),
      sort: this.selectedSort(),
      isEquipped: (diceId) => this.isEquippedAnywhere(diceId),
    }),
  );
  readonly totalPages = computed(() => Math.max(1, Math.ceil(this.filteredDice().length / this.pageSize)));
  readonly currentPage = computed(() => Math.min(this.page(), this.totalPages()));
  readonly consumableTotalPages = computed(() => Math.max(1, Math.ceil(this.consumables().length / this.consumablePageSize)));
  readonly consumableCurrentPage = computed(() => Math.min(this.consumablePage(), this.consumableTotalPages()));
  readonly pagedDice = computed(() => {
    const start = (this.currentPage() - 1) * this.pageSize;
    return this.filteredDice().slice(start, start + this.pageSize);
  });
  readonly pagedConsumables = computed(() => {
    const start = (this.consumableCurrentPage() - 1) * this.consumablePageSize;
    return this.consumables().slice(start, start + this.consumablePageSize);
  });
  readonly inspectedDice = computed(() => {
    const pagedDice = this.pagedDice();
    if (!pagedDice.length) {
      return null;
    }

    const hoveredId = this.hoveredDiceId();
    if (hoveredId) {
      const hoveredDie = pagedDice.find((die) => die.id === hoveredId);
      if (hoveredDie) {
        return hoveredDie;
      }
    }

    return pagedDice[0] ?? null;
  });
  readonly pendingSellDice = computed(() => this.dice().find((die) => die.id === this.pendingSellDiceId()) ?? null);
  readonly pendingSalvageDice = computed(() => this.dice().find((die) => die.id === this.pendingSalvageDiceId()) ?? null);
  readonly inspectedConsumable = computed(() => {
    const pagedConsumables = this.pagedConsumables();
    if (!pagedConsumables.length) {
      return null;
    }

    const hoveredSlug = this.hoveredItemSlug();
    if (hoveredSlug) {
      const hoveredItem = pagedConsumables.find((item) => item.item_slug === hoveredSlug);
      if (hoveredItem) {
        return hoveredItem;
      }
    }

    return pagedConsumables[0] ?? null;
  });
  readonly inspectedAffixDetails = computed(() =>
    (this.inspectedDice()?.affixes ?? [])
      .map((affix) => ({
        name: this.resolveAffixName(affix),
        description: affix.description?.trim() ?? '',
      }))
      .filter((affix) => affix.name.length > 0 || affix.description.length > 0),
  );

  isEquippedAnywhere(diceId: string): boolean {
    return this.sessionService
      .units()
      .some((unit) => (unit.ability_dice ?? []).some((die) => die.dice_instance_id === diceId));
  }

  equippedUnit(diceId: string): { id: string; name: string } | null {
    const unit = this.sessionService
      .units()
      .find((entry) => (entry.ability_dice ?? []).some((die) => die.dice_instance_id === diceId));
    return unit ? { id: unit.id, name: unit.name } : null;
  }

  async sellDice(die: DiceRecord): Promise<void> {
    this.busyDiceId.set(die.id);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.diceService.sellDice(die.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set(`Sold die for ${response.data.sell_value}.`);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to sell die.');
    } finally {
      this.busyDiceId.set(null);
    }
  }

  async salvageDice(die: DiceRecord): Promise<void> {
    this.busyDiceId.set(die.id);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.diceService.salvageDice(die.id);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set(`Salvaged die for ${response.data.raw_chaos_awarded} Raw Chaos.`);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to salvage die.');
    } finally {
      this.busyDiceId.set(null);
    }
  }

  updateSize(value: string): void {
    this.selectedSize.set(value ? Number(value) : null);
    this.resetPage();
  }

  updateRarity(value: string): void {
    this.selectedRarity.set(value || null);
    this.resetPage();
  }

  updateEquipFilter(value: DiceEquipFilter): void {
    this.selectedEquipFilter.set(value);
    this.resetPage();
  }

  updateSort(value: DiceSortOption): void {
    this.selectedSort.set(value);
    this.resetPage();
  }

  showDiceInventory(): void {
    this.inventoryTab.set('dice');
  }

  showConsumableInventory(): void {
    this.inventoryTab.set('consumables');
  }

  previewDice(diceId: string): void {
    this.hoveredDiceId.set(diceId);
  }

  previewConsumable(itemSlug: string): void {
    this.hoveredItemSlug.set(itemSlug);
  }

  goToPreviousPage(): void {
    this.hoveredDiceId.set(null);
    this.page.set(Math.max(1, this.currentPage() - 1));
  }

  goToNextPage(): void {
    this.hoveredDiceId.set(null);
    this.page.set(Math.min(this.totalPages(), this.currentPage() + 1));
  }

  goToPreviousConsumablePage(): void {
    this.hoveredItemSlug.set(null);
    this.consumablePage.set(Math.max(1, this.consumableCurrentPage() - 1));
  }

  goToNextConsumablePage(): void {
    this.hoveredItemSlug.set(null);
    this.consumablePage.set(Math.min(this.consumableTotalPages(), this.consumableCurrentPage() + 1));
  }

  async activateDice(die: DiceRecord): Promise<void> {
    const unit = this.equippedUnit(die.id);
    if (unit) {
      await this.router.navigate(['/warband/units', unit.id]);
      return;
    }

    this.pendingSellDiceId.set(die.id);
  }

  closeSellConfirm(): void {
    if (this.busyDiceId()) {
      return;
    }

    this.pendingSellDiceId.set(null);
  }

  openSalvageConfirm(die: DiceRecord): void {
    if (!this.wrongMachineUnlocked()) {
      return;
    }

    this.pendingSellDiceId.set(null);
    this.pendingSalvageDiceId.set(die.id);
  }

  closeSalvageConfirm(): void {
    if (this.busyDiceId()) {
      return;
    }

    this.pendingSalvageDiceId.set(null);
  }

  async confirmSellDice(): Promise<void> {
    const die = this.pendingSellDice();
    if (!die) {
      this.pendingSellDiceId.set(null);
      return;
    }

    await this.sellDice(die);
    if (!this.error()) {
      this.pendingSellDiceId.set(null);
    }
  }

  async confirmSalvageDice(): Promise<void> {
    const die = this.pendingSalvageDice();
    if (!die) {
      this.pendingSalvageDiceId.set(null);
      return;
    }

    await this.salvageDice(die);
    if (!this.error()) {
      this.pendingSalvageDiceId.set(null);
    }
  }

  isInspecting(diceId: string): boolean {
    return this.inspectedDice()?.id === diceId;
  }

  isInspectingConsumable(itemSlug: string): boolean {
    return this.inspectedConsumable()?.item_slug === itemSlug;
  }

  diceTitle(die: DiceRecord | null): string {
    if (!die) {
      return 'd6';
    }

    const displayName = die.display_name?.trim();
    if (displayName) {
      return displayName;
    }

    const affixNames = (die.affixes ?? [])
      .map((affix) => this.resolveAffixName(affix))
      .filter((name) => name.length > 0);
    if (affixNames.length) {
      return `${affixNames.join(' ')} ${this.sizeLabel(die)}`;
    }

    return this.sizeLabel(die);
  }

  rarityLabel(die: DiceRecord | null): string {
    return this.normalizeLabel(die?.rarity, 'Common');
  }

  sizeLabel(die: DiceRecord | null): string {
    return `d${die?.sides ?? 6}`;
  }

  inspectArtUrl(die: DiceRecord | null): string {
    return resolveDiceArtStyles(die?.rarity, die?.sides, 132).imageUrl;
  }

  consumableEffectLabel(item: ItemRecord | null): string {
    const effect = String(item?.meta?.['effect'] ?? '');
    const amount = Number(item?.meta?.['amount'] ?? 0);
    if (effect === 'heal_run_unit_hp') {
      return amount > 0 ? `Heals ${amount} life` : 'Healing item';
    }
    if (effect === 'restore_energy') {
      return amount > 0 ? `Restores ${amount} energy` : 'Energy item';
    }

    return item?.is_spendable ? 'Consumable' : 'Supply';
  }

  consumableRarityLabel(item: ItemRecord | null): string {
    return this.normalizeLabel(item?.rarity, 'Common');
  }

  consumableIconLabel(item: ItemRecord | null): string {
    const effect = String(item?.meta?.['effect'] ?? '');
    if (effect === 'heal_run_unit_hp') {
      return '+';
    }
    if (effect === 'restore_energy') {
      return 'E';
    }

    return (item?.name?.trim().charAt(0) || '?').toUpperCase();
  }

  private resolveAffixName(affix: { name?: string | null; affix_slug?: string | null }): string {
    const name = affix.name?.trim();
    if (name) {
      return name;
    }

    const slug = affix.affix_slug?.trim();
    if (!slug) {
      return '';
    }

    return slug
      .split(/[_-]+/)
      .filter((segment) => segment.length > 0)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }

  private normalizeLabel(value: string | null | undefined, fallback: string): string {
    const normalized = value?.trim();
    if (!normalized) {
      return fallback;
    }

    return normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase();
  }

  private resetPage(): void {
    this.page.set(1);
    this.hoveredDiceId.set(null);
  }
}

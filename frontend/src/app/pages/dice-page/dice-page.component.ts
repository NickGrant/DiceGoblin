import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { DiceAffixRecord, DiceRecord } from '../../core/models/api.models';
import { DiceService } from '../../core/services/dice/dice.service';
import { SessionService } from '../../core/services/session/session.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { ConfirmModalComponent } from '../../shared/ui/confirm-modal/confirm-modal.component';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgButtonDirective } from '../../shared/ui/dg-button/dg-button.directive';
import { DgChipDirective } from '../../shared/ui/dg-chip/dg-chip.directive';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { DiceSortOption, filterAndSortDice } from '../../shared/ui/dice-display/dice-display.utils';

type DiceMaterial = 'all' | 'cardboard' | 'wood' | 'bone' | 'metal' | 'gemstone';

type DiceEffectDetail = {
  icon: string;
  label: string;
  description: string;
};

const DIE_SIZES = [4, 6, 8, 10, 12, 20] as const;

const MATERIAL_BY_RARITY: Record<string, Exclude<DiceMaterial, 'all'>> = {
  common: 'cardboard',
  uncommon: 'wood',
  rare: 'bone',
  epic: 'metal',
  legendary: 'gemstone',
};

@Component({
  selector: 'app-dice-page',
  standalone: true,
  imports: [
    ConfirmModalComponent,
    DgAlertComponent,
    DgButtonDirective,
    DgChipDirective,
    FormsModule,
    PageFrameComponent,
    RouterLink,
  ],
  templateUrl: './dice-page.component.html',
  styleUrl: './dice-page.component.scss',
  host: {
    '[attr.data-page]': "'dice-inventory'",
  },
})
export class DicePageComponent {
  private readonly diceService = inject(DiceService);
  private readonly sessionService = inject(SessionService);

  readonly dice = computed(() => this.sessionService.dice());
  readonly wrongMachineUnlocked = this.sessionService.wrongMachineUnlocked;
  readonly busyDiceId = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly searchTerm = signal('');
  readonly selectedMaterial = signal<DiceMaterial>('all');
  readonly selectedSize = signal<number | null>(null);
  readonly selectedSort = signal<DiceSortOption>('rarity-desc');
  readonly selectedDiceId = signal<string | null>(null);
  readonly page = signal(1);
  readonly pendingSellDiceId = signal<string | null>(null);
  readonly pendingSalvageDiceId = signal<string | null>(null);
  readonly dieSizes = DIE_SIZES;
  readonly pageSize = 8;

  readonly filteredDice = computed(() => {
    const searchTerm = this.searchTerm().trim().toLowerCase();
    const material = this.selectedMaterial();

    return filterAndSortDice(this.dice(), {
      selectedSize: this.selectedSize(),
      selectedRarity: null,
      equipFilter: 'all',
      sort: this.selectedSort(),
      isEquipped: (diceId) => this.isEquippedAnywhere(diceId),
    })
      .filter((die) => material === 'all' || this.materialSlug(die) === material)
      .filter((die) => !searchTerm || this.diceSearchText(die).includes(searchTerm));
  });

  readonly totalPages = computed(() => Math.max(1, Math.ceil(this.filteredDice().length / this.pageSize)));
  readonly currentPage = computed(() => Math.min(this.page(), this.totalPages()));
  readonly pagedDice = computed(() => {
    const start = (this.currentPage() - 1) * this.pageSize;
    return this.filteredDice().slice(start, start + this.pageSize);
  });

  readonly inspectedDice = computed(() => {
    const dice = this.pagedDice();
    if (!dice.length) {
      return null;
    }

    const selectedId = this.selectedDiceId();
    if (selectedId) {
      return dice.find((die) => die.id === selectedId) ?? dice[0];
    }

    return dice[0];
  });

  readonly pendingSellDice = computed(() => this.dice().find((die) => die.id === this.pendingSellDiceId()) ?? null);
  readonly pendingSalvageDice = computed(() => this.dice().find((die) => die.id === this.pendingSalvageDiceId()) ?? null);
  readonly inspectedEffects = computed(() => this.effectDetails(this.inspectedDice()));

  updateSearch(value: string): void {
    this.searchTerm.set(value);
    this.resetPage();
  }

  updateMaterial(value: DiceMaterial | string): void {
    this.selectedMaterial.set(this.isDiceMaterial(value) ? value : 'all');
    this.resetPage();
  }

  selectSize(size: number | null): void {
    this.selectedSize.set(size);
    this.resetPage();
  }

  updateSort(value: DiceSortOption): void {
    this.selectedSort.set(value);
    this.resetPage();
  }

  inspectDice(die: DiceRecord): void {
    this.selectedDiceId.set(die.id);
  }

  closeInspectPanel(): void {
    this.selectedDiceId.set(null);
  }

  goToPreviousPage(): void {
    this.page.set(Math.max(1, this.currentPage() - 1));
    this.selectedDiceId.set(null);
  }

  goToNextPage(): void {
    this.page.set(Math.min(this.totalPages(), this.currentPage() + 1));
    this.selectedDiceId.set(null);
  }

  isInspecting(diceId: string): boolean {
    return this.inspectedDice()?.id === diceId;
  }

  isEquippedAnywhere(diceId: string): boolean {
    return this.sessionService
      .units()
      .some(
        (unit) =>
          (unit.ability_dice ?? []).some((die) => die.dice_instance_id === diceId) ||
          (unit.equipped_dice ?? []).some((die) => die.dice_instance_id === diceId),
      );
  }

  equippedUnit(diceId: string): { id: string; name: string } | null {
    const unit = this.sessionService
      .units()
      .find(
        (entry) =>
          (entry.ability_dice ?? []).some((die) => die.dice_instance_id === diceId) ||
          (entry.equipped_dice ?? []).some((die) => die.dice_instance_id === diceId),
      );
    return unit ? { id: unit.id, name: unit.name } : null;
  }

  diceTitle(die: DiceRecord | null): string {
    const displayName = die?.display_name?.trim();
    if (displayName) {
      return displayName;
    }

    const material = this.materialLabel(die);
    return `${material} ${this.sizeLabel(die)}`;
  }

  rarityLabel(die: DiceRecord | null): string {
    return this.normalizeLabel(die?.rarity, 'Common');
  }

  sizeLabel(die: DiceRecord | null): string {
    return `D${die?.sides ?? 6}`;
  }

  materialLabel(die: DiceRecord | null): string {
    return this.normalizeLabel(this.materialSlug(die), 'Cardboard');
  }

  cardStatLabel(die: DiceRecord): string {
    const affix = die.affixes?.[0] ?? null;
    if (!affix) {
      return `VALUE +${die.value ?? die.sides ?? 0}`;
    }

    const value = Number(affix.value ?? 0);
    const prefix = value >= 0 ? '+' : '';
    return `${this.shortAffixLabel(affix)} ${prefix}${value}`;
  }

  diceLore(die: DiceRecord | null): string {
    if (!die) {
      return '';
    }

    const affix = die.affixes?.find((entry) => entry.description?.trim()) ?? null;
    if (affix?.description) {
      return affix.description;
    }

    return `A ${this.materialLabel(die).toLowerCase()} ${this.sizeLabel(die)} kept in the war chest for the next raid.`;
  }

  inspectArtUrl(die: DiceRecord | null): string {
    return resolveDiceArtStyles(die?.rarity, die?.sides, 160).imageUrl;
  }

  sellValueLabel(die: DiceRecord | null): string {
    return `${die?.sell_value ?? 0} Gold`;
  }

  salvageLabel(die: DiceRecord | null): string {
    const estimate = Math.max(1, Math.floor((die?.sell_value ?? die?.value ?? 5) / 3));
    return `Salvage (${estimate} Raw Chaos)`;
  }

  openSellConfirm(die: DiceRecord): void {
    this.pendingSalvageDiceId.set(null);
    this.pendingSellDiceId.set(die.id);
  }

  openSalvageConfirm(die: DiceRecord): void {
    if (!this.wrongMachineUnlocked()) {
      return;
    }

    this.pendingSellDiceId.set(null);
    this.pendingSalvageDiceId.set(die.id);
  }

  closeSellConfirm(): void {
    if (!this.busyDiceId()) {
      this.pendingSellDiceId.set(null);
    }
  }

  closeSalvageConfirm(): void {
    if (!this.busyDiceId()) {
      this.pendingSalvageDiceId.set(null);
    }
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
      this.syncSelectedDie();
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
      this.syncSelectedDie();
    }
  }

  private async sellDice(die: DiceRecord): Promise<void> {
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

  private async salvageDice(die: DiceRecord): Promise<void> {
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

  private effectDetails(die: DiceRecord | null): DiceEffectDetail[] {
    const affixes = die?.affixes ?? [];
    if (!affixes.length) {
      return [
        {
          icon: 'd20',
          label: 'Primary Bonus',
          description: `Adds ${die?.value ?? die?.sides ?? 0} to the die value when equipped.`,
        },
      ];
    }

    return affixes.map((affix, index) => ({
      icon: index === 0 ? 'd20' : '+',
      label: index === 0 ? 'Primary Bonus' : this.resolveAffixName(affix),
      description: this.affixDescription(affix),
    }));
  }

  private affixDescription(affix: DiceAffixRecord): string {
    const description = affix.description?.trim();
    if (description) {
      return description;
    }

    const value = Number(affix.value ?? 0);
    const prefix = value >= 0 ? '+' : '';
    return `${this.resolveAffixName(affix)} ${prefix}${value}.`;
  }

  private diceSearchText(die: DiceRecord): string {
    return [
      this.diceTitle(die),
      this.sizeLabel(die),
      this.rarityLabel(die),
      this.materialLabel(die),
      ...(die.affixes ?? []).flatMap((affix) => [this.resolveAffixName(affix), affix.description ?? '']),
    ]
      .join(' ')
      .toLowerCase();
  }

  private materialSlug(die: DiceRecord | null): Exclude<DiceMaterial, 'all'> {
    const displayName = die?.display_name?.trim().toLowerCase() ?? '';
    const materialFromName = (Object.values(MATERIAL_BY_RARITY) as Exclude<DiceMaterial, 'all'>[]).find((material) =>
      displayName.includes(material),
    );
    if (materialFromName) {
      return materialFromName;
    }

    return MATERIAL_BY_RARITY[(die?.rarity ?? '').trim().toLowerCase()] ?? 'cardboard';
  }

  private shortAffixLabel(affix: DiceAffixRecord): string {
    const name = this.resolveAffixName(affix);
    const compact = name
      .split(/\s+/)
      .filter((segment) => segment.length > 0)
      .map((segment) => segment.charAt(0))
      .join('')
      .slice(0, 3);

    return `STAT: ${compact || 'BON'}`;
  }

  private resolveAffixName(affix: { name?: string | null; affix_slug?: string | null }): string {
    const name = affix.name?.trim();
    if (name) {
      return name;
    }

    const slug = affix.affix_slug?.trim();
    if (!slug) {
      return 'Bonus';
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

  private isDiceMaterial(value: string): value is DiceMaterial {
    return value === 'all' || (Object.values(MATERIAL_BY_RARITY) as string[]).includes(value);
  }

  private syncSelectedDie(): void {
    const selectedId = this.selectedDiceId();
    if (!selectedId || this.pagedDice().some((die) => die.id === selectedId)) {
      return;
    }

    this.selectedDiceId.set(this.pagedDice()[0]?.id ?? null);
  }

  private resetPage(): void {
    this.page.set(1);
    this.selectedDiceId.set(null);
  }
}

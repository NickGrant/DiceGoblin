import { TitleCasePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { DiceRecord } from '../../core/models/api.models';
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
  readonly dice = computed(() => this.sessionService.dice());
  readonly busyDiceId = signal<string | null>(null);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly selectedSize = signal<number | null>(null);
  readonly selectedRarity = signal<string | null>(null);
  readonly selectedEquipFilter = signal<DiceEquipFilter>('all');
  readonly selectedSort = signal<DiceSortOption>('size-asc');
  readonly hoveredDiceId = signal<string | null>(null);
  readonly pendingSellDiceId = signal<string | null>(null);
  readonly sizeOptions = computed(() => buildDiceSizeOptions(this.dice()));
  readonly rarityOptions = computed(() => buildDiceRarityOptions(this.dice()));
  readonly filteredDice = computed(() =>
    filterAndSortDice(this.dice(), {
      selectedSize: this.selectedSize(),
      selectedRarity: this.selectedRarity(),
      equipFilter: this.selectedEquipFilter(),
      sort: this.selectedSort(),
      isEquipped: (diceId) => this.isEquippedAnywhere(diceId),
    }),
  );
  readonly inspectedDice = computed(() => {
    const filteredDice = this.filteredDice();
    if (!filteredDice.length) {
      return null;
    }

    const hoveredId = this.hoveredDiceId();
    if (hoveredId) {
      const hoveredDie = filteredDice.find((die) => die.id === hoveredId);
      if (hoveredDie) {
        return hoveredDie;
      }
    }

    return filteredDice[0] ?? null;
  });
  readonly pendingSellDice = computed(() => this.dice().find((die) => die.id === this.pendingSellDiceId()) ?? null);
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

  updateSize(value: string): void {
    this.selectedSize.set(value ? Number(value) : null);
  }

  updateRarity(value: string): void {
    this.selectedRarity.set(value || null);
  }

  updateEquipFilter(value: DiceEquipFilter): void {
    this.selectedEquipFilter.set(value);
  }

  updateSort(value: DiceSortOption): void {
    this.selectedSort.set(value);
  }

  previewDice(diceId: string): void {
    this.hoveredDiceId.set(diceId);
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

  isInspecting(diceId: string): boolean {
    return this.inspectedDice()?.id === diceId;
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
}

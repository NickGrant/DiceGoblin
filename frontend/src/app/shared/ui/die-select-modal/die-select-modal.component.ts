import { CdkTrapFocus } from '@angular/cdk/a11y';
import { TitleCasePipe, UpperCasePipe } from '@angular/common';
import { Component, computed, effect, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import {
  faChevronLeft,
  faChevronRight,
  faMagnifyingGlass,
  faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { DiceRecord } from '../../../core/models/api.models';
import { DgButtonDirective } from '../dg-button/dg-button.directive';
import { resolveDiceArtStyles } from '../dice-art/dice-art';
import {
  DiceEquipFilter,
  buildDiceRarityOptions,
  buildDiceSizeOptions,
  filterAndSortDice,
} from '../dice-display/dice-display.utils';

export type DieSelectFilterKey = 'status' | 'rarity' | 'size';

export type DieSelectFilterLocks = Partial<{
  status: DiceEquipFilter;
  rarity: string | null;
  size: number | null;
}>;

@Component({
  selector: 'dg-die-select-modal',
  standalone: true,
  imports: [CdkTrapFocus, DgButtonDirective, FontAwesomeModule, FormsModule, TitleCasePipe, UpperCasePipe],
  templateUrl: './die-select-modal.component.html',
  styleUrl: './die-select-modal.component.scss',
  host: {
    '[attr.data-component]': "'die-select-modal'",
  },
})
export class DieSelectModalComponent {
  readonly open = input(false);
  readonly title = input('Select Die');
  readonly dice = input<readonly DiceRecord[]>([]);
  readonly selectedDieId = input<string | null>(null);
  readonly equippedDiceIds = input<readonly string[]>([]);
  readonly lockedFilters = input<DieSelectFilterLocks>({});
  readonly hiddenFilters = input<readonly DieSelectFilterKey[]>([]);
  readonly pageSize = input(6);
  readonly busy = input(false);
  readonly allowEmptySelection = input(false);

  readonly dismissed = output<void>();
  readonly selected = output<DiceRecord | null>();

  readonly faChevronLeft = faChevronLeft;
  readonly faChevronRight = faChevronRight;
  readonly faMagnifyingGlass = faMagnifyingGlass;
  readonly faXmark = faXmark;

  readonly query = signal('');
  readonly activeDieId = signal<string | null>(null);
  readonly statusFilter = signal<DiceEquipFilter>('all');
  readonly rarityFilter = signal<string | null>(null);
  readonly sizeFilter = signal<number | null>(null);
  readonly page = signal(1);

  readonly hiddenFilterSet = computed(() => new Set(this.hiddenFilters()));
  readonly equippedDiceIdSet = computed(() => new Set(this.equippedDiceIds()));
  readonly sizeOptions = computed(() => buildDiceSizeOptions(this.dice()));
  readonly rarityOptions = computed(() => buildDiceRarityOptions(this.dice()));
  readonly selectedDie = computed(() => this.dice().find((die) => die.id === this.activeDieId()) ?? null);
  readonly filteredDice = computed(() => {
    const query = this.query().trim().toLowerCase();
    return filterAndSortDice(this.dice(), {
      selectedSize: this.effectiveSize(),
      selectedRarity: this.effectiveRarity(),
      equipFilter: this.effectiveStatus(),
      sort: 'size-asc',
      isEquipped: (diceId) => this.isEquipped(diceId),
    }).filter((die) => {
      if (!query) {
        return true;
      }

      return this.searchText(die).includes(query);
    });
  });
  readonly totalPages = computed(() =>
    Math.max(1, Math.ceil(this.filteredDice().length / Math.max(1, this.pageSize()))),
  );
  readonly pagedDice = computed(() => {
    const currentPage = Math.min(this.page(), this.totalPages());
    const start = (currentPage - 1) * Math.max(1, this.pageSize());
    return this.filteredDice().slice(start, start + Math.max(1, this.pageSize()));
  });

  constructor() {
    effect(() => {
      this.activeDieId.set(this.selectedDieId());
    });

    effect(() => {
      const locks = this.lockedFilters();
      if (locks.status !== undefined) {
        this.statusFilter.set(locks.status);
      }
      if (locks.rarity !== undefined) {
        this.rarityFilter.set(locks.rarity);
      }
      if (locks.size !== undefined) {
        this.sizeFilter.set(locks.size);
      }
    });

    effect(() => {
      this.filteredDice();
      this.page.set(1);
    });
  }

  close(): void {
    this.dismissed.emit();
  }

  chooseDie(die: DiceRecord): void {
    this.activeDieId.set(die.id);
  }

  confirm(): void {
    if (!this.allowEmptySelection() && !this.selectedDie()) {
      return;
    }

    this.selected.emit(this.selectedDie());
  }

  clearSelection(): void {
    if (!this.allowEmptySelection()) {
      return;
    }

    this.activeDieId.set(null);
  }

  updateStatus(value: DiceEquipFilter): void {
    if (!this.isFilterLocked('status')) {
      this.statusFilter.set(value);
    }
  }

  updateRarity(value: string): void {
    if (!this.isFilterLocked('rarity')) {
      this.rarityFilter.set(value || null);
    }
  }

  updateSize(value: string): void {
    if (!this.isFilterLocked('size')) {
      this.sizeFilter.set(value ? Number(value) : null);
    }
  }

  previousPage(): void {
    this.page.update((page) => Math.max(1, page - 1));
  }

  nextPage(): void {
    this.page.update((page) => Math.min(this.totalPages(), page + 1));
  }

  isFilterHidden(filter: DieSelectFilterKey): boolean {
    return this.hiddenFilterSet().has(filter);
  }

  isFilterLocked(filter: DieSelectFilterKey): boolean {
    return this.lockedFilters()[filter] !== undefined;
  }

  isActive(die: DiceRecord): boolean {
    return this.activeDieId() === die.id;
  }

  isEquipped(diceId: string): boolean {
    return this.equippedDiceIdSet().has(diceId);
  }

  diceArtUrl(die: DiceRecord): string {
    return resolveDiceArtStyles(die.rarity, die.sides, 64).imageUrl;
  }

  diceTitle(die: DiceRecord): string {
    return die.display_name?.trim() || `${this.toTitleCase(die.rarity || 'common')} D${die.sides ?? 6}`;
  }

  rarityTone(die: DiceRecord): string {
    return (die.rarity ?? 'common').trim().toLowerCase() || 'common';
  }

  affixLabel(die: DiceRecord): string {
    const affix = die.affixes?.[0];
    if (!affix) {
      return 'No affix';
    }

    if (affix.description?.trim()) {
      return affix.description.trim();
    }

    const name = affix.name?.trim() || affix.affix_slug?.replace(/[_-]+/g, ' ') || 'Affix';
    return `${name}: ${affix.value}`;
  }

  statusLabel(die: DiceRecord): string {
    return this.isEquipped(die.id) ? 'Equipped' : 'Available';
  }

  private effectiveStatus(): DiceEquipFilter {
    return this.lockedFilters().status ?? this.statusFilter();
  }

  private effectiveRarity(): string | null {
    return this.lockedFilters().rarity ?? this.rarityFilter();
  }

  private effectiveSize(): number | null {
    return this.lockedFilters().size ?? this.sizeFilter();
  }

  private searchText(die: DiceRecord): string {
    return [
      this.diceTitle(die),
      die.rarity,
      die.sides ? `d${die.sides}` : '',
      ...(die.affixes ?? []).flatMap((affix) => [affix.name, affix.affix_slug, affix.description]),
      this.statusLabel(die),
    ]
      .filter((value): value is string => typeof value === 'string')
      .join(' ')
      .toLowerCase();
  }

  private toTitleCase(value: string): string {
    return value
      .split(/[_\s-]+/)
      .filter((segment) => segment.length > 0)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
      .join(' ');
  }
}

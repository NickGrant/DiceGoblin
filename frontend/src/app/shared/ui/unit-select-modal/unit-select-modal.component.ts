import { CdkTrapFocus } from '@angular/cdk/a11y';
import { TitleCasePipe } from '@angular/common';
import { Component, computed, effect, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import {
  faChevronLeft,
  faChevronRight,
  faMagnifyingGlass,
  faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { UnitRecord } from '../../../core/models/api.models';
import { DgButtonDirective } from '../dg-button/dg-button.directive';
import { resolveUnitThumbnailUrl } from '../unit-art/unit-art';

export type UnitSelectFilterKey = 'kin' | 'unitType' | 'tier' | 'levelMin' | 'levelMax';

export type UnitSelectFilterLocks = Partial<{
  kin: string | null;
  unitType: string | null;
  tier: number | null;
  levelMin: number | null;
  levelMax: number | null;
}>;

@Component({
  selector: 'dg-unit-select-modal',
  standalone: true,
  imports: [CdkTrapFocus, DgButtonDirective, FontAwesomeModule, FormsModule, TitleCasePipe],
  templateUrl: './unit-select-modal.component.html',
  styleUrl: './unit-select-modal.component.scss',
  host: {
    '[attr.data-component]': "'unit-select-modal'",
  },
})
export class UnitSelectModalComponent {
  readonly open = input(false);
  readonly title = input('Select Unit');
  readonly units = input<readonly UnitRecord[]>([]);
  readonly selectedUnitId = input<string | null>(null);
  readonly lockedFilters = input<UnitSelectFilterLocks>({});
  readonly hiddenFilters = input<readonly UnitSelectFilterKey[]>([]);
  readonly pageSize = input(6);
  readonly busy = input(false);

  readonly dismissed = output<void>();
  readonly selected = output<UnitRecord>();

  readonly faChevronLeft = faChevronLeft;
  readonly faChevronRight = faChevronRight;
  readonly faMagnifyingGlass = faMagnifyingGlass;
  readonly faXmark = faXmark;

  readonly query = signal('');
  readonly activeUnitId = signal<string | null>(null);
  readonly kinFilter = signal<string | null>(null);
  readonly unitTypeFilter = signal<string | null>(null);
  readonly tierFilter = signal<number | null>(null);
  readonly levelMinFilter = signal<number | null>(null);
  readonly levelMaxFilter = signal<number | null>(null);
  readonly page = signal(1);

  readonly hiddenFilterSet = computed(() => new Set(this.hiddenFilters()));
  readonly kinOptions = computed(() => this.uniqueOptions((unit) => unit.kin_slug || unit.kin_name));
  readonly unitTypeOptions = computed(() => this.uniqueOptions((unit) => unit.unit_type_slug || unit.unit_type_name));
  readonly tierOptions = computed(() =>
    [...new Set(this.units().map((unit) => unit.tier).filter((tier): tier is number => typeof tier === 'number'))]
      .sort((left, right) => left - right),
  );
  readonly selectedUnit = computed(() => this.units().find((unit) => unit.id === this.activeUnitId()) ?? null);
  readonly filteredUnits = computed(() => {
    const query = this.query().trim().toLowerCase();
    return this.units()
      .filter((unit) => this.matchesKin(unit))
      .filter((unit) => this.matchesUnitType(unit))
      .filter((unit) => this.matchesTier(unit))
      .filter((unit) => this.matchesLevel(unit))
      .filter((unit) => !query || this.searchText(unit).includes(query))
      .sort((left, right) => left.name.localeCompare(right.name));
  });
  readonly totalPages = computed(() =>
    Math.max(1, Math.ceil(this.filteredUnits().length / Math.max(1, this.pageSize()))),
  );
  readonly pagedUnits = computed(() => {
    const currentPage = Math.min(this.page(), this.totalPages());
    const start = (currentPage - 1) * Math.max(1, this.pageSize());
    return this.filteredUnits().slice(start, start + Math.max(1, this.pageSize()));
  });

  constructor() {
    effect(() => {
      this.activeUnitId.set(this.selectedUnitId());
    });

    effect(() => {
      const locks = this.lockedFilters();
      if (locks.kin !== undefined) {
        this.kinFilter.set(locks.kin);
      }
      if (locks.unitType !== undefined) {
        this.unitTypeFilter.set(locks.unitType);
      }
      if (locks.tier !== undefined) {
        this.tierFilter.set(locks.tier);
      }
      if (locks.levelMin !== undefined) {
        this.levelMinFilter.set(locks.levelMin);
      }
      if (locks.levelMax !== undefined) {
        this.levelMaxFilter.set(locks.levelMax);
      }
    });

    effect(() => {
      this.filteredUnits();
      this.page.set(1);
    });
  }

  close(): void {
    this.dismissed.emit();
  }

  chooseUnit(unit: UnitRecord): void {
    this.activeUnitId.set(unit.id);
  }

  confirm(): void {
    const unit = this.selectedUnit();
    if (unit) {
      this.selected.emit(unit);
    }
  }

  updateKin(value: string): void {
    if (!this.isFilterLocked('kin')) {
      this.kinFilter.set(value || null);
    }
  }

  updateUnitType(value: string): void {
    if (!this.isFilterLocked('unitType')) {
      this.unitTypeFilter.set(value || null);
    }
  }

  updateTier(value: string): void {
    if (!this.isFilterLocked('tier')) {
      this.tierFilter.set(value ? Number(value) : null);
    }
  }

  updateLevelMin(value: string): void {
    if (!this.isFilterLocked('levelMin')) {
      this.levelMinFilter.set(value ? Number(value) : null);
    }
  }

  updateLevelMax(value: string): void {
    if (!this.isFilterLocked('levelMax')) {
      this.levelMaxFilter.set(value ? Number(value) : null);
    }
  }

  previousPage(): void {
    this.page.update((page) => Math.max(1, page - 1));
  }

  nextPage(): void {
    this.page.update((page) => Math.min(this.totalPages(), page + 1));
  }

  isFilterHidden(filter: UnitSelectFilterKey): boolean {
    return this.hiddenFilterSet().has(filter);
  }

  isFilterLocked(filter: UnitSelectFilterKey): boolean {
    return this.lockedFilters()[filter] !== undefined;
  }

  isActive(unit: UnitRecord): boolean {
    return this.activeUnitId() === unit.id;
  }

  unitThumbnailUrl(unit: UnitRecord): string | null {
    return resolveUnitThumbnailUrl(unit.unit_type_slug) ?? resolveUnitThumbnailUrl(unit.unit_type_name);
  }

  kinLabel(unit: UnitRecord): string {
    return unit.kin_name || this.labelFromSlug(unit.kin_slug) || 'Basic Kin';
  }

  unitTypeLabel(unit: UnitRecord): string {
    return unit.unit_type_name || this.labelFromSlug(unit.unit_type_slug) || 'Unit';
  }

  xpPercent(unit: UnitRecord): number {
    const xp = Number(unit.xp ?? 0);
    const target = Number(unit.xp_to_next_level ?? 0);
    if (target <= 0) {
      return unit.is_mastered ? 100 : 0;
    }

    return Math.max(0, Math.min(100, Math.round((xp / target) * 100)));
  }

  private effectiveKin(): string | null {
    return this.lockedFilters().kin ?? this.kinFilter();
  }

  private effectiveUnitType(): string | null {
    return this.lockedFilters().unitType ?? this.unitTypeFilter();
  }

  private effectiveTier(): number | null {
    return this.lockedFilters().tier ?? this.tierFilter();
  }

  private effectiveLevelMin(): number | null {
    return this.lockedFilters().levelMin ?? this.levelMinFilter();
  }

  private effectiveLevelMax(): number | null {
    return this.lockedFilters().levelMax ?? this.levelMaxFilter();
  }

  private matchesKin(unit: UnitRecord): boolean {
    const kin = this.effectiveKin();
    return !kin || this.matchesNormalized(kin, unit.kin_slug, unit.kin_name);
  }

  private matchesUnitType(unit: UnitRecord): boolean {
    const unitType = this.effectiveUnitType();
    return !unitType || this.matchesNormalized(unitType, unit.unit_type_slug, unit.unit_type_name);
  }

  private matchesTier(unit: UnitRecord): boolean {
    const tier = this.effectiveTier();
    return tier === null || unit.tier === tier;
  }

  private matchesLevel(unit: UnitRecord): boolean {
    const level = unit.level ?? 0;
    const min = this.effectiveLevelMin();
    const max = this.effectiveLevelMax();
    return (min === null || level >= min) && (max === null || level <= max);
  }

  private searchText(unit: UnitRecord): string {
    return [
      unit.name,
      unit.kin_name,
      unit.kin_slug,
      unit.unit_type_name,
      unit.unit_type_slug,
      unit.tier ? `tier ${unit.tier}` : '',
      unit.level ? `level ${unit.level}` : '',
    ]
      .filter((value): value is string => typeof value === 'string')
      .join(' ')
      .toLowerCase();
  }

  private uniqueOptions(selector: (unit: UnitRecord) => string | null | undefined): string[] {
    return [...new Set(this.units().map((unit) => selector(unit)?.trim()).filter((value): value is string => !!value))]
      .sort((left, right) => this.labelFromSlug(left).localeCompare(this.labelFromSlug(right)));
  }

  private matchesNormalized(target: string, ...values: Array<string | null | undefined>): boolean {
    const normalizedTarget = this.normalize(target);
    return values.some((value) => this.normalize(value) === normalizedTarget);
  }

  private normalize(value: string | null | undefined): string {
    return (value ?? '').trim().toLowerCase().replace(/[\s-]+/g, '_');
  }

  private labelFromSlug(value: string | null | undefined): string {
    return (value ?? '')
      .trim()
      .split(/[_\s-]+/)
      .filter((segment) => segment.length > 0)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1).toLowerCase())
      .join(' ');
  }
}

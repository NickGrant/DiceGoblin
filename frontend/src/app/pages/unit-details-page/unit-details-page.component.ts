import {
  CdkDrag,
  CdkDragDrop,
  CdkDragHandle,
  CdkDropList,
  moveItemInArray,
} from '@angular/cdk/drag-drop';
import { Component, computed, effect, inject, signal } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import {
  faArrowDown,
  faArrowUp,
  faCrown,
  faHandFist,
  faHeartPulse,
  faPlus,
  faShieldHalved,
  faTrophy,
  faUserPen,
  faXmark,
} from '@fortawesome/free-solid-svg-icons';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import {
  DiceRecord,
  UnitCapstoneChoiceRecord,
  UnitAbilityDieRecord,
  UnitRecord,
} from '../../core/models/api.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { resolveDiceArtStyles } from '../../shared/ui/dice-art/dice-art';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DicePickerModalComponent } from '../../shared/ui/dice-picker-modal/dice-picker-modal.component';
import { type TabStripItem } from '../../shared/ui/tab-strip/tab-strip.component';
import { formatUnitKinLabel, humanizeAbilityId, normalizeAbilityId, resolveAbilityDisplayName, toRomanNumeral } from '../../shared/utils/unit-formatters';
import { resolveUnitImageUrl } from '../../shared/ui/unit-art/unit-art';
import { UnitThumbnailComponent } from '../../shared/ui/unit-thumbnail/unit-thumbnail.component';

type AbilitySlotViewModel = {
  abilityId: string;
  displayName: string;
  shortDesc: string;
  speedCost: number;
  diceCost: number;
  copyCount: number;
  slots: Array<{
    slotIndex: number;
    die: DiceRecord | null;
  }>;
};

type AbilityLoadoutViewModel = {
  abilityId: string;
  displayName: string;
  shortDesc: string;
  type: 'active' | 'passive' | string;
  speed: number;
  diceCost: number;
  equippedCount: number;
};

type LoadoutBarViewModel = {
  instanceKey: string;
  abilityId: string;
  displayName: string;
  speed: number;
  diceCost: number;
  heightPx: number;
};

type StaticAbilityViewModel = {
  abilityId: string;
  displayName: string;
  shortDesc: string;
  badge: string;
};

type PickerState = {
  abilityId: string;
  abilityName: string;
  slotIndex: number;
};

type DiceAssignmentRecord = {
  unitId: string;
  abilityId: string;
  slotIndex: number;
  diceId: string;
};

@Component({
  selector: 'app-unit-details-page',
  standalone: true,
  imports: [
    CdkDrag,
    CdkDragHandle,
    CdkDropList,
    DgAlertComponent,
    DgCommandBtnDirective,
    PageFrameComponent,
    DicePickerModalComponent,
    FontAwesomeModule,
    FormsModule,
    RouterLink,
    UnitThumbnailComponent,
  ],
  templateUrl: './unit-details-page.component.html',
  styleUrl: './unit-details-page.component.scss',
})
export class UnitDetailsPageComponent {
  private static readonly LOADOUT_SPEED_BUDGET = 20;
  private static readonly LOADOUT_PIXEL_PER_SPEED = 15;

  private readonly route = inject(ActivatedRoute);
  private readonly sessionService = inject(SessionService);
  private readonly unitService = inject(UnitService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);
  readonly faPlus = faPlus;
  readonly faArrowUp = faArrowUp;
  readonly faArrowDown = faArrowDown;
  readonly faXmark = faXmark;
  readonly faUserPen = faUserPen;
  readonly faTrophy = faTrophy;
  readonly faCrown = faCrown;
  readonly faHeartPulse = faHeartPulse;
  readonly faHandFist = faHandFist;
  readonly faShieldHalved = faShieldHalved;

  readonly unitId = this.route.snapshot.paramMap.get('unitId') ?? '';
  readonly unit = computed<UnitRecord | null>(
    () => this.sessionService.units().find((entry) => entry.id === this.unitId) ?? null,
  );
  readonly activeSquad = this.sessionService.activeSquad;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly academyUnlocked = this.sessionService.academyUnlocked;
  readonly unitLocked = computed(
    () => !!this.unit()?.locked || (this.hasActiveRun() && !!this.activeSquad()?.unit_ids?.includes(this.unitId)),
  );
  readonly showPromotionAction = computed(() => {
    const unit = this.unit();
    if (!unit) {
      return false;
    }

    return this.academyUnlocked() || !!unit.promotion_eligible;
  });
  readonly units = this.sessionService.units;
  readonly dice = this.sessionService.dice;
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);
  readonly selectingCapstoneId = signal<string | null>(null);
  readonly busy = signal(false);
  readonly busySlotKey = signal<string | null>(null);
  readonly activeTab = signal<'stats' | 'abilities'>('stats');
  readonly tabs: ReadonlyArray<TabStripItem> = [
    { id: 'stats', label: 'Stats', kicker: 'Unit' },
    { id: 'abilities', label: 'Abilities', kicker: 'Loadout' },
  ];
  readonly pendingEquippedAbilityIds = signal<string[]>([]);
  readonly savingLoadout = signal(false);
  readonly pickerState = signal<PickerState | null>(null);
  readonly abilityCatalogError = this.abilityCatalogService.error;
  readonly abilityCatalog = this.abilityCatalogService.abilityMap;
  readonly unlockedAbilityIds = computed(() => this.unit()?.unlocked_abilities?.map((ability) => ability.ability_id) ?? []);
  readonly learnedAbilities = computed<AbilityLoadoutViewModel[]>(() => {
    const unit = this.unit();
    if (!unit) {
      return [];
    }

    const authoredAbilityIds = (unit.abilities ?? [])
      .map((ability) => normalizeAbilityId(ability.ability_id))
      .filter((abilityId): abilityId is string => abilityId !== null);
    const unlockedAbilityIds = this.unlockedAbilityIds()
      .map((abilityId) => normalizeAbilityId(abilityId))
      .filter((abilityId): abilityId is string => abilityId !== null);
    const learnedIds = Array.from(new Set([...authoredAbilityIds, ...unlockedAbilityIds]));

    return learnedIds.map((abilityId) => {
      const abilityMeta = this.abilityCatalog().get(abilityId);
      const authoredRecord = unit.abilities?.find((ability) => ability.ability_id === abilityId);
      return {
        abilityId,
        displayName: abilityMeta?.display_name ?? humanizeAbilityId(abilityId),
        shortDesc: abilityMeta?.short_desc ?? 'No description available.',
        type: abilityMeta?.type ?? authoredRecord?.type ?? 'active',
        speed: abilityMeta?.speed ?? 0,
        diceCost: Math.max(0, abilityMeta?.dice_cost ?? 0),
        equippedCount: this.pendingEquippedAbilityIds().filter((entry) => entry === abilityId).length,
      };
    });
  });
  readonly learnedActiveAbilities = computed(() =>
    this.learnedAbilities()
      .filter((ability) => ability.type === 'active')
      .sort((left, right) => left.displayName.localeCompare(right.displayName)),
  );
  readonly learnedPassiveAbilities = computed(() =>
    this.learnedAbilities()
      .filter((ability) => ability.type !== 'active')
      .sort((left, right) => left.displayName.localeCompare(right.displayName)),
  );
  readonly staticAbilities = computed<StaticAbilityViewModel[]>(() => {
    const cards = new Map<string, StaticAbilityViewModel>();

    for (const ability of this.learnedPassiveAbilities()) {
      cards.set(ability.abilityId, {
        abilityId: ability.abilityId,
        displayName: ability.displayName,
        shortDesc: ability.shortDesc,
        badge: 'Passive',
      });
    }

    for (const inherited of this.inheritedPassiveAbilities()) {
      const abilityId = normalizeAbilityId(inherited.ability_id);
      if (!abilityId || cards.has(abilityId)) {
        continue;
      }

      cards.set(abilityId, {
        abilityId,
        displayName: this.abilityDisplayName(abilityId),
        shortDesc: this.abilityShortDescription(abilityId),
        badge: 'Inherited',
      });
    }

    const selectedCapstoneId = normalizeAbilityId(this.selectedCapstoneAbilityId());
    if (selectedCapstoneId && !cards.has(selectedCapstoneId)) {
      cards.set(selectedCapstoneId, {
        abilityId: selectedCapstoneId,
        displayName: this.abilityDisplayName(selectedCapstoneId),
        shortDesc: this.abilityShortDescription(selectedCapstoneId),
        badge: 'Passive',
      });
    }

    return Array.from(cards.values()).sort((left, right) => left.displayName.localeCompare(right.displayName));
  });
  readonly capstoneChoices = computed<UnitCapstoneChoiceRecord[]>(() => this.unit()?.capstone_choices ?? []);
  readonly inheritedPassiveAbilities = computed(() => this.unit()?.inherited_passive_abilities ?? []);
  readonly selectedCapstoneAbilityId = computed(() => this.unit()?.selected_capstone?.ability_id ?? null);
  readonly currentCapstoneState = computed(() => (this.unit()?.current_capstone_state ?? 'none').toString());
  readonly promotionReadinessLabel = computed(() => {
    const unit = this.unit();
    if (!unit) {
      return 'Unavailable';
    }

    const threshold = unit.promotion_level ?? 6;
    if (unit.promotion_eligible) {
      return `Eligible now (unlocked at level ${threshold})`;
    }

    return `Unlocks at level ${threshold}`;
  });
  readonly canChooseCapstone = computed(
    () => this.currentCapstoneState() === 'ready_to_select' && !this.selectedCapstoneAbilityId(),
  );
  readonly totalEquippedSpeed = computed(() =>
    this.pendingEquippedAbilityIds().reduce(
      (total, abilityId) => total + (this.abilityCatalog().get(abilityId)?.speed ?? 0),
      0,
    ),
  );
  readonly loadoutHeightPx = computed(
    () => UnitDetailsPageComponent.LOADOUT_SPEED_BUDGET * UnitDetailsPageComponent.LOADOUT_PIXEL_PER_SPEED,
  );
  readonly canSaveLoadout = computed(() => {
    const current = (this.unit()?.equipped_abilities ?? []).map((ability) => ability.ability_id);
    const next = this.pendingEquippedAbilityIds();
    return current.length !== next.length || current.some((abilityId, index) => next[index] !== abilityId);
  });
  readonly totalLoadoutDiceSlots = computed(() =>
    this.configurableAbilitySlots().reduce((total, ability) => total + ability.diceCost, 0),
  );
  readonly loadoutBars = computed<LoadoutBarViewModel[]>(() =>
    this.pendingEquippedAbilityIds().map((abilityId, index) => {
      const ability = this.learnedAbilities().find((entry) => entry.abilityId === abilityId);
      const speed = Math.max(0, ability?.speed ?? this.abilityCatalog().get(abilityId)?.speed ?? 0);
      return {
        instanceKey: `${abilityId}:${index}`,
        abilityId,
        displayName: ability?.displayName ?? humanizeAbilityId(abilityId),
        speed,
        diceCost: Math.max(0, ability?.diceCost ?? this.abilityCatalog().get(abilityId)?.dice_cost ?? 0),
        heightPx: speed * UnitDetailsPageComponent.LOADOUT_PIXEL_PER_SPEED,
      };
    }),
  );
  readonly configurableAbilitySlots = computed<AbilitySlotViewModel[]>(() => {
    return this.learnedActiveAbilities().map((ability) => {
      const abilityId = ability.abilityId;
      const abilityMeta = this.abilityCatalog().get(abilityId);
      const speedCost = abilityMeta?.speed ?? 0;
      const diceCost = Math.max(0, abilityMeta?.dice_cost ?? 0);

      return {
        abilityId,
        displayName: abilityMeta?.display_name ?? humanizeAbilityId(abilityId),
        shortDesc: abilityMeta?.short_desc ?? 'Configure dice used when this ability resolves.',
        speedCost,
        diceCost,
        copyCount: this.pendingEquippedAbilityIds().filter((entry) => entry === abilityId).length,
        slots: Array.from({ length: diceCost }, (_unused, slotIndex) => ({
          slotIndex,
          die: this.findDice(this.findUnitAbilityBinding(this.unit(), abilityId, slotIndex)?.dice_instance_id ?? null),
        })),
      };
    }).filter((ability) => ability.diceCost > 0);
  });
  readonly pickerAvailableDice = computed(() => {
    const pickerState = this.pickerState();
    if (!pickerState) {
      return [];
    }

    return this.dice().filter((die) => {
      const assignment = this.findDiceAssignment(die.id);
      if (!assignment) {
        return true;
      }

      return (
        assignment.unitId === this.unitId &&
        assignment.abilityId === pickerState.abilityId &&
        assignment.slotIndex === pickerState.slotIndex
      );
    });
  });
  readonly pickerSelectedDiceId = computed(
    () =>
      this.findUnitAbilityBinding(
        this.unit(),
        this.pickerState()?.abilityId ?? '',
        this.pickerState()?.slotIndex ?? -1,
      )?.dice_instance_id ?? null,
  );
  readonly pickerSlotLabel = computed(() => {
    const pickerState = this.pickerState();
    if (!pickerState) {
      return '';
    }

    return `${pickerState.abilityName} - Slot ${pickerState.slotIndex + 1}`;
  });
  readonly pickerBusy = computed(
    () =>
      !!this.pickerState() &&
      this.busySlotKey() === this.slotKey(this.pickerState()!.abilityId, this.pickerState()!.slotIndex),
  );

  renameValue = '';

  readonly unitTypeLabel = computed(() => this.unit()?.unit_type_name || this.unit()?.unit_type_slug || 'Unit');
  readonly kinLabel = computed(() => {
    const unit = this.unit();
    return unit ? formatUnitKinLabel(unit) : 'Basic Goblin';
  });
  readonly kinSummary = computed(() =>
    this.normalizeKinSummary(this.unit()?.kin_passive_summary || this.unit()?.splice_variant_passive_summary),
  );
  readonly tierRomanNumeral = computed(() => toRomanNumeral(this.unit()?.tier ?? 1));
  readonly portraitLoadFailed = signal(false);
  readonly unitPortraitUrl = computed(() => resolveUnitImageUrl(this.unit()?.unit_type_slug));
  readonly unitStatTiles = computed(() => {
    const unit = this.unit();
    if (!unit) {
      return [];
    }

    return [
      {
        icon: this.faCrown,
        label: 'Tier',
        value: this.tierRomanNumeral(),
        meta: this.unitTypeLabel(),
        description: 'Tier is the unit type rank. Higher tiers usually unlock stronger stat growth and abilities.',
      },
      {
        icon: this.faArrowUp,
        label: 'Level',
        value: `${unit.level ?? 1}/${unit.max_level || 0}`,
        meta: `XP to next: ${unit.xp_to_next_level || 0}`,
        description: 'Level reflects combat experience. Units can promote when they reach their promotion level.',
      },
      {
        icon: this.faHeartPulse,
        label: 'HP',
        value: `${unit.current_hp || unit.max_hp || 0}/${unit.max_hp || 0}`,
        meta: 'Frontline endurance',
        description: 'HP is how much damage this unit can take before being defeated in a run.',
      },
      {
        icon: this.faHandFist,
        label: 'Attack',
        value: `${unit.total_attack || 0}`,
        meta: 'Strike pressure',
        description: 'Attack increases the damage dealt by offensive actions.',
      },
      {
        icon: this.faTrophy,
        label: 'Precision',
        value: `${unit.total_precision ?? 5}`,
        meta: 'Effect reliability',
        description: 'Precision improves the reliability and effectiveness of actions that need accuracy or control.',
      },
      {
        icon: this.faShieldHalved,
        label: 'Defense',
        value: `${unit.total_defense || 0}`,
        meta: 'Damage mitigation',
        description: 'Defense reduces incoming physical damage.',
      },
      {
        icon: this.faShieldHalved,
        label: 'Resolve',
        value: `${unit.total_resolve ?? 5}`,
        meta: 'Status resistance',
        description: 'Resolve helps resist harmful effects and pressure during combat.',
      },
    ];
  });

  constructor() {
    this.renameValue = this.unit()?.name ?? '';
    void this.abilityCatalogService.load();
    effect(() => {
      this.pendingEquippedAbilityIds.set(
        (this.unit()?.equipped_abilities ?? []).map((ability) => ability.ability_id),
      );
    });
    effect(() => {
      this.unitPortraitUrl();
      this.portraitLoadFailed.set(false);
    });
  }

  setActiveTab(tab: 'stats' | 'abilities'): void {
    this.activeTab.set(tab);
  }

  handleTabSelection(tabId: string): void {
    this.setActiveTab(tabId === 'abilities' ? 'abilities' : 'stats');
  }

  addAbilityToLoadout(abilityId: string, insertIndex?: number): void {
    if (this.unitLocked()) {
      return;
    }

    const ability = this.learnedAbilities().find((entry) => entry.abilityId === abilityId)
      ?? this.abilityCatalog().get(abilityId);
    if (!ability || ability.type !== 'active') {
      return;
    }

    const nextTotal = this.totalEquippedSpeed() + (ability.speed ?? 0);
    if (nextTotal > UnitDetailsPageComponent.LOADOUT_SPEED_BUDGET) {
      this.error.set('Equipped abilities cannot exceed the 20-point speed budget.');
      return;
    }

    this.error.set(null);
    this.pendingEquippedAbilityIds.update((current) => {
      const next = [...current];
      const targetIndex =
        insertIndex === undefined ? next.length : Math.max(0, Math.min(insertIndex, next.length));
      next.splice(targetIndex, 0, abilityId);
      return next;
    });
  }

  canAddAbilityToLoadout(abilityId: string): boolean {
    if (this.unitLocked()) {
      return false;
    }

    const ability = this.learnedAbilities().find((entry) => entry.abilityId === abilityId)
      ?? this.abilityCatalog().get(abilityId);
    if (!ability || ability.type !== 'active') {
      return false;
    }

    return this.totalEquippedSpeed() + (ability.speed ?? 0) <= UnitDetailsPageComponent.LOADOUT_SPEED_BUDGET;
  }

  removeAbilityFromLoadout(indexOrAbilityId: number | string): void {
    if (this.unitLocked()) {
      return;
    }

    if (typeof indexOrAbilityId === 'number') {
      this.pendingEquippedAbilityIds.update((current) =>
        current.filter((_entry, index) => index !== indexOrAbilityId),
      );
      return;
    }

    let removed = false;
    this.pendingEquippedAbilityIds.update((current) =>
      current.filter((entry) => {
        if (!removed && entry === indexOrAbilityId) {
          removed = true;
          return false;
        }
        return true;
      }),
    );
  }

  moveAbilityWithinLoadout(index: number, direction: -1 | 1): void {
    if (this.unitLocked()) {
      return;
    }

    const nextIndex = index + direction;
    const current = this.pendingEquippedAbilityIds();
    if (index < 0 || index >= current.length || nextIndex < 0 || nextIndex >= current.length) {
      return;
    }

    const next = [...current];
    moveItemInArray(next, index, nextIndex);
    this.pendingEquippedAbilityIds.set(next);
  }

  handleLoadoutDrop(
    event: CdkDragDrop<
      LoadoutBarViewModel[],
      LoadoutBarViewModel[] | AbilityLoadoutViewModel[],
      LoadoutBarViewModel | AbilityLoadoutViewModel
    >,
  ): void {
    if (this.unitLocked()) {
      return;
    }

    if (event.previousContainer === event.container) {
      const next = [...this.pendingEquippedAbilityIds()];
      moveItemInArray(next, event.previousIndex, event.currentIndex);
      this.pendingEquippedAbilityIds.set(next);
      return;
    }

    const droppedAbility = event.item.data as AbilityLoadoutViewModel | undefined;
    if (!droppedAbility) {
      return;
    }

    this.addAbilityToLoadout(droppedAbility.abilityId, event.currentIndex);
  }

  async saveLoadout(): Promise<void> {
    if (this.unitLocked()) {
      return;
    }

    this.savingLoadout.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.replaceEquippedAbilities(
        this.unitId,
        this.pendingEquippedAbilityIds(),
      );
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Combat loadout updated.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to save loadout.');
    } finally {
      this.savingLoadout.set(false);
    }
  }

  async renameUnit(): Promise<void> {
    if (!this.renameValue.trim() || this.unitLocked()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.renameUnit(this.unitId, this.renameValue.trim());
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.message.set('Unit renamed.');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to rename unit.');
    } finally {
      this.busy.set(false);
    }
  }

  async chooseCapstone(abilityId: string): Promise<void> {
    const unit = this.unit();
    if (!unit || this.unitLocked()) {
      return;
    }

    this.selectingCapstoneId.set(abilityId);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.selectCapstone(unit.id, abilityId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set(`Capstone selected: ${this.abilityDisplayName(abilityId)}.`);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to select capstone.');
    } finally {
      this.selectingCapstoneId.set(null);
    }
  }

  openDicePicker(abilityId: string, abilityName: string, slotIndex: number): void {
    if (this.unitLocked()) {
      return;
    }

    this.pickerState.set({ abilityId, abilityName, slotIndex });
  }

  diceArtStyles(die: DiceRecord | null): ReturnType<typeof resolveDiceArtStyles> | null {
    if (!die) {
      return null;
    }

    return resolveDiceArtStyles(die.rarity, die.sides, 64);
  }

  abilitySlotsFor(abilityId: string): AbilitySlotViewModel | null {
    return this.configurableAbilitySlots().find((entry) => entry.abilityId === abilityId) ?? null;
  }

  closeDicePicker(): void {
    if (this.pickerBusy()) {
      return;
    }

    this.pickerState.set(null);
  }

  isSlotBusy(abilityId: string, slotIndex: number): boolean {
    return this.busySlotKey() === this.slotKey(abilityId, slotIndex);
  }

  async applyDiceSelection(diceId: string | null): Promise<void> {
    const pickerState = this.pickerState();
    if (!pickerState || this.unitLocked()) {
      return;
    }

    this.busySlotKey.set(this.slotKey(pickerState.abilityId, pickerState.slotIndex));
    this.error.set(null);
    this.message.set(null);
    try {
      const response = diceId
        ? await this.unitService.assignAbilitySlotDie(
            this.unitId,
            pickerState.abilityId,
            pickerState.slotIndex,
            diceId,
          )
        : await this.unitService.clearAbilitySlotDie(this.unitId, pickerState.abilityId, pickerState.slotIndex);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set(diceId ? 'Die assigned to slot.' : 'Slot cleared.');
      this.pickerState.set(null);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to update slot.');
    } finally {
      this.busySlotKey.set(null);
    }
  }

  handlePortraitError(): void {
    this.portraitLoadFailed.set(true);
  }

  private findUnitAbilityBinding(
    unit: UnitRecord | null,
    abilityId: string,
    slotIndex: number,
  ): UnitAbilityDieRecord | null {
    if (!unit) {
      return null;
    }

    return (
      unit.ability_dice?.find(
        (binding) => binding.ability_id === abilityId && binding.slot_index === slotIndex,
      ) ?? null
    );
  }

  private findDice(diceId: string | null): DiceRecord | null {
    if (!diceId) {
      return null;
    }

    return this.dice().find((die) => die.id === diceId) ?? null;
  }

  private findDiceAssignment(diceId: string): DiceAssignmentRecord | null {
    for (const unit of this.units()) {
      for (const binding of unit.ability_dice ?? []) {
        if (binding.dice_instance_id === diceId) {
          return {
            unitId: unit.id,
            abilityId: binding.ability_id,
            slotIndex: binding.slot_index,
            diceId,
          };
        }
      }
    }

    return null;
  }

  abilityDisplayName(abilityId: string | null | undefined): string {
    return resolveAbilityDisplayName(abilityId, this.abilityCatalog());
  }

  abilityShortDescription(abilityId: string | null | undefined): string {
    const normalized = normalizeAbilityId(abilityId);
    if (!normalized) {
      return 'No description available.';
    }

    return this.abilityCatalog().get(normalized)?.short_desc ?? 'No description available.';
  }

  private slotKey(abilityId: string, slotIndex: number): string {
    return `${abilityId}:${slotIndex}`;
  }

  private normalizeKinSummary(value: string | null | undefined): string {
    const normalized = value?.trim();
    if (!normalized || normalized.toLowerCase() === 'no splice modifier.') {
      return 'No kin trait.';
    }

    return normalized.replace(/\bsplice modifier\b/gi, 'kin trait');
  }
}


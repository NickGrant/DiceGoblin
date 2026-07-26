import { Component, computed, effect, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {
  AcademyUnitUnlockItem,
  PromotionOptionRecord,
  PromotionOptionsData,
  UnitCapstoneChoiceRecord,
  UnitRecord,
  UnitUnlockedAbilityRecord,
} from '../../core/models/api.models';
import { AcademyService } from '../../core/services/academy/academy.service';
import { SessionService } from '../../core/services/session/session.service';
import { UnitService } from '../../core/services/unit/unit.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { resolveAbilityDisplayName, summarizeAbilityNames, toRomanNumeral } from '../../shared/utils/unit-formatters';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';

@Component({
  selector: 'app-academy-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, PageFrameComponent, FormsModule],
  templateUrl: './academy-page.component.html',
  styleUrl: './academy-page.component.scss',
})
export class AcademyPageComponent {
  private static readonly UNIT_UNLOCK_DESCRIPTIONS: Record<string, string> = {
    frontline_bruiser_t1: 'A durable frontliner built to absorb hits and keep pressure on the enemy line.',
    frontline_bruiser_t2: 'An upgraded bruiser branch that leans into heavier execution damage and frontline pressure.',
    frontline_pit_fighter_t2: 'A risky brawler branch that cashes in on wounded states, counters, and comeback turns.',
    frontline_guardian_t1: 'A shield-first defender that trades damage for stronger protection and staying power.',
    frontline_guardian_t2: 'A fortified guardian branch that specializes in tanking, guard conversion, and line-holding.',
    frontline_shieldbreaker_t2: 'An anti-armor frontline branch built to crack defenses open for the squad.',
    backline_marksman_t1: 'A ranged damage dealer that thrives from the back row with steady offensive pressure.',
    backline_marksman_t2: 'A precision ranged branch focused on single-target removal and armor-piercing shots.',
    backline_trapper_t2: 'A utility archer branch built around marks, setup tools, and treasure sense.',
    support_banner_t1: 'A support specialist that reinforces nearby allies and helps the squad endure longer fights.',
    support_banner_t2: 'An offensive support branch that turns team tempo and buffs into aggressive momentum.',
    support_mascot_t2: 'A chaotic support branch that spreads luck, morale swings, and scrappy clutch bonuses.',
    control_saboteur_t1: 'A disruptive skirmisher focused on interference, control, and breaking enemy momentum.',
    control_saboteur_t2: 'A sharper control branch that punishes compromised enemies with precise follow-up pressure.',
    control_plaguehand_t2: 'A poison-heavy control branch that spreads weakness and softens multiple targets at once.',
  };

  private readonly route = inject(ActivatedRoute);
  private readonly academyService = inject(AcademyService);
  private readonly sessionService = inject(SessionService);
  private readonly unitService = inject(UnitService);

  readonly units = this.sessionService.units;
  readonly profile = this.sessionService.profile;
  readonly activeSquad = this.sessionService.activeSquad;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly selectedUnitId = signal(this.route.snapshot.queryParamMap.get('unitId') ?? '');
  readonly promotionOptions = signal<PromotionOptionRecord[]>([]);
  readonly promotionContext = signal<PromotionOptionsData | null>(null);
  readonly unitUnlockCatalog = signal<AcademyUnitUnlockItem[]>([]);
  readonly selectedSecondaries = signal<string[]>([]);
  readonly selectedDestination = signal<string>('');
  readonly selectedCapstoneChoice = signal<string>('');
  readonly busy = signal(false);
  readonly unlockingUnitTypeSlug = signal<string | null>(null);
  readonly selectingCapstoneId = signal<string | null>(null);
  readonly loadingOptions = signal(false);
  readonly loadingUnlocks = signal(false);
  readonly error = signal<string | null>(null);
  readonly message = signal<string | null>(null);

  readonly selectedUnit = computed<UnitRecord | null>(
    () => this.units().find((entry) => entry.id === this.selectedUnitId()) ?? null,
  );
  readonly unitLocked = computed(() => {
    const unit = this.selectedUnit();
    if (!unit) {
      return false;
    }

    return !!unit.locked || (this.hasActiveRun() && !!this.activeSquad()?.unit_ids?.includes(unit.id));
  });
  readonly promotableUnits = computed(() => {
    const units = this.units();
    return units
      .filter((unit) => {
        if (!unit.promotion_eligible) {
          return false;
        }

        const candidateCount = units.filter((candidate) => {
          if (candidate.id === unit.id) {
            return false;
          }

          return (
            candidate.unit_type_id === unit.unit_type_id &&
            candidate.tier === unit.tier &&
            !!candidate.promotion_eligible
          );
        }).length;

        return candidateCount >= 2;
      })
      .slice()
      .sort((left, right) => left.name.localeCompare(right.name));
  });
  readonly eligiblePromotionCandidates = computed(() => {
    const unit = this.selectedUnit();
    if (!unit) {
      return [];
    }

    return this.units().filter((candidate) => {
      if (candidate.id === unit.id) {
        return false;
      }

      return (
        candidate.unit_type_id === unit.unit_type_id &&
        candidate.tier === unit.tier &&
        !!candidate.promotion_eligible
      );
    });
  });
  readonly availableUnitUnlocks = computed(() => this.unitUnlockCatalog().filter((entry) => !entry.is_unlocked));
  readonly selectedPromotionOption = computed(() => {
    const selectedDestination = this.selectedDestination();
    return this.promotionOptions().find((option) => option.target_unit_type_id === selectedDestination) ?? null;
  });
  readonly capstoneChoices = computed<UnitCapstoneChoiceRecord[]>(() => this.promotionContext()?.capstone_choices ?? []);
  readonly selectedCapstone = computed(() => this.promotionContext()?.selected_capstone ?? null);
  readonly currentCapstoneState = computed(() => this.promotionContext()?.current_capstone_state ?? 'none');
  readonly inheritedPassives = computed<UnitUnlockedAbilityRecord[]>(() => this.selectedUnit()?.inherited_passive_abilities ?? []);
  readonly mustChooseCapstoneBeforePromotion = computed(() =>
    this.currentCapstoneState() === 'ready_to_select' && !this.selectedCapstone(),
  );

  constructor() {
    void this.loadUnitUnlockCatalog();

    effect(() => {
      const units = this.promotableUnits();
      if (!units.length) {
        this.selectedUnitId.set('');
        return;
      }

      const selectedId = this.selectedUnitId();
      if (selectedId && units.some((unit) => unit.id === selectedId)) {
        return;
      }

      this.selectedUnitId.set('');
    });

    effect(() => {
      const unitId = this.selectedUnitId();
      this.selectedSecondaries.set([]);
      this.selectedDestination.set('');
      this.promotionOptions.set([]);
      this.promotionContext.set(null);
      this.selectedCapstoneChoice.set('');

      if (!unitId) {
        return;
      }

      void this.loadPromotionOptions(unitId);
    });
  }

  async loadPromotionOptions(unitId: string): Promise<void> {
    this.loadingOptions.set(true);
    this.error.set(null);
    try {
      const response = await this.unitService.getPromotionOptions(unitId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.promotionContext.set(response.data);
      const options = response.data.options ?? [];
      this.promotionOptions.set(options);
      this.selectedDestination.set(options[0]?.target_unit_type_id ?? '');
      this.selectedCapstoneChoice.set(response.data.selected_capstone?.ability_id ?? '');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load promotion options.');
    } finally {
      this.loadingOptions.set(false);
    }
  }

  async loadUnitUnlockCatalog(): Promise<void> {
    this.loadingUnlocks.set(true);
    this.error.set(null);
    try {
      const response = await this.academyService.getCatalog();
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.unitUnlockCatalog.set(response.data.unit_unlocks ?? []);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load unit unlocks.');
    } finally {
      this.loadingUnlocks.set(false);
    }
  }

  abilityDisplayName(abilityId: string | null | undefined): string {
    return resolveAbilityDisplayName(abilityId);
  }

  summarizeAbilityList(abilityIds: string[] | null | undefined): string {
    return summarizeAbilityNames(abilityIds);
  }

  summarizeInheritedPassives(abilities: UnitUnlockedAbilityRecord[] | null | undefined): string {
    if (!abilities?.length) {
      return 'None yet';
    }

    return summarizeAbilityNames(abilities.map((ability) => ability.ability_id));
  }

  currentCapstoneCopy(state: string): string {
    return {
      none: 'This class has no capstone to choose.',
      unearned: 'Promoting now will skip this class capstone because the unit has not mastered the class yet.',
      ready_to_select: 'This unit is mastered. Choose one capstone before confirming promotion.',
      selected: 'This unit already locked in a capstone and will carry it into the next class.',
    }[state] ?? 'Capstone state unavailable.';
  }

  toggleSecondary(unitId: string): void {
    if (this.unitLocked()) {
      return;
    }

    const next = new Set(this.selectedSecondaries());
    if (next.has(unitId)) {
      next.delete(unitId);
    } else if (next.size < 2) {
      next.add(unitId);
    }
    this.selectedSecondaries.set(Array.from(next));
  }

  promotionOptionLabel(option: PromotionOptionRecord): string {
    if (option.mode === 'sideways') {
      if (option.target_unit_type_name === option.branch_unit_type_name) {
        return `${option.target_unit_type_name} - sideways`;
      }

      return `${option.target_unit_type_name} - sideways via ${option.branch_unit_type_name}`;
    }

    return `${option.target_unit_type_name} - chain`;
  }

  async promoteUnit(): Promise<void> {
    const unit = this.selectedUnit();
    if (!unit || this.unitLocked()) {
      return;
    }

    if (this.selectedSecondaries().length !== 2) {
      this.error.set('Choose two units to consume.');
      return;
    }

    if (this.mustChooseCapstoneBeforePromotion()) {
      this.error.set('Choose a capstone for this mastered unit before confirming promotion.');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.unitService.promoteUnit(
        unit.id,
        [this.selectedSecondaries()[0], this.selectedSecondaries()[1]],
        this.selectedDestination() || undefined,
      );
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.selectedSecondaries.set([]);
      this.message.set('Promotion complete.');
      await this.loadPromotionOptions(unit.id);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to promote unit.');
    } finally {
      this.busy.set(false);
    }
  }

  async chooseCapstone(abilityId: string): Promise<void> {
    const unit = this.selectedUnit();
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

      this.selectedCapstoneChoice.set(abilityId);
      this.message.set(`Capstone selected: ${this.abilityDisplayName(abilityId)}.`);
      await this.loadPromotionOptions(unit.id);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to select capstone.');
    } finally {
      this.selectingCapstoneId.set(null);
    }
  }

  async unlockUnitType(unitTypeSlug: string): Promise<void> {
    this.unlockingUnitTypeSlug.set(unitTypeSlug);
    this.error.set(null);
    this.message.set(null);
    try {
      const response = await this.academyService.unlockUnitType(unitTypeSlug);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set('Unit type unlocked.');
      await this.loadUnitUnlockCatalog();
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to unlock unit type.');
    } finally {
      this.unlockingUnitTypeSlug.set(null);
    }
  }

  roleLabel(value: string | null | undefined): string {
    const normalized = (value ?? '').trim().toLowerCase();
    return normalized.length ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : 'Unit';
  }

  unitUnlockStats(entry: AcademyUnitUnlockItem): Array<{ label: string; value: string }> {
    return [
      { label: 'ATK', value: this.statValue(entry.total_attack) },
      { label: 'DEF', value: this.statValue(entry.total_defense) },
      { label: 'PRC', value: this.statValue(entry.total_precision) },
      { label: 'RES', value: this.statValue(entry.total_resolve) },
      { label: 'HP', value: this.statValue(entry.max_hp) },
    ];
  }

  unitUnlockDescription(unitTypeSlug: string): string {
    return AcademyPageComponent.UNIT_UNLOCK_DESCRIPTIONS[unitTypeSlug] ?? 'Unlock this unit type for future recruitment opportunities.';
  }

  unitUnlockRequirementLabel(entry: AcademyUnitUnlockItem): string {
    const unmet = (entry.requirements ?? []).find((requirement) => !requirement.is_met);
    if (unmet) {
      const current = unmet.progress_current;
      const target = unmet.progress_target;
      const progress =
        typeof current === 'number' && typeof target === 'number'
          ? ` (${Math.max(0, current)}/${Math.max(0, target)})`
          : '';
      return `Requires: ${unmet.label}${progress}`;
    }

    return `Tier ${this.unitUnlockTierLabel(entry.unit_type_slug)} ${this.roleLabel(entry.role)} - adds future recruit and reward drops.`;
  }

  unitUnlockActionLabel(entry: AcademyUnitUnlockItem): string {
    if (this.unlockingUnitTypeSlug() === entry.unit_type_slug) {
      return 'Working...';
    }
    if (entry.is_available === false) {
      return 'Locked';
    }

    const missingTeeth = entry.cost - profileSoftCurrency(this.profile());
    if (missingTeeth > 0) {
      return `Need ${missingTeeth} teeth`;
    }

    return 'Unlock';
  }

  unitUnlockDisabled(entry: AcademyUnitUnlockItem): boolean {
    return (
      this.unlockingUnitTypeSlug() === entry.unit_type_slug ||
      profileSoftCurrency(this.profile()) < entry.cost ||
      entry.is_available === false
    );
  }

  toRomanNumeral(value: number | null | undefined): string {
    return toRomanNumeral(value);
  }

  private statValue(value: number | null | undefined): string {
    return typeof value === 'number' ? `${value}` : '-';
  }

  private unitUnlockTierLabel(unitTypeSlug: string): string {
    const match = unitTypeSlug.match(/_t(\d+)$/i);
    return match ? toRomanNumeral(Number(match[1])) : 'I';
  }
}

function profileSoftCurrency(profile: { softCurrency?: number } | null | undefined): number {
  return Math.max(0, profile?.softCurrency ?? 0);
}

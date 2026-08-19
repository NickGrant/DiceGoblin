import { Component, computed, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CurrentRunData, ObjectiveRecord, ProfileData, RegionRecord, UnitRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { RunService } from '../../core/services/run/run.service';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { DgChipDirective } from '../../shared/ui/dg-chip/dg-chip.directive';
import { DgProgressComponent } from '../../shared/ui/dg-progress/dg-progress.component';
import { UnitThumbnailComponent } from '../../shared/ui/unit-thumbnail/unit-thumbnail.component';
import { resolveRunRegionBackgroundUrl } from '../../core/regions/region-catalog';
import { formatUnitKinLabel } from '../../shared/utils/unit-formatters';

type DashboardAction = {
  eyebrow: string;
  title: string;
  body: string;
  route: string;
  cta: string;
};

type CommandTile = {
  label: string;
  detail: string;
  route: string;
  icon: string;
};

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink, PageFrameComponent, DgChipDirective, DgProgressComponent, UnitThumbnailComponent],
  templateUrl: './home-page.component.html',
  styleUrl: './home-page.component.scss',
})
export class HomePageComponent {
  private readonly sessionService = inject(SessionService);
  private readonly runService = inject(RunService);
  private loadedRunId: string | null = null;

  readonly profileData = this.sessionService.profileData;
  readonly profile = this.sessionService.profile;
  readonly session = this.sessionService.session;
  readonly shopUnlocked = this.sessionService.shopUnlocked;
  readonly academyUnlocked = this.sessionService.academyUnlocked;
  readonly wrongMachineUnlocked = this.sessionService.wrongMachineUnlocked;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly activeSquad = this.sessionService.activeSquad;
  readonly squadUnitCap = this.sessionService.squadUnitCap;
  readonly units = this.sessionService.units;
  readonly dice = this.sessionService.dice;
  readonly currentRunData = signal<CurrentRunData | null>(null);
  readonly runProgressLoading = signal(false);
  readonly runProgressError = signal<string | null>(null);

  readonly primaryRoute = computed(() => (this.hasActiveRun() ? '/run/map' : '/regions'));
  readonly subtitle = computed(() =>
    this.hasActiveRun()
      ? 'Your raiders are already in the field. Patch the squad up and get them back to work.'
      : 'Prep the warband, sharpen the dice, and send the crew out hunting for loot.',
  );
  readonly activeSquadUnits = computed(() => {
    const activeSquad = this.activeSquad();
    const unitsById = new Map(this.units().map((unit) => [unit.id, unit]));
    return (activeSquad?.unit_ids ?? [])
      .map((unitId) => unitsById.get(unitId) ?? null)
      .filter((unit): unit is NonNullable<typeof unit> => unit !== null);
  });
  readonly activeRun = computed(() => this.profileData()?.active_run ?? null);
  readonly heroBackgroundUrl = computed(() =>
    resolveRunRegionBackgroundUrl(this.activeRun()) ?? '/assets/ui/biome/mystic_cave.png',
  );
  readonly heroRegionLabel = computed(() => this.biomeLabel(this.activeRun()?.region_name ?? this.activeRun()?.region_slug));
  readonly heroTitle = computed(() =>
    this.hasActiveRun() ? `Raid the ${this.heroRegionLabel()}` : 'Choose the Next Raid',
  );
  readonly heroEyebrow = computed(() => (this.hasActiveRun() ? 'Active Run' : 'Camp Ready'));
  readonly heroCta = computed(() => (this.hasActiveRun() ? 'Continue Run' : 'Start Run'));
  readonly heroEnergyLabel = computed(() => {
    const cost = this.activeRun()?.energy_cost;
    return typeof cost === 'number' ? `${cost} energy cost` : 'Energy ready';
  });
  readonly runNodeLabel = computed(() => {
    if (!this.hasActiveRun()) {
      return 'No active run';
    }

    const nodes = this.currentRunData()?.map?.nodes ?? [];
    if (this.runProgressLoading() && !nodes.length) {
      return 'Loading nodes';
    }
    if (!nodes.length) {
      return 'Node --/--';
    }

    const sorted = [...nodes].sort((left, right) => left.node_index - right.node_index);
    const current = sorted.find((node) => node.status === 'available')
      ?? sorted.find((node) => node.status !== 'cleared')
      ?? sorted.at(-1);
    const currentPosition = current ? sorted.indexOf(current) + 1 : 1;

    return `Node ${currentPosition}/${sorted.length}`;
  });
  readonly profileObjectives = computed(() => this.profileData()?.objectives ?? []);
  readonly currentObjective = computed(
    () => this.profileObjectives().find((objective) => objective.status !== 'complete') ?? null,
  );
  readonly completedObjectives = computed(() =>
    this.profileObjectives().filter((objective) => objective.status === 'complete'),
  );
  readonly nextRegion = computed(() => this.findNextRegion(this.profileData()));
  readonly nextProgressionAction = computed<DashboardAction>(() =>
    this.resolveNextProgressionAction(),
  );
  readonly squadStatusLabel = computed(() => {
    const assigned = this.activeSquadUnits().length;
    const cap = this.sessionService.squadUnitCap();

    if (!this.activeSquad()) {
      return 'No active squad';
    }

    return assigned >= cap
      ? `${assigned}/${cap} squad full`
      : `${assigned}/${cap} squad slots filled`;
  });
  readonly regionProgressLabel = computed(() => {
    const profile = this.profileData();
    const regions = profile?.regions?.filter((region) => region.is_enabled) ?? [];
    const completed = regions.filter((region) => region.is_completed).length;

    if (!regions.length) {
      return 'Regions loading';
    }

    return `${completed}/${regions.length} regions cleared`;
  });
  readonly unlockProgressLabel = computed(() => {
    const featureUnlockCount = this.profileData()?.feature_unlocks?.length ?? 0;
    return `${featureUnlockCount} features unlocked`;
  });
  readonly commandTiles = computed<CommandTile[]>(() => [
    {
      label: 'Warband',
      detail: `${this.units().length} units recruited`,
      route: '/warband',
      icon: '/assets/ui/icons/icon_warband.png',
    },
    {
      label: 'Inventory',
      detail: `${this.dice().length} dice`,
      route: '/dice',
      icon: '/assets/ui/icons/icon_inventory.png',
    },
    {
      label: 'Shop',
      detail: 'Tooth Collector',
      route: '/shop',
      icon: '/assets/ui/icons/icon_shop.png',
    },
    {
      label: 'Academy',
      detail: `${this.sessionService.unitTypeUnlocks().length} unlocks made`,
      route: '/academy',
      icon: '/assets/ui/icons/icon_guide.png',
    },
    {
      label: 'Wrong Machine',
      detail: 'Pig Kin results',
      route: '/wrong-machine',
      icon: '/assets/ui/icons/icon_encounter_locked.png',
    },
    {
      label: 'Codex',
      detail: 'Lore & records',
      route: '/codex',
      icon: '/assets/ui/icons/icon_guide.png',
    },
  ]);

  constructor() {
    effect(() => {
      const runId = this.activeRun()?.run_id ?? null;
      if (!runId) {
        this.loadedRunId = null;
        this.currentRunData.set(null);
        this.runProgressError.set(null);
        this.runProgressLoading.set(false);
        return;
      }

      if (this.loadedRunId === runId) {
        return;
      }

      this.loadedRunId = runId;
      void this.loadCurrentRun(runId);
    });
  }

  rewardLabelForUnitCount(): string {
    const count = this.activeSquadUnits().length;
    return `${count} ${count === 1 ? 'raider' : 'raiders'} ready`;
  }

  unitHp(unit: UnitRecord): number {
    return Math.max(0, unit.current_hp ?? unit.max_hp ?? 0);
  }

  unitXpToNext(unit: UnitRecord): number {
    return Math.max(0, unit.xp_to_next_level ?? 0);
  }

  unitXpMax(unit: UnitRecord): number {
    const xp = Math.max(0, unit.xp ?? 0);
    return xp + this.unitXpToNext(unit);
  }

  unitKinLabel(unit: UnitRecord): string {
    return formatUnitKinLabel(unit);
  }

  rawChaosBalance(): number {
    return this.profileData()?.currency.raw_chaos ?? 0;
  }

  private async loadCurrentRun(runId: string): Promise<void> {
    this.runProgressLoading.set(true);
    this.runProgressError.set(null);

    try {
      const response = await this.runService.getCurrentRun();
      if (this.loadedRunId !== runId) {
        return;
      }

      if (response.ok) {
        this.currentRunData.set(response.data);
        return;
      }

      this.currentRunData.set(null);
      this.runProgressError.set(response.error.message);
    } catch (error) {
      if (this.loadedRunId !== runId) {
        return;
      }

      this.currentRunData.set(null);
      this.runProgressError.set(error instanceof Error ? error.message : 'Unable to load run progress.');
    } finally {
      if (this.loadedRunId === runId) {
        this.runProgressLoading.set(false);
      }
    }
  }

  private resolveNextProgressionAction(): DashboardAction {
    const profile = this.profileData();
    const objective = this.currentObjective();
    if (objective) {
      return {
        eyebrow: 'Current Objective',
        title: objective.title,
        body: this.objectiveBody(objective),
        route: objective.route,
        cta: this.ctaForObjectiveRoute(objective.route),
      };
    }

    const activeRun = this.activeRun();
    const activeSquadUnits = this.activeSquadUnits();
    const promotionReadyUnit = this.findPromotionReadyUnit(activeSquadUnits);
    const capstoneReadyUnit = this.findCapstoneReadyUnit(activeSquadUnits);
    const nextRegion = this.nextRegion();

    if (activeRun) {
      return {
        eyebrow: 'Current Objective',
        title: `Continue ${activeRun.region_name ?? 'the active run'}`,
        body: 'A run is already underway. Finish the route before reshuffling the warband.',
        route: '/run/map',
        cta: 'Open Map',
      };
    }

    if (!this.activeSquad() || activeSquadUnits.length === 0) {
      return {
        eyebrow: 'Squad Needed',
        title: 'Assign raiders before launching',
        body: 'The next raid needs at least one active squad member ready in formation.',
        route: '/warband',
        cta: 'Manage Squad',
      };
    }

    if (capstoneReadyUnit) {
      return {
        eyebrow: 'Power Spike',
        title: `${capstoneReadyUnit.name} has a capstone ready`,
        body: 'Lock in the capstone choice before the next serious push.',
        route: this.academyUnlocked() ? '/academy' : `/warband/units/${capstoneReadyUnit.id}`,
        cta: this.academyUnlocked() ? 'Open Academy' : 'Inspect Unit',
      };
    }

    if (promotionReadyUnit) {
      return {
        eyebrow: 'Promotion Ready',
        title: `${promotionReadyUnit.name} can advance`,
        body: this.academyUnlocked()
          ? 'Promote eligible raiders to widen the squad build options.'
          : 'Unlock the Academy to convert earned levels into promotions.',
        route: this.academyUnlocked() ? '/academy' : this.shopUnlocked() ? '/shop' : '/regions',
        cta: this.academyUnlocked()
          ? 'Open Academy'
          : this.shopUnlocked()
            ? 'Visit Shop'
            : 'Run Regions',
      };
    }

    if (nextRegion) {
      return {
        eyebrow: 'Next Region',
        title: `Clear ${nextRegion.name}`,
        body: `Recommended level ${nextRegion.recommended_level}; costs ${nextRegion.energy_cost} energy.`,
        route: '/regions',
        cta: 'Choose Region',
      };
    }

    if (!this.shopUnlocked()) {
      return {
        eyebrow: 'Feature Unlock',
        title: 'Free the Tooth Collector',
        body: 'Defeat The Farm to unlock shop access and start spending teeth.',
        route: '/regions',
        cta: 'Pick Region',
      };
    }

    if (!this.academyUnlocked()) {
      return {
        eyebrow: 'Feature Unlock',
        title: 'Unlock the Academy',
        body: 'The Academy opens promotions, capstones, and deeper warband growth.',
        route: '/shop',
        cta: 'Visit Shop',
      };
    }

    return {
      eyebrow: 'Warband Growth',
      title: 'Tune dice before the next run',
      body: `${profile?.dice?.length ?? 0} dice in inventory. Check loadouts before pushing farther.`,
      route: '/dice',
      cta: 'Open Inventory',
    };
  }

  private findNextRegion(profile: ProfileData | null): RegionRecord | null {
    return (
      profile?.regions
        ?.filter((region) => region.is_enabled && region.is_unlocked && !region.is_completed)
        .sort((left, right) => (left.recommended_level ?? 0) - (right.recommended_level ?? 0))[0] ??
      null
    );
  }

  private findPromotionReadyUnit(units: UnitRecord[]): UnitRecord | null {
    return units.find((unit) => !!unit.promotion_eligible) ?? null;
  }

  private findCapstoneReadyUnit(units: UnitRecord[]): UnitRecord | null {
    return units.find((unit) => unit.current_capstone_state === 'ready_to_select') ?? null;
  }

  objectiveProgressLabel(objective: ObjectiveRecord): string {
    const target = Math.max(1, objective.progress_target);
    const current = Math.min(Math.max(0, objective.progress_current), target);
    return `${current}/${target}`;
  }

  private objectiveBody(objective: ObjectiveRecord): string {
    return `${this.objectiveProgressLabel(objective)} - ${objective.description}`;
  }

  private ctaForObjectiveRoute(route: string): string {
    if (route.startsWith('/run')) {
      return 'Open Map';
    }
    if (route.startsWith('/warband')) {
      return 'Manage Squad';
    }
    if (route.startsWith('/academy')) {
      return 'Open Academy';
    }
    if (route.startsWith('/dice')) {
      return 'Open Inventory';
    }
    if (route.startsWith('/shop')) {
      return 'Visit Shop';
    }

    return 'Choose Region';
  }

  private biomeLabel(value: string | null | undefined): string {
    const normalized = (value ?? 'Mystic Cave')
      .replace(/^the[\s_-]+/i, '')
      .replace(/[_-]+/g, ' ')
      .trim();

    return normalized || 'Mystic Cave';
  }
}

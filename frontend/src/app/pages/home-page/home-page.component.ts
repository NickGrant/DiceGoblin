import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ProfileData, RegionRecord, UnitRecord } from '../../core/models/api.models';
import { SessionService } from '../../core/services/session/session.service';
import { isDevPanelEnabled } from '../../core/config/runtime-config';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitBarComponent } from '../../shared/ui/unit-bar/unit-bar.component';

type DashboardAction = {
  eyebrow: string;
  title: string;
  body: string;
  route: string;
  cta: string;
};

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink, PageFrameComponent, UnitBarComponent],
  templateUrl: './home-page.component.html',
  styleUrl: './home-page.component.scss',
})
export class HomePageComponent {
  private readonly sessionService = inject(SessionService);
  readonly profileData = this.sessionService.profileData;
  readonly shopUnlocked = this.sessionService.shopUnlocked;
  readonly academyUnlocked = this.sessionService.academyUnlocked;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly activeSquad = this.sessionService.activeSquad;
  readonly units = this.sessionService.units;
  readonly devPanelEnabled = isDevPanelEnabled();
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

  rewardLabelForUnitCount(): string {
    const count = this.activeSquadUnits().length;
    return `${count} ${count === 1 ? 'raider' : 'raiders'} ready`;
  }

  private resolveNextProgressionAction(): DashboardAction {
    const profile = this.profileData();
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
}

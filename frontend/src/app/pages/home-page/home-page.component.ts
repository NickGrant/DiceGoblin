import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { isDevPanelEnabled } from '../../core/config/runtime-config';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';
import { UnitBarComponent } from '../../shared/ui/unit-bar/unit-bar.component';

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

  rewardLabelForUnitCount(): string {
    const count = this.activeSquadUnits().length;
    return `${count} ${count === 1 ? 'raider' : 'raiders'} ready`;
  }
}

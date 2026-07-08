import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { isDevPanelEnabled } from '../../core/config/runtime-config';
import { PageFrameComponent } from '../../layout/page-frame/page-frame.component';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [RouterLink, PageFrameComponent],
  templateUrl: './home-page.component.html',
  styleUrl: './home-page.component.scss',
})
export class HomePageComponent {
  private readonly sessionService = inject(SessionService);
  readonly profile = this.sessionService.profile;
  readonly shopUnlocked = this.sessionService.shopUnlocked;
  readonly academyUnlocked = this.sessionService.academyUnlocked;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly devPanelEnabled = isDevPanelEnabled();
  readonly primaryLabel = computed(() => (this.hasActiveRun() ? 'Continue Run' : 'Start Run'));
  readonly primaryRoute = computed(() => (this.hasActiveRun() ? '/run/map' : '/regions'));
  readonly subtitle = computed(() =>
    this.hasActiveRun()
      ? 'Your raiders are already in the field. Patch the squad up and get them back to work.'
      : 'Prep the warband, sharpen the dice, and send the crew out hunting for loot.',
  );
}


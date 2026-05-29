import { Component, computed, inject } from '@angular/core';
import { NgClass } from '@angular/common';
import { RouterLink } from '@angular/router';
import { SessionService } from '../core/services/session.service';
import { isDevPanelEnabled } from '../core/config/runtime-config';

@Component({
  selector: 'app-home-page',
  standalone: true,
  imports: [NgClass, RouterLink],
  templateUrl: './home-page.component.html',
  styleUrl: './home-page.component.scss',
})
export class HomePageComponent {
  private readonly sessionService = inject(SessionService);
  readonly profile = this.sessionService.profile;
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly devPanelEnabled = isDevPanelEnabled();
  readonly primaryLabel = computed(() => (this.hasActiveRun() ? 'Continue Run' : 'Start Run'));
  readonly primaryRoute = computed(() => (this.hasActiveRun() ? '/run/map' : '/regions'));
}

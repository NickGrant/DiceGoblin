import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { resolveApiBaseUrl } from '../../core/config/runtime-config';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';

@Component({
  selector: 'app-landing-page',
  standalone: true,
  imports: [DgCommandBtnDirective, RouterLink],
  templateUrl: './landing-page.component.html',
  styleUrl: './landing-page.component.scss',
})
export class LandingPageComponent {
  readonly loginUrl = `${resolveApiBaseUrl()}/auth/discord/start`;
}


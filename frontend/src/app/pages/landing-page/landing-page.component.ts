import { Component, OnInit, inject } from '@angular/core';
import { Router } from '@angular/router';
import { resolveApiBaseUrl } from '../../core/config/runtime-config';
import { SessionService } from '../../core/services/session/session.service';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';

@Component({
  selector: 'app-landing-page',
  standalone: true,
  imports: [DgCommandBtnDirective],
  templateUrl: './landing-page.component.html',
  styleUrl: './landing-page.component.scss',
})
export class LandingPageComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly sessionService = inject(SessionService);

  readonly loginUrl = `${resolveApiBaseUrl()}/auth/discord/start`;

  async ngOnInit(): Promise<void> {
    await this.sessionService.initialize();

    if (this.sessionService.session().isAuthenticated) {
      await this.router.navigateByUrl('/home');
    }
  }
}


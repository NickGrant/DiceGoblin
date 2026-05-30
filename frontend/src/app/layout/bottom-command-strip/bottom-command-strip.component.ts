import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';

@Component({
  selector: 'app-bottom-command-strip',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  host: {
    style: 'display: block;',
  },
  templateUrl: './bottom-command-strip.component.html',
  styleUrl: './bottom-command-strip.component.scss',
})
export class BottomCommandStripComponent {
  private readonly sessionService = inject(SessionService);

  readonly session = this.sessionService.session;
  readonly profile = this.sessionService.profile;

  async logout(): Promise<void> {
    await this.sessionService.logout();
  }
}

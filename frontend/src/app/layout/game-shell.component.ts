import { Component, OnInit, inject } from '@angular/core';
import { NgClass, NgIf } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { SessionService } from '../core/services/session.service';

@Component({
  selector: 'app-game-shell',
  standalone: true,
  imports: [NgClass, NgIf, RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './game-shell.component.html',
  styleUrl: './game-shell.component.scss',
})
export class GameShellComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly sessionService = inject(SessionService);
  readonly session = this.sessionService.session;
  readonly profile = this.sessionService.profile;
  readonly isLoading = this.sessionService.isLoading;
  readonly error = this.sessionService.error;

  ngOnInit(): void {
    void this.sessionService.initialize();
  }

  async logout(): Promise<void> {
    await this.sessionService.logout();
    await this.router.navigateByUrl('/login');
  }
}

import { Component, inject, signal } from '@angular/core';
import { NgClass, NgIf } from '@angular/common';
import { Router } from '@angular/router';
import { RunService } from '../core/services/run.service';
import { SessionService } from '../core/services/session.service';

@Component({
  selector: 'app-regions-page',
  standalone: true,
  imports: [NgClass, NgIf],
  templateUrl: './regions-page.component.html',
  styleUrl: './regions-page.component.scss',
})
export class RegionsPageComponent {
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);

  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly isStarting = signal(false);
  readonly message = signal<string | null>(null);
  readonly error = signal<string | null>(null);

  async startFarmRun(): Promise<void> {
    this.isStarting.set(true);
    this.message.set(null);
    this.error.set(null);

    try {
      const response = await this.runService.createRun(1);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }

      this.message.set('Run started.');
      await this.router.navigateByUrl('/run/map');
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to start run.');
    } finally {
      this.isStarting.set(false);
    }
  }

  async continueRun(): Promise<void> {
    await this.router.navigateByUrl('/run/map');
  }
}

import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { RunService } from '../../core/services/run/run.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

@Component({
  selector: 'app-run-summary-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent, RouterLink],
  templateUrl: './run-summary-page.component.html',
  styleUrl: './run-summary-page.component.scss',
})
export class RunSummaryPageComponent {
  private readonly runService = inject(RunService);
  readonly summary = this.runService.summary;
  readonly statusLabel = computed(() => this.humanize(this.summary()?.status ?? 'summary'));
  readonly rewardCount = computed(() => this.summary()?.rewards.length ?? 0);
  readonly survivorCount = computed(() => this.summary()?.survivors.length ?? 0);
  readonly defeatedCount = computed(() => this.summary()?.defeated.length ?? 0);
  readonly progressionCount = computed(() => this.summary()?.progression.length ?? 0);

  private humanize(value: string): string {
    return value
      .split(/[_\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }
}


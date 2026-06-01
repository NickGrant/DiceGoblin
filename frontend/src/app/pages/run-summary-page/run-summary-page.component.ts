import { Component, inject } from '@angular/core';
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
}


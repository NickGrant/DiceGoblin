import { NgFor, NgIf } from '@angular/common';
import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { RunService } from '../core/services/run.service';

@Component({
  selector: 'app-run-summary-page',
  standalone: true,
  imports: [NgFor, NgIf, RouterLink],
  templateUrl: './run-summary-page.component.html',
  styleUrl: './run-summary-page.component.scss',
})
export class RunSummaryPageComponent {
  private readonly runService = inject(RunService);
  readonly summary = this.runService.summary;
}

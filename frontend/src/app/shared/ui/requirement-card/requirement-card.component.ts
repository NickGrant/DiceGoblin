import { Component, input } from '@angular/core';
import { DgProgressComponent } from '../dg-progress/dg-progress.component';

export type DgRequirementTone = 'neutral' | 'met' | 'blocked' | 'pending';

@Component({
  selector: 'dg-requirement-card',
  standalone: true,
  imports: [DgProgressComponent],
  templateUrl: './requirement-card.component.html',
  styleUrl: './requirement-card.component.scss',
  host: {
    '[attr.data-dg-requirement]': 'tone()',
    '[attr.data-met]': 'met() ? "" : null',
  },
})
export class RequirementCardComponent {
  readonly label = input.required<string>();
  readonly value = input('');
  readonly detail = input('');
  readonly current = input(0);
  readonly max = input(0);
  readonly met = input(false);
  readonly tone = input<DgRequirementTone>('neutral');
  readonly showProgress = input(true);
}

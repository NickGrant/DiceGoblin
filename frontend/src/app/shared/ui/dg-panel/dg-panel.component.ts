import { Component, input, output } from '@angular/core';
import { DgButtonDirective } from '../dg-button/dg-button.directive';

export type DgPanelVariant = 'content' | 'surface' | 'tinted';

@Component({
  selector: 'dg-panel',
  standalone: true,
  imports: [DgButtonDirective],
  templateUrl: './dg-panel.component.html',
  styleUrl: './dg-panel.component.scss',
  host: {
    '[attr.data-dg-panel]': 'variant()',
  },
})
export class DgPanelComponent {
  readonly variant = input<DgPanelVariant>('content');
  readonly heading = input('');
  readonly actionLabel = input('');
  readonly actionAriaLabel = input('');

  readonly action = output<void>();
}

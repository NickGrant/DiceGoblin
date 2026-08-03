import { Component, input, output } from '@angular/core';
import { CdkTrapFocus } from '@angular/cdk/a11y';
import { DgButtonDirective } from '../dg-button/dg-button.directive';

@Component({
  selector: 'dg-confirm-modal',
  standalone: true,
  imports: [CdkTrapFocus, DgButtonDirective],
  templateUrl: './confirm-modal.component.html',
  styleUrl: './confirm-modal.component.scss',
})
export class ConfirmModalComponent {
  readonly open = input(false);
  readonly title = input('Confirm');
  readonly message = input('');
  readonly confirmLabel = input('Confirm');
  readonly cancelLabel = input('Cancel');
  readonly busy = input(false);
  readonly tone = input<'default' | 'danger'>('default');

  readonly confirmed = output<void>();
  readonly dismissed = output<void>();

  confirm(): void {
    this.confirmed.emit();
  }

  close(): void {
    this.dismissed.emit();
  }
}

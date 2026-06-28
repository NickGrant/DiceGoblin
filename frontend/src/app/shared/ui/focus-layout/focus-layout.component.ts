import { Component, input } from '@angular/core';

@Component({
  selector: 'dg-focus-layout',
  standalone: true,
  templateUrl: './focus-layout.component.html',
  styleUrl: './focus-layout.component.scss',
})
export class FocusLayoutComponent {
  readonly panelWidth = input('31.125rem');
  readonly stickyPanel = input(true);
}

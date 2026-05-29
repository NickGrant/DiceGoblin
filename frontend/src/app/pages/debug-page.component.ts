import { Component } from '@angular/core';

@Component({
  selector: 'app-debug-page',
  standalone: true,
  templateUrl: './placeholder-page.component.html',
  styleUrl: './placeholder-page.component.scss',
})
export class DebugPageComponent {
  readonly title = 'Debug Panel';
  readonly eyebrow = 'Route / Debug';
  readonly summary = 'Coming soon.';
}

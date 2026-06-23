import { Component, OnInit, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { SessionService } from './core/services/session/session.service';
import { CommandControlsComponent } from './layout/command-controls/command-controls.component';

@Component({
  selector: 'app-root',
  imports: [CommandControlsComponent, RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App implements OnInit {
  private readonly sessionService = inject(SessionService);
  readonly isLoading = this.sessionService.isLoading;
  readonly error = this.sessionService.error;

  ngOnInit(): void {
    void this.sessionService.initialize();
  }
}

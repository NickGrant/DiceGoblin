import { Component, OnInit, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';
import { BottomCommandStripComponent } from '../bottom-command-strip/bottom-command-strip.component';

@Component({
  selector: 'app-game-shell',
  standalone: true,
  imports: [BottomCommandStripComponent, RouterOutlet],
  templateUrl: './game-shell.component.html',
  styleUrl: './game-shell.component.scss',
})
export class GameShellComponent implements OnInit {
  private readonly sessionService = inject(SessionService);
  readonly isLoading = this.sessionService.isLoading;
  readonly error = this.sessionService.error;

  ngOnInit(): void {
    void this.sessionService.initialize();
  }
}


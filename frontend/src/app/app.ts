import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { resolveRouteAudioContext } from './core/audio/route-audio';
import { AudioDirectorService } from './core/services/audio/audio-director.service';
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
  private readonly audioDirector = inject(AudioDirectorService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly isLoading = this.sessionService.isLoading;
  readonly error = this.sessionService.error;

  ngOnInit(): void {
    this.audioDirector.initialize();
    this.audioDirector.setRouteContext(resolveRouteAudioContext(this.router.routerState.snapshot.root));
    this.router.events
      .pipe(
        filter((event): event is NavigationEnd => event instanceof NavigationEnd),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(() => {
        this.audioDirector.setRouteContext(resolveRouteAudioContext(this.router.routerState.snapshot.root));
      });

    void this.sessionService.initialize();
  }
}

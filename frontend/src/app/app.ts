import { Component, DestroyRef, OnInit, computed, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { resolveRouteAudioContext } from './core/audio/route-audio';
import {
  publishDebugCaptureState,
  readDebugCaptureRequest,
  resolveDebugCaptureRoute,
} from './core/debug/debug-capture';
import { AudioDirectorService } from './core/services/audio/audio-director.service';
import { SessionService } from './core/services/session/session.service';
import { ViewportOrientationService } from './core/services/viewport/viewport-orientation.service';
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
  private readonly viewportOrientation = inject(ViewportOrientationService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly debugCaptureRequest = readDebugCaptureRequest();

  readonly isLoading = this.sessionService.isLoading;
  readonly error = this.sessionService.error;
  readonly isAuthenticated = computed(() => this.sessionService.session().isAuthenticated);
  readonly isLandscapeGateActive = this.viewportOrientation.isLandscapeGateActive;

  ngOnInit(): void {
    this.initializeDebugCaptureState();
    this.audioDirector.initialize();
    this.viewportOrientation.initialize();
    this.audioDirector.setRouteContext(resolveRouteAudioContext(this.router.routerState.snapshot.root));
    this.router.events
      .pipe(
        filter((event): event is NavigationEnd => event instanceof NavigationEnd),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(() => {
        this.audioDirector.setRouteContext(resolveRouteAudioContext(this.router.routerState.snapshot.root));
        this.syncDebugCaptureState();
      });

    void this.initializeShell();
  }

  private async initializeShell(): Promise<void> {
    await this.sessionService.initialize();
    await this.navigateToDebugCaptureScene();
    this.syncDebugCaptureState();
  }

  private initializeDebugCaptureState(): void {
    if (!this.debugCaptureRequest) {
      return;
    }

    publishDebugCaptureState(
      this.debugCaptureRequest,
      resolveDebugCaptureRoute(this.debugCaptureRequest),
      false,
    );
  }

  private async navigateToDebugCaptureScene(): Promise<void> {
    if (!this.debugCaptureRequest) {
      return;
    }

    const route = resolveDebugCaptureRoute(this.debugCaptureRequest);
    if (!route || this.router.url === route) {
      return;
    }

    await this.router.navigateByUrl(route);
  }

  private syncDebugCaptureState(): void {
    if (!this.debugCaptureRequest) {
      return;
    }

    const route = resolveDebugCaptureRoute(this.debugCaptureRequest);
    const normalizedCurrentPath = this.router.url.split('?')[0] ?? '';
    publishDebugCaptureState(this.debugCaptureRequest, route, !route || normalizedCurrentPath === route);
  }
}

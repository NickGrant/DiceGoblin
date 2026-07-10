import { DOCUMENT, isPlatformBrowser } from '@angular/common';
import { DestroyRef, Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';

const PHONE_MAX_LARGEST_DIMENSION_PX = 932;

@Injectable({ providedIn: 'root' })
export class ViewportOrientationService {
  private readonly document = inject(DOCUMENT);
  private readonly platformId = inject(PLATFORM_ID);
  private readonly destroyRef = inject(DestroyRef);

  private readonly landscapeRequiredState = signal(false);
  private readonly activeState = signal(false);
  private readonly syncViewportStateHandler = () => this.syncViewportState();

  readonly requiresLandscape = this.landscapeRequiredState.asReadonly();
  readonly isLandscapeGateActive = computed(() => this.activeState() && this.landscapeRequiredState());

  initialize(): void {
    if (!isPlatformBrowser(this.platformId) || this.activeState()) {
      return;
    }

    this.activeState.set(true);
    this.syncViewportState();

    window.addEventListener('resize', this.syncViewportStateHandler, { passive: true });
    window.addEventListener('orientationchange', this.syncViewportStateHandler, { passive: true });
    window.visualViewport?.addEventListener('resize', this.syncViewportStateHandler, { passive: true });

    this.destroyRef.onDestroy(() => {
      window.removeEventListener('resize', this.syncViewportStateHandler);
      window.removeEventListener('orientationchange', this.syncViewportStateHandler);
      window.visualViewport?.removeEventListener('resize', this.syncViewportStateHandler);
    });
  }

  private syncViewportState(): void {
    if (!isPlatformBrowser(this.platformId)) {
      this.landscapeRequiredState.set(false);
      return;
    }

    const viewportWidth = window.innerWidth || this.document.documentElement.clientWidth || 0;
    const viewportHeight = window.innerHeight || this.document.documentElement.clientHeight || 0;
    const largestDimension = Math.max(viewportWidth, viewportHeight);
    const isPortrait = viewportHeight > viewportWidth;
    const isCoarsePointer = window.matchMedia('(pointer: coarse)').matches || window.matchMedia('(hover: none)').matches;
    const isPhoneSized = largestDimension <= PHONE_MAX_LARGEST_DIMENSION_PX;

    this.landscapeRequiredState.set(isPortrait && isCoarsePointer && isPhoneSized);
  }
}

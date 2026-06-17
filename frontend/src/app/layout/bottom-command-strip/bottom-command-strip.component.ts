import { DOCUMENT } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnDestroy, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';

@Component({
  selector: 'app-bottom-command-strip',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  host: {
    style: 'display: block;',
  },
  templateUrl: './bottom-command-strip.component.html',
  styleUrl: './bottom-command-strip.component.scss',
})
export class BottomCommandStripComponent implements AfterViewInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly router = inject(Router);
  private readonly elementRef = inject(ElementRef<HTMLElement>);
  private readonly document = inject(DOCUMENT);

  private resizeObserver: ResizeObserver | null = null;
  private readonly viewportResizeHandler = () => this.syncHudHeight();

  readonly session = this.sessionService.session;
  readonly profile = this.sessionService.profile;

  ngAfterViewInit(): void {
    this.syncHudHeight();

    if (typeof ResizeObserver !== 'undefined') {
      this.resizeObserver = new ResizeObserver(() => this.syncHudHeight());
      this.resizeObserver.observe(this.elementRef.nativeElement);
    }

    if (typeof window !== 'undefined') {
      window.addEventListener('resize', this.viewportResizeHandler, { passive: true });
      window.visualViewport?.addEventListener('resize', this.viewportResizeHandler, { passive: true });
    }
  }

  ngOnDestroy(): void {
    this.resizeObserver?.disconnect();
    this.resizeObserver = null;

    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', this.viewportResizeHandler);
      window.visualViewport?.removeEventListener('resize', this.viewportResizeHandler);
    }
  }

  guideQueryParams(): { returnUrl: string } | null {
    const currentUrl = this.router.url;

    if (currentUrl.startsWith('/field-guide')) {
      return null;
    }

    return { returnUrl: currentUrl };
  }

  async logout(): Promise<void> {
    await this.sessionService.logout();
  }

  private syncHudHeight(): void {
    const hudHeight = Math.ceil(this.elementRef.nativeElement.getBoundingClientRect().height);
    this.document.documentElement.style.setProperty('--bottom-command-strip-height', `${hudHeight}px`);
  }
}

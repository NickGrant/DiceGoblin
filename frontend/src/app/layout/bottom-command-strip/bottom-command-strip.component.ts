import { DOCUMENT } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnDestroy, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';

type HudNavItem = {
  readonly label: string;
  readonly ariaLabel: string;
  readonly icon: string;
  readonly route: string;
  readonly guide: boolean;
};

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
  readonly mobileMenuOpen = signal(false);
  readonly navItems: readonly HudNavItem[] = [
    { label: 'Home', ariaLabel: 'Home', icon: '/assets/ui/icons/icon_home.png', route: '/home', guide: false },
    { label: 'Warband', ariaLabel: 'Warband', icon: '/assets/ui/icons/icon_warband.png', route: '/warband', guide: false },
    { label: 'Inventory', ariaLabel: 'Inventory', icon: '/assets/ui/icons/icon_inventory.png', route: '/dice', guide: false },
    { label: 'Shop', ariaLabel: 'Shop', icon: '/assets/ui/icons/icon_shop.png', route: '/shop', guide: false },
    { label: 'Guide', ariaLabel: 'Guide', icon: '/assets/ui/icons/icon_guide.png', route: '/field-guide', guide: true },
  ];

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
    this.mobileMenuOpen.set(false);
    await this.sessionService.logout();
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen.update((open) => !open);
  }

  closeMobileMenu(): void {
    this.mobileMenuOpen.set(false);
  }

  private syncHudHeight(): void {
    const hudHeight = Math.ceil(this.elementRef.nativeElement.getBoundingClientRect().height);
    this.document.documentElement.style.setProperty('--bottom-command-strip-height', `${hudHeight}px`);
  }
}

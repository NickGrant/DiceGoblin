import { DOCUMENT } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnDestroy, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { SessionService } from '../../core/services/session/session.service';

type HudNavItem = {
  readonly label: string;
  readonly ariaLabel: string;
  readonly icon: string;
  readonly authenticatedRoute: string;
  readonly publicRoute: string | null;
  readonly guide: boolean;
  readonly requiredFeatureUnlockKey?: string | null;
};

@Component({
  selector: 'app-command-controls',
  standalone: true,
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './command-controls.component.html',
  styleUrl: './command-controls.component.scss',
})
export class CommandControlsComponent implements AfterViewInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly router = inject(Router);
  private readonly elementRef = inject(ElementRef<HTMLElement>);
  private readonly document = inject(DOCUMENT);

  private resizeObserver: ResizeObserver | null = null;
  private readonly viewportResizeHandler = () => this.syncHudHeight();

  readonly session = this.sessionService.session;
  readonly profile = this.sessionService.profile;
  readonly isAuthenticated = computed(() => this.session().isAuthenticated);
  readonly mobileMenuOpen = signal(false);
  readonly navItems: readonly HudNavItem[] = [
    {
      label: 'Home',
      ariaLabel: 'Home',
      icon: '/assets/ui/icons/icon_home.png',
      authenticatedRoute: '/home',
      publicRoute: '/login',
      guide: false,
    },
    {
      label: 'Warband',
      ariaLabel: 'Warband',
      icon: '/assets/ui/icons/icon_warband.png',
      authenticatedRoute: '/warband',
      publicRoute: null,
      guide: false,
    },
    {
      label: 'Inventory',
      ariaLabel: 'Inventory',
      icon: '/assets/ui/icons/icon_inventory.png',
      authenticatedRoute: '/dice',
      publicRoute: null,
      guide: false,
    },
    {
      label: 'Shop',
      ariaLabel: 'Shop',
      icon: '/assets/ui/icons/icon_shop.png',
      authenticatedRoute: '/shop',
      publicRoute: null,
      guide: false,
      requiredFeatureUnlockKey: 'shop',
    },
    {
      label: 'Guide',
      ariaLabel: 'Field Guide',
      icon: '/assets/ui/icons/icon_guide.png',
      authenticatedRoute: '/field-guide',
      publicRoute: '/guide',
      guide: true,
    },
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

  async logout(): Promise<void> {
    this.mobileMenuOpen.set(false);
    await this.sessionService.logout();
  }

  isNavItemEnabled(item: HudNavItem): boolean {
    if (!this.isAuthenticated()) {
      return item.publicRoute !== null;
    }

    if (item.requiredFeatureUnlockKey) {
      return this.sessionService.featureUnlocks().includes(item.requiredFeatureUnlockKey);
    }

    return true;
  }

  navRoute(item: HudNavItem): string {
    return this.isAuthenticated() ? item.authenticatedRoute : (item.publicRoute ?? '/login');
  }

  navDisabledLabel(item: HudNavItem): string {
    if (!this.isAuthenticated()) {
      return item.ariaLabel + ' unavailable until you sign in';
    }

    if (item.requiredFeatureUnlockKey === 'shop') {
      return item.ariaLabel + ' unavailable until you defeat The Farm';
    }

    return item.ariaLabel + ' unavailable';
  }

  login(): void {
    this.mobileMenuOpen.set(false);
    void this.router.navigateByUrl('/login');
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen.update((open) => !open);
  }

  closeMobileMenu(): void {
    this.mobileMenuOpen.set(false);
  }

  private syncHudHeight(): void {
    const hudHeight = Math.ceil(this.elementRef.nativeElement.getBoundingClientRect().height);
    this.document.documentElement.style.setProperty('--command-controls-height', `${hudHeight}px`);
  }
}

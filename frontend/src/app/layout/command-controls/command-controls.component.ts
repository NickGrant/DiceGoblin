import { DOCUMENT } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnDestroy, computed, inject, signal } from '@angular/core';
import { FontAwesomeModule } from '@fortawesome/angular-fontawesome';
import { faVolumeHigh, faVolumeOff, faVolumeXmark, faWandMagicSparkles } from '@fortawesome/free-solid-svg-icons';
import { Router, RouterLink, RouterLinkActive } from '@angular/router';
import { EnergyRestoreResponse, ItemRecord } from '../../core/models/api.models';
import { ApiHttpService } from '../../core/services/api-http/api-http.service';
import { AudioDirectorService } from '../../core/services/audio/audio-director.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgButtonDirective } from '../../shared/ui/dg-button/dg-button.directive';
import { DgChipDirective } from '../../shared/ui/dg-chip/dg-chip.directive';

type HudNavItem = {
  readonly id: string;
  readonly label: () => string;
  readonly ariaLabel: () => string;
  readonly icon: string;
  readonly authenticatedRoute: () => string;
  readonly publicRoute: string | null;
  readonly isVisible: () => boolean;
};

@Component({
  selector: 'app-command-controls',
  standalone: true,
  imports: [FontAwesomeModule, RouterLink, RouterLinkActive, DgButtonDirective, DgChipDirective],
  templateUrl: './command-controls.component.html',
  styleUrl: './command-controls.component.scss',
})
export class CommandControlsComponent implements AfterViewInit, OnDestroy {
  private readonly sessionService = inject(SessionService);
  private readonly apiHttp = inject(ApiHttpService);
  private readonly audioDirector = inject(AudioDirectorService);
  private readonly router = inject(Router);
  private readonly elementRef = inject(ElementRef<HTMLElement>);
  private readonly document = inject(DOCUMENT);

  private resizeObserver: ResizeObserver | null = null;
  private readonly viewportResizeHandler = () => this.syncHudHeight();

  readonly session = this.sessionService.session;
  readonly profile = this.sessionService.profile;
  readonly isAuthenticated = computed(() => this.session().isAuthenticated);
  readonly hasActiveRun = this.sessionService.hasActiveRun;
  readonly wrongMachineUnlocked = computed(() => this.sessionService.featureUnlocks().includes('wrong_machine'));
  readonly rawChaosBalance = computed(() => {
    const service = this.sessionService as unknown as {
      profileData?: () => { currency?: { raw_chaos?: number } } | null;
    };
    return service.profileData?.()?.currency?.raw_chaos ?? 0;
  });
  readonly audioEnabled = this.audioDirector.isEnabled;
  readonly audioUnlocked = this.audioDirector.isUnlocked;
  readonly audioMuted = this.audioDirector.isMuted;
  readonly energyUseWorking = signal(false);
  readonly energyUseError = signal<string | null>(null);
  readonly energyConsumables = computed(() =>
    (this.sessionService.profileData()?.items ?? [])
      .map((item) => this.mapEnergyConsumable(item))
      .filter((item): item is { item_slug: string; name: string; quantity: number; amount: number } => item !== null),
  );
  readonly selectedEnergyConsumable = computed(() => {
    const missing = Math.max(0, this.profile().energyMax - this.profile().energyCurrent);
    if (missing <= 0) {
      return null;
    }

    const items = [...this.energyConsumables()].sort((left, right) => left.amount - right.amount);
    return items.find((item) => item.amount >= missing) ?? items.at(-1) ?? null;
  });
  readonly faVolumeHigh = faVolumeHigh;
  readonly faVolumeOff = faVolumeOff;
  readonly faVolumeXmark = faVolumeXmark;
  readonly faRawChaos = faWandMagicSparkles;
  readonly mobileMenuOpen = signal(false);
  readonly navItems: readonly HudNavItem[] = [
    {
      id: 'home',
      label: () => 'Home',
      ariaLabel: () => 'Home',
      icon: '/assets/ui/icons/icon_home.png',
      authenticatedRoute: () => '/home',
      publicRoute: '/login',
      isVisible: () => true,
    },
    {
      id: 'run',
      label: () => (this.hasActiveRun() ? 'Continue Run' : 'Start Run'),
      ariaLabel: () => (this.hasActiveRun() ? 'Continue Run' : 'Start Run'),
      icon: '/assets/ui/icons/icon_encounter_combat.png',
      authenticatedRoute: () => (this.hasActiveRun() ? '/run/map' : '/regions'),
      publicRoute: null,
      isVisible: () => this.isAuthenticated(),
    },
    {
      id: 'warband',
      label: () => 'Warband',
      ariaLabel: () => 'Warband',
      icon: '/assets/ui/icons/icon_warband.png',
      authenticatedRoute: () => '/warband',
      publicRoute: null,
      isVisible: () => this.isAuthenticated(),
    },
    {
      id: 'inventory',
      label: () => 'Inventory',
      ariaLabel: () => 'Inventory',
      icon: '/assets/ui/icons/icon_inventory.png',
      authenticatedRoute: () => '/dice',
      publicRoute: null,
      isVisible: () => this.isAuthenticated(),
    },
    {
      id: 'wrong-machine',
      label: () => 'Machine',
      ariaLabel: () => 'Wrong Machine',
      icon: '/assets/ui/icons/icon_encounter_locked.png',
      authenticatedRoute: () => '/wrong-machine',
      publicRoute: null,
      isVisible: () =>
        this.isAuthenticated() && this.sessionService.featureUnlocks().includes('wrong_machine'),
    },
    {
      id: 'shop',
      label: () => 'Shop',
      ariaLabel: () => 'Shop',
      icon: '/assets/ui/icons/icon_shop.png',
      authenticatedRoute: () => '/shop',
      publicRoute: null,
      isVisible: () =>
        this.isAuthenticated() && this.sessionService.featureUnlocks().includes('shop'),
    },
    {
      id: 'academy',
      label: () => 'Academy',
      ariaLabel: () => 'Academy',
      icon: '/assets/ui/icons/icon_shop.png',
      authenticatedRoute: () => '/academy',
      publicRoute: null,
      isVisible: () =>
        this.isAuthenticated() && this.sessionService.featureUnlocks().includes('academy'),
    },
    {
      id: 'guide',
      label: () => 'Guide',
      ariaLabel: () => 'Guide',
      icon: '/assets/ui/icons/icon_guide.png',
      authenticatedRoute: () => '/guide',
      publicRoute: '/guide',
      isVisible: () => true,
    },
    {
      id: 'codex',
      label: () => 'Codex',
      ariaLabel: () => 'Codex',
      icon: '/assets/ui/icons/icon_guide.png',
      authenticatedRoute: () => '/codex',
      publicRoute: null,
      isVisible: () => this.isAuthenticated(),
    },
  ];
  readonly visibleNavItems = computed(() => this.navItems.filter((item) => item.isVisible()));

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

  async activateAudioControl(): Promise<void> {
    if (!this.audioUnlocked()) {
      await this.audioDirector.enableSound();
      return;
    }

    this.audioDirector.toggleMute();
  }

  async restoreEnergy(): Promise<void> {
    const item = this.selectedEnergyConsumable();
    if (!item || this.energyUseWorking()) {
      return;
    }

    this.energyUseWorking.set(true);
    this.energyUseError.set(null);
    try {
      const response = await this.sessionService.runProfileMutation(() =>
        this.apiHttp.postWithCsrf<EnergyRestoreResponse>('/api/v1/items/energy/restore', {
          item_slug: item.item_slug,
        }),
      );
      if (!response.ok) {
        this.energyUseError.set(response.error.message);
      }
    } catch (error) {
      this.energyUseError.set(error instanceof Error ? error.message : 'Unable to restore energy.');
    } finally {
      this.energyUseWorking.set(false);
    }
  }

  energyUseLabel(): string {
    const item = this.selectedEnergyConsumable();
    return item ? `Use ${item.name}` : 'Use energy item';
  }

  audioControlLabel(): string {
    if (!this.audioUnlocked()) {
      return 'Enable Sound';
    }

    return this.audioMuted() ? 'Muted' : 'Sound On';
  }

  audioControlAriaLabel(): string {
    if (!this.audioUnlocked()) {
      return 'Enable sound';
    }

    return this.audioMuted() ? 'Unmute sound' : 'Mute sound';
  }

  audioControlIcon() {
    if (!this.audioUnlocked()) {
      return this.faVolumeOff;
    }

    return this.audioMuted() ? this.faVolumeXmark : this.faVolumeHigh;
  }

  navRoute(item: HudNavItem): string {
    return this.isAuthenticated() ? item.authenticatedRoute() : (item.publicRoute ?? '/login');
  }

  navLabel(item: HudNavItem): string {
    return item.label();
  }

  navAriaLabel(item: HudNavItem): string {
    return item.ariaLabel();
  }

  login(): void {
    this.mobileMenuOpen.set(false);
    void this.router.navigateByUrl('/login');
  }

  toggleMobileMenu(): void {
    this.mobileMenuOpen.update((open) => !open);
  }

  menuToggleLabel(): string {
    return this.mobileMenuOpen() ? 'Close navigation menu' : 'Open navigation menu';
  }

  authActionLabel(): string {
    return this.isAuthenticated() ? 'Logout' : 'Login';
  }

  authActionIcon(): string {
    return this.isAuthenticated() ? '/assets/ui/icons/icon_logout.png' : '/assets/ui/icons/icon_home.png';
  }

  closeMobileMenu(): void {
    this.mobileMenuOpen.set(false);
  }

  private syncHudHeight(): void {
    const hudHeight = Math.ceil(this.elementRef.nativeElement.getBoundingClientRect().height);
    this.document.documentElement.style.setProperty('--command-controls-height', `${hudHeight}px`);
  }

  private mapEnergyConsumable(
    item: ItemRecord,
  ): { item_slug: string; name: string; quantity: number; amount: number } | null {
    const effect = item.meta?.['effect'];
    if (!effect || typeof effect !== 'object' || Array.isArray(effect)) {
      return null;
    }

    const type = String((effect as Record<string, unknown>)['type'] ?? '');
    const amount = Number((effect as Record<string, unknown>)['amount'] ?? 0);
    if (
      item.category !== 'consumable' ||
      !item.is_spendable ||
      type !== 'restore_energy' ||
      !Number.isFinite(amount) ||
      amount <= 0 ||
      item.quantity <= 0
    ) {
      return null;
    }

    return {
      item_slug: item.item_slug,
      name: item.name,
      quantity: item.quantity,
      amount,
    };
  }
}

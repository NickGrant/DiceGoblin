import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import {
  ProfileData,
  PasswordResetRequestResponse,
  PasswordResetRequestData,
  ProfileResponse,
  ProfileViewModel,
  SessionResponse,
  SessionViewModel,
  TeamRecord,
  UnitRecord,
  DiceRecord,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import {
  createDebugCaptureProfile,
  createDebugCaptureSession,
  isDebugCaptureAuthenticated,
  isDebugCaptureGuest,
  readDebugCaptureRequest,
} from '../../debug/debug-capture';
import { ProfileService } from '../profile/profile.service';

const DEFAULT_SESSION: SessionViewModel = {
  isAuthenticated: false,
  displayName: 'Visitor',
  userId: null,
  csrfToken: null,
};

const DEFAULT_PROFILE: ProfileViewModel = {
  energyCurrent: 0,
  energyMax: 0,
  softCurrency: 0,
  activeRunId: null,
  squadCount: 0,
  unitCount: 0,
  activeSquadName: null,
};

@Injectable({ providedIn: 'root' })
export class SessionService {
  private readonly router = inject(Router);
  private readonly sessionState = signal(DEFAULT_SESSION);
  private readonly profileState = signal(DEFAULT_PROFILE);
  private readonly profileDataState = signal<ProfileData | null>(null);
  private readonly loadingState = signal(false);
  private readonly errorState = signal<string | null>(null);
  private initialized = false;
  private initializePromise: Promise<void> | null = null;

  readonly session = this.sessionState.asReadonly();
  readonly profile = this.profileState.asReadonly();
  readonly profileData = this.profileDataState.asReadonly();
  readonly isLoading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();
  readonly hasActiveRun = computed(() => !!this.profileState().activeRunId);
  readonly squadUnitCap = computed(() => this.profileDataState()?.squad_unit_cap ?? 4);
  readonly featureUnlocks = computed(() => this.profileDataState()?.feature_unlocks ?? []);
  readonly unitTypeUnlocks = computed(() => this.profileDataState()?.unit_type_unlocks ?? []);
  readonly shopUnlocked = computed(() => this.featureUnlocks().includes('shop'));
  readonly academyUnlocked = computed(() => this.featureUnlocks().includes('academy'));
  readonly activeSquad = computed<TeamRecord | null>(
    () => this.profileDataState()?.squads.find((squad) => squad.is_active) ?? null,
  );
  readonly units = computed<UnitRecord[]>(() => this.profileDataState()?.units ?? []);
  readonly squads = computed<TeamRecord[]>(() => this.profileDataState()?.squads ?? []);
  readonly dice = computed<DiceRecord[]>(() => this.profileDataState()?.dice ?? []);
  private readonly debugCaptureRequest = readDebugCaptureRequest();

  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly profileService: ProfileService,
  ) {
    this.apiHttp.registerAuthRecovery({
      refreshSession: (failingPath) => this.refreshAfterUnauthorized(failingPath),
      handleSessionExpired: () => this.handleExpiredSession(),
    });
  }

  async initialize(): Promise<void> {
    if (this.initializePromise) {
      return this.initializePromise;
    }

    if (this.initialized) {
      return;
    }

    this.initializePromise = (async () => {
      if (this.applyDebugCaptureState()) {
        this.initialized = true;
        return;
      }

      await this.refresh();
      this.initialized = true;
    })();

    try {
      await this.initializePromise;
    } finally {
      this.initializePromise = null;
    }
  }

  async refresh(): Promise<void> {
    await this.refreshInternal();
  }

  async logout(): Promise<void> {
    try {
      await this.apiHttp.post('/api/v1/auth/logout', {}, { skipAuthRecovery: true });
    } catch {
      // Keep local shell usable even if the backend logout call fails.
    }

    await this.clearSessionStateAndRouteToLogin();
  }

  async loginWithLocalCredentials(email: string, password: string): Promise<void> {
    await this.apiHttp.post<SessionResponse>(
      '/api/v1/auth/local/login',
      { email, password },
      { skipAuthRecovery: true },
    );
    await this.refresh();
    await this.router.navigateByUrl('/');
  }

  async registerWithLocalCredentials(email: string, password: string, displayName: string): Promise<void> {
    await this.apiHttp.post<SessionResponse>(
      '/api/v1/auth/local/register',
      { email, password, display_name: displayName },
      { skipAuthRecovery: true },
    );
    await this.refresh();
    await this.router.navigateByUrl('/');
  }

  async requestPasswordReset(email: string): Promise<PasswordResetRequestData> {
    const response = await this.apiHttp.post<PasswordResetRequestResponse>(
      '/api/v1/auth/local/password-reset/request',
      { email },
      { skipAuthRecovery: true },
    );

    if (!response.ok) {
      throw new Error(response.error?.message ?? 'Could not request password reset.');
    }

    return response.data;
  }

  async confirmPasswordReset(token: string, password: string): Promise<void> {
    await this.apiHttp.post<SessionResponse>(
      '/api/v1/auth/local/password-reset/confirm',
      { token, password },
      { skipAuthRecovery: true },
    );
    await this.refresh();
    await this.router.navigateByUrl('/');
  }

  async refreshProfile(options?: { force?: boolean }): Promise<void> {
    try {
      const profile = await this.profileService.getProfile({
        force: options?.force,
        allowStaleOnError: !options?.force,
      });
      this.profileDataState.set(profile.ok ? profile.data : null);
      this.profileState.set(this.mapProfile(profile));
      this.errorState.set(null);
    } catch (error) {
      this.errorState.set(error instanceof Error ? error.message : 'Unable to reach the API.');
    }
  }

  async runProfileMutation<T>(operation: () => Promise<T>, options?: { force?: boolean }): Promise<T> {
    const response = await operation();
    await this.refreshProfile({ force: options?.force ?? true });
    return response;
  }

  invalidateProfileCache(): void {
    this.profileService.invalidateProfileCache();
  }

  private async refreshInternal(options?: { skipAuthRecovery?: boolean; suppressErrors?: boolean }): Promise<void> {
    this.loadingState.set(true);
    if (!options?.suppressErrors) {
      this.errorState.set(null);
    }

    try {
      const session = await this.apiHttp.get<SessionResponse>(
        '/api/v1/session',
        options?.skipAuthRecovery ? { skipAuthRecovery: true } : undefined,
      );
      const mappedSession = this.mapSession(session);
      this.sessionState.set(mappedSession);

      if (mappedSession.isAuthenticated) {
        const profile = await this.profileService.getProfile();
        this.profileDataState.set(profile.ok ? profile.data : null);
        this.profileState.set(this.mapProfile(profile));
      } else {
        this.profileDataState.set(null);
        this.profileState.set(DEFAULT_PROFILE);
      }
    } catch (error) {
      if (!options?.suppressErrors) {
        this.errorState.set(error instanceof Error ? error.message : 'Unable to reach the API.');
      }
    } finally {
      this.loadingState.set(false);
    }
  }

  async clearSessionStateAndRouteToLogin(): Promise<void> {
    this.initialized = false;
    this.initializePromise = null;
    this.sessionState.set(DEFAULT_SESSION);
    this.profileState.set(DEFAULT_PROFILE);
    this.profileDataState.set(null);
    this.errorState.set(null);
    await this.router.navigateByUrl('/login');
  }

  private async refreshAfterUnauthorized(_failingPath: string): Promise<boolean> {
    await this.refreshInternal({ skipAuthRecovery: true, suppressErrors: true });
    return this.sessionState().isAuthenticated;
  }

  private async handleExpiredSession(): Promise<void> {
    await this.clearSessionStateAndRouteToLogin();
  }

  private mapSession(session: SessionResponse): SessionViewModel {
    if (!session.ok || session.data?.authenticated !== true) {
      return DEFAULT_SESSION;
    }

    return {
      isAuthenticated: true,
      displayName: session.data?.user?.display_name?.trim() || 'Goblin Commander',
      userId: session.data?.user?.id ?? null,
      csrfToken: session.data?.csrf_token ?? null,
    };
  }

  private mapProfile(profile: ProfileResponse): ProfileViewModel {
    if (!profile.ok) {
      return DEFAULT_PROFILE;
    }

    const activeSquad = profile.data?.squads?.find((squad) => squad.is_active);

    return {
      energyCurrent: profile.data?.energy?.current ?? 0,
      energyMax: profile.data?.energy?.max ?? 0,
      softCurrency: profile.data?.currency?.soft ?? 0,
      activeRunId: profile.data?.active_run?.run_id?.toString() ?? null,
      squadCount: profile.data?.squads?.length ?? 0,
      unitCount: profile.data?.units?.length ?? 0,
      activeSquadName: activeSquad?.name ?? null,
    };
  }

  private applyDebugCaptureState(): boolean {
    if (isDebugCaptureGuest(this.debugCaptureRequest)) {
      this.sessionState.set(DEFAULT_SESSION);
      this.profileState.set(DEFAULT_PROFILE);
      this.profileDataState.set(null);
      this.loadingState.set(false);
      this.errorState.set(null);
      return true;
    }

    if (!isDebugCaptureAuthenticated(this.debugCaptureRequest) || !this.debugCaptureRequest) {
      return false;
    }

    const profileData = createDebugCaptureProfile(this.debugCaptureRequest);
    this.sessionState.set(createDebugCaptureSession(this.debugCaptureRequest));
    this.profileDataState.set(profileData);
    this.profileState.set({
      energyCurrent: profileData.energy.current,
      energyMax: profileData.energy.max,
      softCurrency: profileData.currency.soft,
      activeRunId: profileData.active_run?.run_id ?? null,
      squadCount: profileData.squads.length,
      unitCount: profileData.units.length,
      activeSquadName: profileData.squads.find((squad) => squad.is_active)?.name ?? null,
    });
    this.loadingState.set(false);
    this.errorState.set(null);
    return true;
  }
}


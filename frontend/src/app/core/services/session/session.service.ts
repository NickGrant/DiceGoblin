import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import {
  ProfileData,
  ProfileResponse,
  ProfileViewModel,
  SessionResponse,
  SessionViewModel,
  TeamRecord,
  UnitRecord,
  DiceRecord,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
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

  readonly session = this.sessionState.asReadonly();
  readonly profile = this.profileState.asReadonly();
  readonly profileData = this.profileDataState.asReadonly();
  readonly isLoading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();
  readonly hasActiveRun = computed(() => !!this.profileState().activeRunId);
  readonly activeSquad = computed<TeamRecord | null>(
    () => this.profileDataState()?.squads.find((squad) => squad.is_active) ?? null,
  );
  readonly units = computed<UnitRecord[]>(() => this.profileDataState()?.units ?? []);
  readonly squads = computed<TeamRecord[]>(() => this.profileDataState()?.squads ?? []);
  readonly dice = computed<DiceRecord[]>(() => this.profileDataState()?.dice ?? []);

  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly profileService: ProfileService,
  ) {}

  async initialize(): Promise<void> {
    if (this.initialized || this.loadingState()) {
      return;
    }

    this.initialized = true;
    await this.refresh();
  }

  async refresh(): Promise<void> {
    this.loadingState.set(true);
    this.errorState.set(null);

    try {
      const session = await this.apiHttp.get<SessionResponse>('/api/v1/session');
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
      this.errorState.set(error instanceof Error ? error.message : 'Unable to reach the API.');
    } finally {
      this.loadingState.set(false);
    }
  }

  async logout(): Promise<void> {
    try {
      await this.apiHttp.post('/api/v1/auth/logout', {});
    } catch {
      // Keep local shell usable even if the backend logout call fails.
    }

    this.initialized = false;
    this.sessionState.set(DEFAULT_SESSION);
    this.profileState.set(DEFAULT_PROFILE);
    this.profileDataState.set(null);
    await this.router.navigateByUrl('/login');
  }

  async refreshProfile(options?: { force?: boolean }): Promise<void> {
    const profile = await this.profileService.getProfile({
      force: options?.force,
      allowStaleOnError: !options?.force,
    });
    this.profileDataState.set(profile.ok ? profile.data : null);
    this.profileState.set(this.mapProfile(profile));
  }

  invalidateProfileCache(): void {
    this.profileService.invalidateProfileCache();
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
}


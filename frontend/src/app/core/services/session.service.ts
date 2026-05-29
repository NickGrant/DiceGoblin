import { Injectable, computed, signal } from '@angular/core';
import {
  ProfileResponse,
  ProfileViewModel,
  SessionResponse,
  SessionViewModel,
} from '../models/api.models';
import { ApiHttpService } from './api-http.service';
import { ProfileService } from './profile.service';

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
  private readonly sessionState = signal(DEFAULT_SESSION);
  private readonly profileState = signal(DEFAULT_PROFILE);
  private readonly loadingState = signal(false);
  private readonly errorState = signal<string | null>(null);
  private initialized = false;

  readonly session = this.sessionState.asReadonly();
  readonly profile = this.profileState.asReadonly();
  readonly isLoading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();
  readonly hasActiveRun = computed(() => !!this.profileState().activeRunId);

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
      this.sessionState.set(this.mapSession(session));

      if (session.ok) {
        const profile = await this.profileService.getProfile();
        this.profileState.set(this.mapProfile(profile));
      } else {
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

    this.sessionState.set(DEFAULT_SESSION);
    this.profileState.set(DEFAULT_PROFILE);
  }

  private mapSession(session: SessionResponse): SessionViewModel {
    if (!session.ok) {
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
      activeRunId: profile.data?.active_run?.id?.toString() ?? null,
      squadCount: profile.data?.squads?.length ?? 0,
      unitCount: profile.data?.units?.length ?? 0,
      activeSquadName: activeSquad?.name ?? null,
    };
  }
}

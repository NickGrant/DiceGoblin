import { Injectable, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import {
  PasswordResetRequestResponse,
  PasswordResetRequestData,
  SessionResponse,
  SessionViewModel,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import {
  createDebugCaptureSession,
  isDebugCaptureAuthenticated,
  isDebugCaptureGuest,
  readDebugCaptureRequest,
} from '../../debug/debug-capture';

const DEFAULT_SESSION: SessionViewModel = {
  isAuthenticated: false,
  displayName: 'Visitor',
  userId: null,
  csrfToken: null,
};

@Injectable({ providedIn: 'root' })
export class SessionService {
  private readonly router = inject(Router);
  private readonly sessionState = signal(DEFAULT_SESSION);
  private readonly loadingState = signal(false);
  private readonly errorState = signal<string | null>(null);
  private initialized = false;
  private initializePromise: Promise<void> | null = null;

  readonly session = this.sessionState.asReadonly();
  readonly isLoading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();
  private readonly debugCaptureRequest = readDebugCaptureRequest();

  constructor(private readonly apiHttp: ApiHttpService) {
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
    await this.router.navigateByUrl('/game');
  }

  async registerWithLocalCredentials(email: string, password: string, displayName: string): Promise<void> {
    await this.apiHttp.post<SessionResponse>(
      '/api/v1/auth/local/register',
      { email, password, display_name: displayName },
      { skipAuthRecovery: true },
    );
    await this.refresh();
    await this.router.navigateByUrl('/game');
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
    await this.router.navigateByUrl('/game');
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

  private applyDebugCaptureState(): boolean {
    if (isDebugCaptureGuest(this.debugCaptureRequest)) {
      this.sessionState.set(DEFAULT_SESSION);
      this.loadingState.set(false);
      this.errorState.set(null);
      return true;
    }

    if (!isDebugCaptureAuthenticated(this.debugCaptureRequest) || !this.debugCaptureRequest) {
      return false;
    }

    this.sessionState.set(createDebugCaptureSession(this.debugCaptureRequest));
    this.loadingState.set(false);
    this.errorState.set(null);
    return true;
  }
}


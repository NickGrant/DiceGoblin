import { Injectable } from '@angular/core';
import { resolveApiBaseUrl } from '../../config/runtime-config';
import { SessionResponse } from '../../models/api.models';

type RequestBehavior = {
  skipAuthRecovery?: boolean;
};

@Injectable({ providedIn: 'root' })
export class ApiHttpService {
  readonly baseUrl = resolveApiBaseUrl();
  private authRecoveryRegistration: {
    refreshSession: (failingPath: string) => Promise<boolean>;
    handleSessionExpired: () => Promise<void>;
  } | null = null;
  private authRecoveryPromise: Promise<boolean> | null = null;

  registerAuthRecovery(handlers: {
    refreshSession: (failingPath: string) => Promise<boolean>;
    handleSessionExpired: () => Promise<void>;
  }): void {
    this.authRecoveryRegistration = handlers;
  }

  async get<T>(path: string, behavior?: RequestBehavior): Promise<T> {
    return this.request<T>(path, { method: 'GET' }, behavior);
  }

  async post<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.request<T>(path, {
      method: 'POST',
      body: JSON.stringify(body),
    }, behavior);
  }

  async put<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.request<T>(path, {
      method: 'PUT',
      body: JSON.stringify(body),
    }, behavior);
  }

  async patch<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.request<T>(path, {
      method: 'PATCH',
      body: JSON.stringify(body),
    }, behavior);
  }

  async delete<T>(path: string, body?: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.request<T>(path, {
      method: 'DELETE',
      body: body === undefined ? undefined : JSON.stringify(body),
    }, behavior);
  }

  async postWithCsrf<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.requestWithCsrf<T>(path, 'POST', body, behavior);
  }

  async putWithCsrf<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.requestWithCsrf<T>(path, 'PUT', body, behavior);
  }

  async patchWithCsrf<T>(path: string, body: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.requestWithCsrf<T>(path, 'PATCH', body, behavior);
  }

  async deleteWithCsrf<T>(path: string, body?: unknown, behavior?: RequestBehavior): Promise<T> {
    return this.requestWithCsrf<T>(path, 'DELETE', body ?? {}, behavior);
  }

  async request<T>(path: string, init: RequestInit, behavior?: RequestBehavior): Promise<T> {
    const headers = new Headers(init.headers ?? undefined);
    if (init.body !== undefined && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    const executeRequest = async (): Promise<Response> =>
      fetch(`${this.baseUrl}${path}`, {
        ...init,
        headers,
        credentials: 'include',
      });

    let response = await executeRequest();

    if (
      response.status === 401
      && !behavior?.skipAuthRecovery
      && await this.tryRecoverFromUnauthorized(path)
    ) {
      response = await executeRequest();
    }

    if (!response.ok) {
      const body = await response.text().catch(() => '');
      throw new Error(`API ${response.status} ${response.statusText}: ${body}`);
    }

    return (await response.json()) as T;
  }

  private async requestWithCsrf<T>(
    path: string,
    method: NonNullable<RequestInit['method']>,
    body: unknown,
    behavior?: RequestBehavior,
  ): Promise<T> {
    const session = await this.get<SessionResponse>('/api/v1/session', { skipAuthRecovery: true });
    const headers = new Headers();

    if (session.ok) {
      headers.set('X-CSRF-Token', session.data.csrf_token);
    }

    return this.request<T>(path, {
      method,
      headers,
      body: JSON.stringify(body),
    }, behavior);
  }

  private async tryRecoverFromUnauthorized(path: string): Promise<boolean> {
    if (
      !this.authRecoveryRegistration
      || path === '/api/v1/session'
      || path === '/api/v1/auth/logout'
    ) {
      return false;
    }

    if (!this.authRecoveryPromise) {
      this.authRecoveryPromise = (async () => {
        const recovered = await this.authRecoveryRegistration!.refreshSession(path);
        if (!recovered) {
          await this.authRecoveryRegistration!.handleSessionExpired();
        }

        return recovered;
      })().finally(() => {
        this.authRecoveryPromise = null;
      });
    }

    return this.authRecoveryPromise;
  }
}


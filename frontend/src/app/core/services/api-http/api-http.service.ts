import { Injectable } from '@angular/core';
import { resolveApiBaseUrl } from '../../config/runtime-config';
import { SessionResponse } from '../../models/api.models';

@Injectable({ providedIn: 'root' })
export class ApiHttpService {
  readonly baseUrl = resolveApiBaseUrl();

  async get<T>(path: string): Promise<T> {
    return this.request<T>(path, { method: 'GET' });
  }

  async post<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'POST',
      body: JSON.stringify(body),
    });
  }

  async put<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  async patch<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'PATCH',
      body: JSON.stringify(body),
    });
  }

  async delete<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'DELETE',
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }

  async postWithCsrf<T>(path: string, body: unknown): Promise<T> {
    return this.requestWithCsrf<T>(path, 'POST', body);
  }

  async putWithCsrf<T>(path: string, body: unknown): Promise<T> {
    return this.requestWithCsrf<T>(path, 'PUT', body);
  }

  async patchWithCsrf<T>(path: string, body: unknown): Promise<T> {
    return this.requestWithCsrf<T>(path, 'PATCH', body);
  }

  async deleteWithCsrf<T>(path: string, body?: unknown): Promise<T> {
    return this.requestWithCsrf<T>(path, 'DELETE', body ?? {});
  }

  async request<T>(path: string, init: RequestInit): Promise<T> {
    const headers = new Headers(init.headers ?? undefined);
    if (init.body !== undefined && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(`${this.baseUrl}${path}`, {
      ...init,
      headers,
      credentials: 'include',
    });

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
  ): Promise<T> {
    const session = await this.get<SessionResponse>('/api/v1/session');
    const headers = new Headers();

    if (session.ok) {
      headers.set('X-CSRF-Token', session.data.csrf_token);
    }

    return this.request<T>(path, {
      method,
      headers,
      body: JSON.stringify(body),
    });
  }
}


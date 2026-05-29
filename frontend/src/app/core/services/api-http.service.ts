import { Injectable } from '@angular/core';
import { resolveApiBaseUrl } from '../config/runtime-config';

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

  async request<T>(path: string, init: RequestInit): Promise<T> {
    const headers = new Headers(init.headers ?? undefined);
    if (!headers.has('Content-Type')) {
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
}

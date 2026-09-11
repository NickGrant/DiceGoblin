import { resolveApiBaseUrl } from '../../core/config/runtime-config';

export type RuntimeFetch = (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>;

export type RuntimeApiErrorKind = 'unauthorized' | 'http' | 'network' | 'malformed-response';

export class RuntimeApiError extends Error {
  constructor(
    readonly kind: RuntimeApiErrorKind,
    readonly status: number | null = null,
  ) {
    super(`Runtime API request failed: ${kind}`);
    this.name = 'RuntimeApiError';
  }
}

const browserFetch: RuntimeFetch = (input, init) => window.fetch(input, init);

/** Framework-neutral API access owned by the mounted Phaser runtime. */
export class RuntimeApiClient {
  readonly baseUrl: string;

  constructor(
    private readonly fetchRequest: RuntimeFetch = browserFetch,
    baseUrl: string = resolveApiBaseUrl(),
  ) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
  }

  async getBootstrap(): Promise<unknown> {
    let response: Response;

    try {
      response = await this.fetchRequest(`${this.baseUrl}/api/v1/game/bootstrap`, {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
    } catch {
      throw new RuntimeApiError('network');
    }

    if (!response.ok) {
      throw new RuntimeApiError(response.status === 401 ? 'unauthorized' : 'http', response.status);
    }

    try {
      return await response.json();
    } catch {
      throw new RuntimeApiError('malformed-response', response.status);
    }
  }
}

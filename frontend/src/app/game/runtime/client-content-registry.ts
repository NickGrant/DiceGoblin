import { RuntimeFetch } from './runtime-api-client';

export interface ClientRegionDefinition extends Readonly<Record<string, unknown>> {
  readonly id: string;
  readonly display_name: string;
  readonly art_key: string;
}

export interface ClientContentProjection {
  readonly revision: string;
  readonly content: {
    readonly regions: Readonly<Record<string, ClientRegionDefinition>>;
  };
}

export type ClientContentLoadErrorKind = 'request' | 'malformed-response';

export class ClientContentError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'ClientContentError';
  }
}

export class ClientContentLoadError extends Error {
  constructor(readonly kind: ClientContentLoadErrorKind) {
    super(`Client content load failed: ${kind}`);
    this.name = 'ClientContentLoadError';
  }
}

const stableIdPattern = /^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/;
const revisionPattern = /^[a-f0-9]{64}$/;

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function requireNonEmptyString(record: Record<string, unknown>, key: string): string {
  const value = record[key];
  if (typeof value !== 'string' || value.trim() === '') {
    throw new ClientContentError(`Client content field '${key}' must be a non-empty string.`);
  }

  return value;
}

/** Validated, indexed view of the generated browser-safe content projection. */
export class ClientContentRegistry {
  readonly revision: string;
  private readonly definitionsById = new Map<string, ClientRegionDefinition>();

  constructor(projection: unknown) {
    if (!isRecord(projection)) {
      throw new ClientContentError('Client content projection must be an object.');
    }

    const revision = requireNonEmptyString(projection, 'revision');
    if (!revisionPattern.test(revision)) {
      throw new ClientContentError('Client content revision must be a SHA-256 hash.');
    }

    const content = projection['content'];
    if (!isRecord(content) || !isRecord(content['regions'])) {
      throw new ClientContentError('Client content projection must contain a regions catalog.');
    }

    for (const [catalogId, candidate] of Object.entries(content['regions'])) {
      if (!stableIdPattern.test(catalogId) || !isRecord(candidate)) {
        throw new ClientContentError('Client content contains an invalid region definition.');
      }

      const id = requireNonEmptyString(candidate, 'id');
      const displayName = requireNonEmptyString(candidate, 'display_name');
      const artKey = requireNonEmptyString(candidate, 'art_key');
      if (id !== catalogId || !stableIdPattern.test(id) || !id.startsWith('region.')) {
        throw new ClientContentError(
          `Client content region '${catalogId}' has an invalid identity.`,
        );
      }

      if (this.definitionsById.has(id)) {
        throw new ClientContentError(`Client content contains duplicate stable ID '${id}'.`);
      }

      this.definitionsById.set(
        id,
        Object.freeze({ ...candidate, id, display_name: displayName, art_key: artKey }),
      );
    }

    this.revision = revision;
  }

  get(stableId: string): ClientRegionDefinition | undefined {
    return this.definitionsById.get(stableId);
  }

  has(stableId: string): boolean {
    return this.definitionsById.has(stableId);
  }
}

const browserFetch: RuntimeFetch = (input, init) => window.fetch(input, init);

export function resolveClientContentUrl(): string {
  return new URL('game-content.json', document.baseURI).toString();
}

export class ClientContentLoader {
  constructor(
    private readonly fetchRequest: RuntimeFetch = browserFetch,
    readonly contentUrl: string = resolveClientContentUrl(),
  ) {}

  async loadProjection(): Promise<unknown> {
    let response: Response;
    try {
      response = await this.fetchRequest(this.contentUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
    } catch {
      throw new ClientContentLoadError('request');
    }

    if (!response.ok) {
      throw new ClientContentLoadError('request');
    }

    try {
      return await response.json();
    } catch {
      throw new ClientContentLoadError('malformed-response');
    }
  }
}

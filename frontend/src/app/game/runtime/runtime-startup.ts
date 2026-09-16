import {
  ClientContentError,
  ClientContentLoadError,
  ClientContentLoader,
  ClientContentRegistry,
} from './client-content-registry';
import { BootstrapContractError, GameStore, parseGameBootstrapEnvelope } from './game-store';
import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';
import { BattlePresentationState } from './battle-presentation';

export type RuntimeStartupFailureReason =
  | 'client-content-request'
  | 'client-content-malformed'
  | 'bootstrap-request'
  | 'unauthorized'
  | 'bootstrap-malformed'
  | 'unexpected'
  | 'runtime-disposed';

export type RuntimeStartupSnapshot =
  | { readonly status: 'loading' }
  | { readonly status: 'ready' }
  | {
      readonly status: 'content-mismatch';
      readonly clientRevision: string;
      readonly serverRevision: string;
    }
  | { readonly status: 'failure'; readonly reason: RuntimeStartupFailureReason };

/** One-shot startup coordinator and owner of runtime-lifetime startup services. */
export class RuntimeStartup {
  readonly store: GameStore;
  private currentState: RuntimeStartupSnapshot = { status: 'loading' };
  private activeContentRegistry: ClientContentRegistry | null = null;
  private startupPromise: Promise<RuntimeStartupSnapshot> | null = null;
  private disposed = false;

  constructor(
    readonly apiClient: RuntimeApiClient = new RuntimeApiClient(),
    readonly contentLoader: ClientContentLoader = new ClientContentLoader(),
    store: GameStore = new GameStore(),
    readonly battlePresentation: BattlePresentationState = new BattlePresentationState(),
  ) {
    this.store = store;
  }

  get state(): RuntimeStartupSnapshot {
    return this.currentState;
  }

  get contentRegistry(): ClientContentRegistry | null {
    return this.activeContentRegistry;
  }

  start(): Promise<RuntimeStartupSnapshot> {
    if (this.disposed) {
      return Promise.resolve(this.currentState);
    }

    this.startupPromise ??= this.performStartup();
    return this.startupPromise;
  }

  dispose(): void {
    this.disposed = true;
    this.activeContentRegistry = null;
    this.store.clear();
    this.currentState = { status: 'failure', reason: 'runtime-disposed' };
  }

  private async performStartup(): Promise<RuntimeStartupSnapshot> {
    this.currentState = { status: 'loading' };

    let registry: ClientContentRegistry;
    try {
      const projection = await this.contentLoader.loadProjection();
      if (this.disposed) return this.currentState;
      registry = new ClientContentRegistry(projection);
    } catch (error) {
      if (
        error instanceof ClientContentError ||
        (error instanceof ClientContentLoadError && error.kind === 'malformed-response')
      ) {
        return this.fail('client-content-malformed');
      }
      if (error instanceof ClientContentLoadError) {
        return this.fail('client-content-request');
      }
      return this.fail('unexpected');
    }

    let bootstrap;
    try {
      const envelope = await this.apiClient.getBootstrap();
      if (this.disposed) return this.currentState;
      bootstrap = parseGameBootstrapEnvelope(envelope);
    } catch (error) {
      if (
        error instanceof BootstrapContractError ||
        (error instanceof RuntimeApiError && error.kind === 'malformed-response')
      ) {
        return this.fail('bootstrap-malformed');
      }
      if (error instanceof RuntimeApiError && error.kind === 'unauthorized') {
        return this.fail('unauthorized');
      }
      if (error instanceof RuntimeApiError) {
        return this.fail('bootstrap-request');
      }
      return this.fail('unexpected');
    }

    if (registry.revision !== bootstrap.content_revision) {
      this.currentState = {
        status: 'content-mismatch',
        clientRevision: registry.revision,
        serverRevision: bootstrap.content_revision,
      };
      return this.currentState;
    }

    if (this.disposed) return this.currentState;
    this.activeContentRegistry = registry;
    this.store.hydrateBootstrap(bootstrap);
    this.battlePresentation.restoreForAccount(bootstrap.account.id);
    this.currentState = { status: 'ready' };
    return this.currentState;
  }

  private fail(reason: RuntimeStartupFailureReason): RuntimeStartupSnapshot {
    if (this.disposed) return this.currentState;
    this.currentState = { status: 'failure', reason };
    return this.currentState;
  }
}

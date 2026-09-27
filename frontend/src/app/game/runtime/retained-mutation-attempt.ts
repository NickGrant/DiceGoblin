import { RuntimeApiError } from './runtime-api-client';

export type RetainedMutationState = 'idle' | 'submitting' | 'retryable' | 'rejected' | 'succeeded';
export type RetainedMutationOutcome<T> =
  | { readonly kind: 'success'; readonly result: T }
  | { readonly kind: 'ambiguous' }
  | { readonly kind: 'rejected'; readonly error: unknown }
  | { readonly kind: 'ignored' };

/** Owns the request and idempotency key for one durable client mutation. */
export class RetainedMutationAttempt<TRequest, TResult> {
  private attempt: { readonly identity: string; readonly request: TRequest; readonly key: string } | null = null;
  private currentState: RetainedMutationState = 'idle';

  constructor(private readonly createKey: () => string = () => crypto.randomUUID()) {}

  get state(): RetainedMutationState { return this.currentState; }
  get identity(): Readonly<{ identity: string; request: TRequest; key: string }> | null { return this.attempt; }

  begin(identity: string, request: TRequest): void {
    if (this.currentState === 'submitting' || this.currentState === 'retryable') return;
    if (!this.attempt || this.attempt.identity !== identity
      || this.currentState === 'rejected' || this.currentState === 'succeeded') {
      this.attempt = Object.freeze({ identity, request, key: this.createKey() });
    }
    this.currentState = 'idle';
  }

  async submit(send: (request: TRequest, key: string) => Promise<TResult>): Promise<RetainedMutationOutcome<TResult>> {
    if (!this.attempt || this.currentState === 'submitting' || this.currentState === 'succeeded') return { kind: 'ignored' };
    this.currentState = 'submitting';
    try {
      const result = await send(this.attempt.request, this.attempt.key);
      this.currentState = 'succeeded';
      return { kind: 'success', result };
    } catch (error) {
      if (error instanceof RuntimeApiError && (error.kind === 'unauthorized'
        || (error.kind === 'http' && error.status !== null && error.status >= 400 && error.status < 500))) {
        this.currentState = 'rejected';
        return { kind: 'rejected', error };
      }
      this.currentState = 'retryable';
      return { kind: 'ambiguous' };
    }
  }
}

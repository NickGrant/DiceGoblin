import { RuntimeApiClient, RuntimeApiError } from './runtime-api-client';
import { RunNodeResolutionResult } from './run-node-resolution-contracts';

export type CombatResolutionAttemptState = 'idle' | 'submitting' | 'retryable' | 'rejected' | 'already-resolved' | 'succeeded';
export type CombatResolutionAttemptOutcome = { readonly kind: 'success'; readonly result: RunNodeResolutionResult }
  | { readonly kind: 'ambiguous' } | { readonly kind: 'rejected' } | { readonly kind: 'already-resolved' } | { readonly kind: 'ignored' };

export class CombatResolutionAttempt {
  private attempt: { runId: string; nodeId: string; key: string } | null = null;
  private currentState: CombatResolutionAttemptState = 'idle';
  constructor(private readonly createKey: () => string = () => `combat-node:${crypto.randomUUID()}`) {}
  get state(): CombatResolutionAttemptState { return this.currentState; }
  get identity(): Readonly<{ runId: string; nodeId: string; key: string }> | null { return this.attempt; }

  begin(runId: string, nodeId: string): void {
    if (this.currentState === 'submitting') return;
    if (!this.attempt || this.attempt.runId !== runId || this.attempt.nodeId !== nodeId
      || this.currentState === 'rejected' || this.currentState === 'succeeded') this.attempt = { runId, nodeId, key: this.createKey() };
    this.currentState = 'idle';
  }

  async submit(api: RuntimeApiClient, csrfToken: string): Promise<CombatResolutionAttemptOutcome> {
    if (!this.attempt || this.currentState === 'submitting' || this.currentState === 'succeeded') return { kind: 'ignored' };
    this.currentState = 'submitting';
    try {
      const result = await api.resolveRunNode(this.attempt.runId, this.attempt.nodeId, csrfToken, this.attempt.key);
      this.currentState = 'succeeded'; return { kind: 'success', result };
    } catch (error) {
      if (error instanceof RuntimeApiError && error.kind === 'http' && error.status === 409 && error.code === 'run_node_already_resolved') {
        this.currentState = 'already-resolved'; return { kind: 'already-resolved' };
      }
      if (error instanceof RuntimeApiError && (error.kind === 'unauthorized'
        || (error.kind === 'http' && error.status !== null && error.status >= 400 && error.status < 500))) {
        this.currentState = 'rejected'; return { kind: 'rejected' };
      }
      this.currentState = 'retryable'; return { kind: 'ambiguous' };
    }
  }
}

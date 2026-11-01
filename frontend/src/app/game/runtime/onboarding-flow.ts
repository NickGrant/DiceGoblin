import { DialogueCompletion, DialogueRead } from './dialogue-contracts';
import { GameStore, parseGameBootstrapEnvelope } from './game-store';
import { OnboardingProvisionResult, OnboardingStatus } from './onboarding-contracts';
import { RetainedMutationAttempt } from './retained-mutation-attempt';
import { RuntimeApiClient } from './runtime-api-client';

export type OnboardingView =
  | { readonly kind: 'loading' }
  | { readonly kind: 'dialogue'; readonly status: OnboardingStatus; readonly dialogue: DialogueRead; readonly message: string | null }
  | { readonly kind: 'provision'; readonly status: OnboardingStatus; readonly message: string | null }
  | { readonly kind: 'submitting'; readonly status: OnboardingStatus }
  | { readonly kind: 'complete' }
  | { readonly kind: 'error'; readonly message: string };

/** Transient presentation of durable onboarding and bootstrap reads. */
export class OnboardingFlow {
  private current: OnboardingView = { kind: 'loading' };
  private listeners = new Set<(view: OnboardingView) => void>();
  private generation = 0;
  private disposed = false;
  private completedDialogueId: string | null = null;
  private dialogueAttempt = new RetainedMutationAttempt<{ dialogueId: string; choiceId: string }, DialogueCompletion>();
  private provisionAttempt = new RetainedMutationAttempt<null, OnboardingProvisionResult>();

  constructor(private readonly api: RuntimeApiClient, private readonly store: GameStore) {}

  get view(): OnboardingView { return this.current; }

  subscribe(listener: (view: OnboardingView) => void): () => void {
    this.listeners.add(listener);
    listener(this.current);
    return () => this.listeners.delete(listener);
  }

  dispose(): void {
    this.disposed = true;
    this.generation++;
    this.listeners.clear();
  }

  async refresh(minRevision = 0, message: string | null = null): Promise<OnboardingView> {
    const generation = ++this.generation;
    this.set({ kind: 'loading' });
    try {
      const status = await this.api.getOnboarding();
      if (!this.isCurrent(generation)) return this.current;
      const bootstrap = parseGameBootstrapEnvelope(await this.api.getBootstrap());
      if (!this.isCurrent(generation)) return this.current;
      const prior = this.store.bootstrap;
      if (!prior || bootstrap.account.id !== prior.account.id || bootstrap.content_revision !== prior.content_revision
        || bootstrap.player.player_revision < Math.max(minRevision, prior.player.player_revision)
        || (status.status !== 'complete' && bootstrap.active_squad !== null)
        || (this.completedDialogueId !== null && status.dialogue_id === this.completedDialogueId)) {
        throw new Error('Onboarding reads disagree with the latest player state.');
      }
      this.store.hydrateBootstrap(bootstrap);
      if (status.status === 'complete') {
        this.set({ kind: 'complete' });
      } else if (status.status === 'ready_to_provision') {
        this.set({ kind: 'provision', status, message });
      } else {
        const dialogue = await this.api.getDialogue(status.dialogue_id!);
        if (!this.isCurrent(generation)) return this.current;
        if (this.dialogueAttempt.identity?.request.dialogueId !== dialogue.id) {
          this.dialogueAttempt = new RetainedMutationAttempt();
        }
        this.set({ kind: 'dialogue', status, dialogue, message });
      }
    } catch {
      if (this.isCurrent(generation)) this.set({ kind: 'error', message: 'Could not confirm your place in the Mystic Cave. Try again.' });
    }
    return this.current;
  }

  async choose(choiceId: string): Promise<void> {
    const view = this.current;
    if (view.kind !== 'dialogue' || !view.dialogue.choices.some((choice) => choice.id === choiceId)) return;
    const csrf = this.store.bootstrap?.session.csrf_token;
    if (!csrf) return this.set({ kind: 'error', message: 'Your session needs to be refreshed. Try again.' });
    const dialogueId = view.dialogue.id;
    this.dialogueAttempt.begin(`${dialogueId}:${choiceId}`, { dialogueId, choiceId });
    this.set({ kind: 'submitting', status: view.status });
    const outcome = await this.dialogueAttempt.submit((request, key) =>
      this.api.completeDialogue(request.dialogueId, request.choiceId, csrf, key));
    if (this.disposed) return;
    if (outcome.kind === 'success') {
      try { this.store.reconcileDialogueCompletion(outcome.result); }
      catch {
        this.completedDialogueId = dialogueId;
        await this.refresh(outcome.result.playerRevision);
        return;
      }
      this.store.markCodexStale();
      this.store.markObjectivesStale();
      this.completedDialogueId = dialogueId;
      await this.refresh(outcome.result.playerRevision);
    } else if (outcome.kind !== 'ignored') {
      await this.refresh(this.store.playerRevision ?? 0,
        outcome.kind === 'ambiguous' ? 'Could not confirm that choice. You can retry it safely.'
          : 'That choice was unavailable. Your place has been refreshed.');
    }
  }

  async provision(): Promise<void> {
    const view = this.current;
    if (view.kind !== 'provision') return;
    const csrf = this.store.bootstrap?.session.csrf_token;
    if (!csrf) return this.set({ kind: 'error', message: 'Your session needs to be refreshed. Try again.' });
    this.provisionAttempt.begin('starting-warband', null);
    this.set({ kind: 'submitting', status: view.status });
    const outcome = await this.provisionAttempt.submit((_request, key) => this.api.provisionOnboarding(csrf, key));
    if (this.disposed) return;
    if (outcome.kind === 'success') {
      await this.refresh(outcome.result.player_revision);
    } else if (outcome.kind !== 'ignored') {
      await this.refresh(this.store.playerRevision ?? 0,
        outcome.kind === 'ambiguous' ? 'Could not confirm your Warband. You can retry safely.'
          : 'Your Warband could not be established. Your place has been refreshed.');
    }
  }

  private isCurrent(generation: number): boolean { return !this.disposed && generation === this.generation; }

  private set(view: OnboardingView): void {
    if (this.disposed) return;
    this.current = view;
    for (const listener of this.listeners) listener(view);
  }
}

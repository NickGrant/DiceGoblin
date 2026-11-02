import { DialogueCompletion, DialogueRead } from './dialogue-contracts';
import { GameStore, parseGameBootstrapEnvelope } from './game-store';
import { RetainedMutationAttempt } from './retained-mutation-attempt';
import { RuntimeApiClient } from './runtime-api-client';

export type PostFarmWhimView =
  | { readonly kind: 'loading' }
  | { readonly kind: 'dialogue'; readonly dialogue: DialogueRead; readonly message: string | null }
  | { readonly kind: 'submitting'; readonly dialogue: DialogueRead }
  | { readonly kind: 'retryable'; readonly dialogue: DialogueRead; readonly message: string }
  | { readonly kind: 'sync_error'; readonly message: string }
  | { readonly kind: 'complete' }
  | { readonly kind: 'unavailable' }
  | { readonly kind: 'error'; readonly message: string };

/** Transient Camp presentation of the server-owned post-Farm Whim choice. */
export class PostFarmWhimFlow {
  private current: PostFarmWhimView = { kind: 'loading' };
  private readonly listeners = new Set<(view: PostFarmWhimView) => void>();
  private attempt = new RetainedMutationAttempt<{ dialogueId: string; choiceId: string }, DialogueCompletion>();
  private completed: DialogueCompletion | null = null;
  private generation = 0;
  private disposed = false;

  constructor(private readonly api: RuntimeApiClient, private readonly store: GameStore) {}

  get view(): PostFarmWhimView { return this.current; }

  subscribe(listener: (view: PostFarmWhimView) => void): () => void {
    this.listeners.add(listener);
    listener(this.current);
    return () => this.listeners.delete(listener);
  }

  dispose(): void { this.disposed = true; this.generation++; this.listeners.clear(); }

  async refresh(): Promise<void> {
    const generation = ++this.generation;
    this.set({ kind: 'loading' });
    try {
      const bootstrap = await this.readBootstrap(this.completed?.playerRevision ?? 0);
      if (!this.currentGeneration(generation)) return;
      if (bootstrap.progression.unlock_ids.includes('unlock.region.mountains')) {
        if (bootstrap.progression.camp_whim_dialogue_id !== null) throw new Error('Completed Whim remains pending.');
        this.set({ kind: 'complete' }); return;
      }
      if (this.completed) throw new Error('Whim completion did not grant Mountains.');
      const dialogueId = bootstrap.progression.camp_whim_dialogue_id;
      if (!dialogueId) { this.set({ kind: 'unavailable' }); return; }
      const dialogue = await this.api.getDialogue(dialogueId);
      if (!this.currentGeneration(generation)) return;
      if (dialogue.id !== dialogueId || dialogue.choices.length === 0) throw new Error('Whim dialogue is incoherent.');
      this.set({ kind: 'dialogue', dialogue, message: null });
    } catch {
      if (this.currentGeneration(generation)) this.set({ kind: 'error', message: 'Could not confirm the Whim conversation. Try again.' });
    }
  }

  async choose(choiceId: string): Promise<void> {
    const view = this.current;
    if ((view.kind !== 'dialogue' && view.kind !== 'retryable')
      || !view.dialogue.choices.some((choice) => choice.id === choiceId)) return;
    const bootstrap = this.store.bootstrap;
    const dialogueId = bootstrap?.progression.camp_whim_dialogue_id;
    if (!bootstrap || dialogueId !== view.dialogue.id) {
      this.set({ kind: 'error', message: 'The Whim conversation changed. Reload Camp.' }); return;
    }
    this.attempt.begin(`${dialogueId}:${choiceId}`, { dialogueId, choiceId });
    this.set({ kind: 'submitting', dialogue: view.dialogue });
    const outcome = await this.attempt.submit((request, key) =>
      this.api.completeDialogue(request.dialogueId, request.choiceId, bootstrap.session.csrf_token, key));
    if (this.disposed) return;
    if (outcome.kind === 'success') {
      const result = outcome.result;
      if (result.knowledge?.codexId !== 'codex.camp.wrong_machine_search'
        || result.unlock?.unlockId !== 'unlock.region.mountains' || result.unlock.outcome !== 'granted') {
        this.set({ kind: 'error', message: 'The Whim reward could not be verified. Reload Camp.' }); return;
      }
      try { this.store.reconcileDialogueCompletion(result); } catch { /* authoritative bootstrap below resolves revision jumps */ }
      this.store.markCodexStale();
      this.store.markObjectivesStale();
      this.completed = result;
      await this.refresh();
      if (this.current.kind === 'error') this.set({ kind: 'sync_error', message: 'The choice completed. Retry synchronization.' });
    } else if (outcome.kind === 'ambiguous') {
      try {
        const fresh = await this.readBootstrap();
        if (fresh.progression.unlock_ids.includes('unlock.region.mountains')
          && fresh.progression.camp_whim_dialogue_id === null) {
          this.store.markCodexStale();
          this.store.markObjectivesStale();
          this.set({ kind: 'complete' }); return;
        }
      } catch { /* Retain the same idempotency key for retry. */ }
      this.set({ kind: 'retryable', dialogue: view.dialogue,
        message: 'Could not confirm the choice. Retry the same request safely.' });
    } else if (outcome.kind === 'rejected') {
      await this.refresh();
      if (this.current.kind === 'dialogue') this.set({ kind: 'dialogue', dialogue: this.current.dialogue,
        message: 'That choice was unavailable. Try again.' });
    }
  }

  private async readBootstrap(minRevision = 0): Promise<NonNullable<GameStore['bootstrap']>> {
    const prior = this.store.bootstrap;
    if (!prior) throw new Error('Camp bootstrap is missing.');
    const fresh = parseGameBootstrapEnvelope(await this.api.getBootstrap());
    if (fresh.account.id !== prior.account.id || fresh.content_revision !== prior.content_revision
      || fresh.player.player_revision < Math.max(minRevision, prior.player.player_revision)) {
      throw new Error('Whim bootstrap is stale or belongs to another player.');
    }
    this.store.hydrateBootstrap(fresh);
    return fresh;
  }

  private currentGeneration(generation: number): boolean { return !this.disposed && generation === this.generation; }
  private set(view: PostFarmWhimView): void {
    if (this.disposed) return;
    this.current = view;
    for (const listener of this.listeners) listener(view);
  }
}

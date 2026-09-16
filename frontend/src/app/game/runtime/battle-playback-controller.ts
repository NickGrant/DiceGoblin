import { BattleOutcome, BattlePlaybackEvent, BattlePlaybackResult } from './battle-playback-contracts';

export type PlaybackControllerState = 'playing' | 'paused' | 'complete' | 'error';
export interface BattleParticipantPresentation {
  readonly combatantKey: string; readonly side: 'player' | 'enemy'; readonly displayName: string; readonly artKey: string;
  readonly position: { readonly x: number; readonly y: number }; readonly maxHp: number;
  currentHp: number; defeated: boolean; readonly statuses: Set<string>;
}
export interface BattlePresentationSnapshot {
  readonly state: PlaybackControllerState; readonly nextSequence: number; readonly consumedSequences: readonly number[];
  readonly participants: readonly BattleParticipantPresentation[]; readonly actorKey: string | null; readonly targetKey: string | null;
  readonly caption: string; readonly dice: string | null; readonly hit: string | null; readonly outcome: BattleOutcome | null;
}

export class BattlePlaybackController {
  private readonly participantsByKey = new Map<string, BattleParticipantPresentation>();
  private index = 0; private currentState: PlaybackControllerState = 'playing'; private consumed: number[] = [];
  private actor: string | null = null; private target: string | null = null; private captionText = 'Battle ready';
  private diceText: string | null = null; private hitText: string | null = null; private finalOutcome: BattleOutcome | null = null;

  constructor(readonly result: BattlePlaybackResult) {
    for (const participant of result.battle.participants) this.participantsByKey.set(participant.combatantKey, {
      combatantKey: participant.combatantKey, side: participant.side, displayName: participant.displayName, artKey: participant.artKey,
      position: participant.position, maxHp: participant.maxHp, currentHp: participant.initialHp,
      defeated: participant.initialHp === 0, statuses: new Set<string>(),
    });
  }

  get snapshot(): BattlePresentationSnapshot {
    return { state: this.currentState, nextSequence: this.index, consumedSequences: [...this.consumed],
      participants: [...this.participantsByKey.values()], actorKey: this.actor, targetKey: this.target,
      caption: this.captionText, dice: this.diceText, hit: this.hitText, outcome: this.finalOutcome };
  }

  pause(): void { if (this.currentState === 'playing') this.currentState = 'paused'; }
  resume(): void { if (this.currentState === 'paused') this.currentState = 'playing'; }

  advance(): BattlePlaybackEvent | null {
    if (this.currentState !== 'playing') return null;
    const event = this.result.battle.events[this.index];
    if (!event) { this.fail(); return null; }
    try { this.apply(event); } catch { this.fail(); return null; }
    this.consumed.push(event.sequence); this.index += 1;
    return event;
  }

  durationFor(event: BattlePlaybackEvent): number {
    if (event.type === 'battle_started' || event.type === 'round_started') return 450;
    if (event.type === 'dice_rolled' || event.type === 'hit_resolved' || event.type === 'damage_dealt') return 700;
    if (event.type.startsWith('status_') || event.type === 'death') return 800;
    if (event.type === 'battle_ended') return 0;
    return 550;
  }

  private apply(event: BattlePlaybackEvent): void {
    const facts = event.facts;
    this.actor = typeof facts['actor_key'] === 'string' ? facts['actor_key'] : this.actor;
    this.target = typeof facts['target_key'] === 'string' ? facts['target_key'] : null;
    if (event.type === 'battle_started') this.captionText = 'Battle begins';
    else if (event.type === 'round_started') this.captionText = `Round ${event.round}`;
    else if (event.type === 'action_started') this.captionText = `${this.name(facts['actor_key'])} uses ${this.label(facts['ability_id'])}`;
    else if (event.type === 'action_skipped') this.captionText = `${this.name(facts['actor_key'])} cannot act: ${this.label(facts['reason'])}`;
    else if (event.type === 'dice_rolled') {
      this.diceText = `d${facts['sides']}: ${facts['roll_total']}`;
      this.captionText = `${this.name(facts['actor_key'])} rolls ${this.diceText}`;
    } else if (event.type === 'hit_resolved') {
      this.hitText = String(facts['result']).toUpperCase(); this.captionText = `${this.name(facts['target_key'])}: ${this.hitText}`;
    } else if (event.type === 'damage_dealt') {
      const target = this.participant(facts['target_key']); const hp = facts['hp_after'];
      if (!Number.isSafeInteger(hp) || (hp as number) < 0 || (hp as number) > target.maxHp) throw new Error('invalid recorded HP');
      target.currentHp = hp as number; this.captionText = `${this.name(facts['target_key'])} takes ${facts['amount']} damage`;
    } else if (event.type === 'status_applied') {
      this.participant(facts['target_key']).statuses.add(String(facts['status_id']));
      this.captionText = `${this.name(facts['target_key'])}: ${this.label(facts['status_id'])} applied`;
    } else if (event.type === 'status_resisted') this.captionText = `${this.name(facts['target_key'])} resists ${this.label(facts['status_id'])}`;
    else if (event.type === 'status_removed') {
      this.participant(facts['target_key']).statuses.delete(String(facts['status_id']));
      this.captionText = `${this.name(facts['target_key'])}: ${this.label(facts['status_id'])} removed`;
    } else if (event.type === 'death') {
      const unit = this.participant(facts['combatant_key']); unit.defeated = true; this.captionText = `${unit.displayName} is defeated`;
    } else if (event.type === 'battle_ended') {
      for (const terminal of this.result.battle.participants) {
        const participant = this.participant(terminal.combatantKey);
        participant.currentHp = terminal.terminalHp; participant.defeated = terminal.isDefeated;
        participant.statuses.clear();
        for (const status of terminal.terminalStatuses) participant.statuses.add(String(status['id']));
      }
      this.finalOutcome = facts['outcome'] as BattleOutcome; this.captionText = `${this.label(this.finalOutcome)} · Playback complete`;
      this.currentState = 'complete';
    }
  }

  private participant(key: unknown): BattleParticipantPresentation {
    const participant = typeof key === 'string' ? this.participantsByKey.get(key) : null;
    if (!participant) throw new Error('unknown participant'); return participant;
  }
  private name(key: unknown): string { return this.participant(key).displayName; }
  private label(value: unknown): string { return String(value).split('.').pop()!.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()); }
  private fail(): void { this.currentState = 'error'; this.captionText = 'Playback could not be presented safely.'; }
}

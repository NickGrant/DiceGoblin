import { Injectable } from '@angular/core';
import { BattlePlaybackActionStep, BattlePlaybackParticipant, BattlePlaybackResultSegment, BattlePlaybackSnapshot, BattlePlaybackSnapshotInput } from '../../battle-playback/battle-playback.models';
import { ResolveNodeData, UnitRecord } from '../../models/api.models';

type ConditionDefinition = {
  aliases: string[];
  tooltip: string;
};

@Injectable({ providedIn: 'root' })
export class BattlePlaybackAdapterService {
  private static readonly CONDITION_DEFINITIONS: readonly ConditionDefinition[] = [
    {
      aliases: ['poisoned', 'poison'],
      tooltip: 'Poison deals damage during status phases. It does not stack, and reapplying it refreshes duration.',
    },
    {
      aliases: ['bleeding', 'bleed'],
      tooltip: 'Bleeding increases damage received for a limited duration.',
    },
    {
      aliases: ['bolstered', 'bolster'],
      tooltip: 'Bolstered is a defensive buff that increases Defense by a percentage for a limited duration.',
    },
    {
      aliases: ['sleep', 'sleeping', 'asleep'],
      tooltip: 'Sleep prevents a unit from acting until it expires or the unit takes damage.',
    },
  ];

  createSnapshot(input: BattlePlaybackSnapshotInput): BattlePlaybackSnapshot | null {
    const result = input.result;
    if (!result) {
      return null;
    }

    const nodeType = this.resolveNodeType(result);
    const timeline = this.buildTimeline(result, input.playerUnits, input.diceInventory, input.abilityNames);
    const participants = this.buildParticipants(result, input.playerUnits);

    return {
      metadata: {
        runId: input.runId,
        nodeId: input.nodeId,
        battleId: result.battle.battle_id,
        nodeType,
        battleResult: result.battle.outcome,
        status: result.battle.status,
        rounds: result.battle.rounds,
        ticks: result.battle.ticks,
        regionTheme: input.regionTheme ?? null,
      },
      source: result,
      participants,
      timeline,
      rewards: result.battle.reward_preview
        ? {
            nodeType: result.battle.reward_preview.node_type,
            xpTotal: result.battle.reward_preview.xp_total,
            currencySoft: result.battle.reward_preview.currency_soft,
            newUnitLabels: [...result.battle.reward_preview.new_unit_labels],
            newDiceLabels: [...result.battle.reward_preview.new_dice_labels],
          }
        : null,
      presentation: {
        backgroundKey: input.regionTheme ?? null,
        musicIntent: nodeType === 'boss' ? 'music.battle.boss' : 'music.battle.normal',
        ambienceIntent: null,
        reducedMotionMode: 'standard',
      },
    };
  }

  private buildTimeline(
    result: ResolveNodeData,
    playerUnits: UnitRecord[],
    diceInventory: ReadonlyArray<{ id: string; rarity?: string; sides?: number }>,
    abilityNames: ReadonlyMap<string, string>,
  ): BattlePlaybackActionStep[] {
    const events = Array.isArray(result.battle.log?.events) ? result.battle.log?.events : [];

    return events
      .filter((event): event is Record<string, unknown> => event !== null && typeof event === 'object' && event['type'] === 'action')
      .map((event) => {
        const abilityId = this.stringValue(event['ability_id']);
        const resultParts = this.resultParts(event);
        return {
          type: 'action',
          round: this.numberValue(event['round']),
          tick: this.numberValue(event['tick']),
          side: this.stringValue(event['side']) === 'enemy' ? 'enemy' : 'player',
          actor: this.resolveActorRef(event, playerUnits),
          target: this.resolveTargetRef(event, playerUnits),
          abilityId,
          abilityName: abilityNames.get(abilityId) ?? this.humanizeId(abilityId || 'ability'),
          diceSummary: this.describeDice(event, diceInventory),
          resultSummary: resultParts.join(' | '),
          resultSegments: this.buildResultSegments(resultParts),
        };
      });
  }

  private buildParticipants(
    result: ResolveNodeData,
    playerUnits: UnitRecord[],
  ): { player: BattlePlaybackParticipant[]; enemy: BattlePlaybackParticipant[] } {
    const player = new Map<string, BattlePlaybackParticipant>();
    const enemy = new Map<string, BattlePlaybackParticipant>();
    const events = Array.isArray(result.battle.log?.events) ? result.battle.log?.events : [];

    for (const event of events) {
      if (!event || typeof event !== 'object' || event['type'] !== 'action') {
        continue;
      }

      const actorRef = this.resolveActorRef(event as Record<string, unknown>, playerUnits);
      const targetRef = this.resolveTargetRef(event as Record<string, unknown>, playerUnits);
      this.collectParticipant(actorRef, playerUnits, player, enemy);
      this.collectParticipant(targetRef, playerUnits, player, enemy);
    }

    return {
      player: [...player.values()],
      enemy: [...enemy.values()],
    };
  }

  private collectParticipant(
    ref: { participantId: string | null; unitId: string | null; enemySlug: string | null; name: string; currentHp: number; maxHp: number },
    playerUnits: UnitRecord[],
    player: Map<string, BattlePlaybackParticipant>,
    enemy: Map<string, BattlePlaybackParticipant>,
  ): void {
    if (!ref.participantId) {
      return;
    }

    if (ref.unitId) {
      if (player.has(ref.participantId)) {
        return;
      }

      const unit = playerUnits.find((entry) => entry.id === ref.unitId);
      player.set(ref.participantId, {
        participantId: ref.participantId,
        side: 'player',
        unitId: ref.unitId,
        enemySlug: null,
        name: ref.name,
        spriteKey: unit?.unit_type_slug ?? 'goblin_bruiser',
        portraitKey: null,
        maxHp: ref.maxHp,
        startingHp: unit?.current_hp ?? ref.currentHp,
      });
      return;
    }

    if (!ref.enemySlug || enemy.has(ref.participantId)) {
      return;
    }

    enemy.set(ref.participantId, {
      participantId: ref.participantId,
      side: 'enemy',
      unitId: null,
      enemySlug: ref.enemySlug,
      name: ref.name,
      spriteKey: ref.enemySlug,
      portraitKey: null,
      maxHp: ref.maxHp,
      startingHp: ref.maxHp,
    });
  }

  private resolveNodeType(result: ResolveNodeData): string {
    const previewType = result.battle.reward_preview?.node_type;
    if (typeof previewType === 'string' && previewType.length > 0) {
      return previewType;
    }

    const metaNodeType = result.battle.log?.meta?.['node_type'];
    return typeof metaNodeType === 'string' && metaNodeType.length > 0 ? metaNodeType : 'combat';
  }

  private resolveActorRef(event: Record<string, unknown>, playerUnits: UnitRecord[]) {
    const side = this.stringValue(event['side']) === 'enemy' ? 'enemy' : 'player';
    if (side === 'player') {
      const unitId = this.stringValue(event['actor_unit_instance_id']);
      const unit = playerUnits.find((entry) => entry.id === unitId);
      return {
        participantId: unitId || null,
        unitId: unitId || null,
        enemySlug: null,
        name: unit?.name ?? `Unit ${unitId}`,
        currentHp: this.resolveHpValue(event['actor_hp_after'], unit?.current_hp),
        maxHp: this.resolveHpValue(event['actor_max_hp'], unit?.max_hp),
      };
    }

    const enemySlug = this.stringValue(event['actor_enemy_slug']);
    return {
      participantId: enemySlug ? `enemy:${enemySlug}` : null,
      unitId: null,
      enemySlug: enemySlug || null,
      name: this.humanizeId(enemySlug || 'enemy'),
      currentHp: this.resolveHpValue(event['actor_hp_after'], 0),
      maxHp: this.resolveHpValue(event['actor_max_hp'], 0),
    };
  }

  private resolveTargetRef(event: Record<string, unknown>, playerUnits: UnitRecord[]) {
    const targetUnitId = this.stringValue(event['target_unit_instance_id']);
    if (targetUnitId.length > 0) {
      const unit = playerUnits.find((entry) => entry.id === targetUnitId);
      return {
        participantId: targetUnitId,
        unitId: targetUnitId,
        enemySlug: null,
        name: unit?.name ?? `Unit ${targetUnitId}`,
        currentHp: this.resolveHpValue(event['target_hp_after'], unit?.current_hp),
        maxHp: this.resolveHpValue(event['target_max_hp'], unit?.max_hp),
      };
    }

    const enemySlug = this.stringValue(event['target_enemy_slug']);
    return {
      participantId: enemySlug ? `enemy:${enemySlug}` : null,
      unitId: null,
      enemySlug: enemySlug || null,
      name: this.humanizeId(enemySlug || 'enemy'),
      currentHp: this.resolveHpValue(event['target_hp_after'], 0),
      maxHp: this.resolveHpValue(event['target_max_hp'], 0),
    };
  }

  private describeDice(
    event: Record<string, unknown>,
    diceInventory: ReadonlyArray<{ id: string; rarity?: string; sides?: number }>,
  ): string {
    const slotTraces = Array.isArray(event['slot_traces']) ? event['slot_traces'] : [];
    if (!slotTraces.length) {
      return 'No dice';
    }

    return slotTraces
      .map((trace, index) => {
        const slot = trace as Record<string, unknown>;
        const diceId = this.stringValue(slot['dice_instance_id']);
        const sides = this.numberValue(slot['sides']) || 1;
        const emptySlot = !!slot['empty_slot'];
        const die = diceId ? diceInventory.find((entry) => entry.id === diceId) : null;
        const rarity = die?.rarity ? `${die.rarity} ` : '';
        const kindLabel = emptySlot ? 'empty slot' : `${rarity}d${sides}`.trim();
        const rolls = Array.isArray(slot['rolls'])
          ? slot['rolls']
              .map((entry) => this.numberValue((entry as Record<string, unknown>)['roll']))
              .filter((roll) => roll > 0)
          : [];
        const rollLabel = rolls.length ? rolls.join(' + ') : '0';
        return `S${index + 1}: ${kindLabel} -> ${rollLabel}`;
      })
      .join(' | ');
  }

  private resultParts(event: Record<string, unknown>): string[] {
    const primaryOutcome = this.stringValue(event['ability_outcome']);
    if (primaryOutcome.length > 0) {
      return [primaryOutcome];
    }

    return [this.humanizeId(this.stringValue(event['outcome']) || 'resolved')];
  }

  private buildResultSegments(parts: string[]): BattlePlaybackResultSegment[] {
    return parts.flatMap((part, index) => {
      const segments = this.parseConditionSegments(part);
      if (index === parts.length - 1) {
        return segments;
      }

      return [...segments, { text: ' | ', tooltip: null }];
    });
  }

  private parseConditionSegments(value: string): BattlePlaybackResultSegment[] {
    const matches = BattlePlaybackAdapterService.CONDITION_DEFINITIONS.flatMap((definition) =>
      definition.aliases.flatMap((alias) => {
        const pattern = new RegExp(`\\b${this.escapeRegex(alias)}\\b`, 'gi');
        return Array.from(value.matchAll(pattern)).map((match) => ({
          start: match.index ?? 0,
          end: (match.index ?? 0) + match[0].length,
          text: match[0],
          tooltip: definition.tooltip,
        }));
      }),
    ).sort((left, right) => left.start - right.start || right.end - left.end);

    const filteredMatches = matches.filter((match, index) => {
      const previous = matches[index - 1];
      return !previous || match.start >= previous.end;
    });

    if (!filteredMatches.length) {
      return [{ text: value, tooltip: null }];
    }

    const segments: BattlePlaybackResultSegment[] = [];
    let cursor = 0;

    for (const match of filteredMatches) {
      if (match.start > cursor) {
        segments.push({ text: value.slice(cursor, match.start), tooltip: null });
      }

      segments.push({ text: match.text, tooltip: match.tooltip });
      cursor = match.end;
    }

    if (cursor < value.length) {
      segments.push({ text: value.slice(cursor), tooltip: null });
    }

    return segments;
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#\s-]/g)
      .filter((segment) => segment.length)
      .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
      .join(' ');
  }

  private stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
  }

  private numberValue(value: unknown): number {
    return typeof value === 'number' ? value : (typeof value === 'string' && value !== '' ? Number(value) : 0);
  }

  private escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  private resolveHpValue(eventValue: unknown, fallbackValue: unknown): number {
    if (typeof eventValue === 'number') {
      return eventValue;
    }

    return this.numberValue(fallbackValue);
  }
}

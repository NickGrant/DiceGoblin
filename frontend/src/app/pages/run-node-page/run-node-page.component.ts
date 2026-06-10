import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { ResolveNodeData } from '../../core/models/api.models';
import { AbilityCatalogService } from '../../core/services/ability-catalog/ability-catalog.service';
import { RunService } from '../../core/services/run/run.service';
import { SessionService } from '../../core/services/session/session.service';
import { DgAlertComponent } from '../../shared/ui/dg-alert/dg-alert.component';
import { DgCommandBtnDirective } from '../../shared/ui/dg-command-btn/dg-command-btn.directive';
import { DgPageFrameComponent } from '../../shared/ui/dg-page-frame/dg-page-frame.component';

type BattleLogActionViewModel = {
  round: number;
  tick: number;
  actorName: string;
  abilityName: string;
  diceSummary: string;
  targetName: string;
  resultSummary: string;
};

type LootRewardSummary = {
  teeth: number;
  diceLabels: string[];
  unitLabels: string[];
};

@Component({
  selector: 'app-run-node-page',
  standalone: true,
  imports: [DgAlertComponent, DgCommandBtnDirective, DgPageFrameComponent],
  templateUrl: './run-node-page.component.html',
  styleUrl: './run-node-page.component.scss',
})
export class RunNodePageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly runService = inject(RunService);
  private readonly sessionService = inject(SessionService);
  private readonly abilityCatalogService = inject(AbilityCatalogService);

  readonly nodeId = this.route.snapshot.paramMap.get('nodeId') ?? '';
  readonly runId = signal<string | null>(null);
  readonly result = signal<ResolveNodeData | null>(null);
  readonly loading = signal(true);
  readonly busy = signal(false);
  readonly error = signal<string | null>(null);
  readonly abilityCatalogError = this.abilityCatalogService.error;
  readonly abilityCatalog = this.abilityCatalogService.abilityMap;
  readonly resolvedNodeType = computed(() => {
    const previewType = this.result()?.battle.reward_preview?.node_type;
    if (typeof previewType === 'string' && previewType.length > 0) {
      return previewType;
    }

    const metaNodeType = this.result()?.battle.log?.meta?.['node_type'];
    return typeof metaNodeType === 'string' ? metaNodeType : 'combat';
  });
  readonly isLootNode = computed(() => this.resolvedNodeType() === 'loot');
  readonly lootRewards = computed<LootRewardSummary | null>(() => {
    const preview = this.result()?.battle.reward_preview;
    if (!preview || this.resolvedNodeType() !== 'loot') {
      return null;
    }

    return {
      teeth: this.numberValue(preview.currency_soft),
      diceLabels: Array.isArray(preview.new_dice_labels) ? preview.new_dice_labels : [],
      unitLabels: Array.isArray(preview.new_unit_labels) ? preview.new_unit_labels : [],
    };
  });
  readonly actionLog = computed<BattleLogActionViewModel[]>(() => {
    const log = this.result()?.battle.log;
    const events = Array.isArray(log?.events) ? log.events : [];

    return events
      .filter((event): event is Record<string, unknown> => event !== null && typeof event === 'object' && event['type'] === 'action')
      .map((event) => {
        const round = this.numberValue(event['round']);
        const tick = this.numberValue(event['tick']);
        const abilityId = this.stringValue(event['ability_id']);
        return {
          round,
          tick,
          actorName: this.resolveActorName(event),
          abilityName: this.resolveAbilityName(abilityId),
          diceSummary: this.describeDice(event),
          targetName: this.resolveTargetName(event),
          resultSummary: this.describeResult(event),
        };
      });
  });

  constructor() {
    void this.abilityCatalogService.load();
    void this.loadRun();
  }

  async loadRun(): Promise<void> {
    this.loading.set(true);
    try {
      const current = await this.runService.getCurrentRun();
      if (!current.ok || !current.data.run) {
        this.error.set(current.ok ? 'No active run.' : current.error.message);
        return;
      }
      this.runId.set(current.data.run.run_id);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to load node.');
    } finally {
      this.loading.set(false);
    }
  }

  async resolveNode(): Promise<void> {
    if (!this.runId()) {
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.resolveNode(this.runId()!, this.nodeId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      this.result.set(response.data);
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to resolve node.');
    } finally {
      this.busy.set(false);
    }
  }

  async claimRewards(): Promise<void> {
    const battleId = this.result()?.battle.battle_id;
    if (!battleId) {
      await this.router.navigateByUrl('/run/map');
      return;
    }

    this.busy.set(true);
    this.error.set(null);
    try {
      const response = await this.runService.claimBattleRewards(battleId);
      if (!response.ok) {
        this.error.set(response.error.message);
        return;
      }
      if (response.data.run_resolution?.status && response.data.run_resolution.status !== 'active') {
        await this.router.navigateByUrl('/run/summary');
      } else {
        await this.router.navigateByUrl('/run/map');
      }
    } catch (error) {
      this.error.set(error instanceof Error ? error.message : 'Unable to claim rewards.');
    } finally {
      this.busy.set(false);
    }
  }

  private resolveActorName(event: Record<string, unknown>): string {
    const side = this.stringValue(event['side']);
    if (side === 'player') {
      const unitId = this.stringValue(event['actor_unit_instance_id']);
      return this.sessionService.units().find((unit) => unit.id === unitId)?.name ?? `Unit ${unitId}`;
    }

    return this.humanizeId(this.stringValue(event['actor_enemy_slug']) || 'enemy');
  }

  private resolveTargetName(event: Record<string, unknown>): string {
    const side = this.stringValue(event['side']);
    if (side === 'player') {
      const allyTargetId = this.stringValue(event['target_unit_instance_id']);
      if (allyTargetId.length > 0) {
        return this.sessionService.units().find((unit) => unit.id === allyTargetId)?.name ?? `Unit ${allyTargetId}`;
      }

      return this.humanizeId(this.stringValue(event['target_enemy_slug']) || 'enemy');
    }

    const enemyTargetSlug = this.stringValue(event['target_enemy_slug']);
    if (enemyTargetSlug.length > 0) {
      return this.humanizeId(enemyTargetSlug);
    }

    const unitId = this.stringValue(event['target_unit_instance_id']);
    return this.sessionService.units().find((unit) => unit.id === unitId)?.name ?? `Unit ${unitId}`;
  }

  private resolveAbilityName(abilityId: string): string {
    return this.abilityCatalog().get(abilityId)?.display_name ?? this.humanizeId(abilityId || 'ability');
  }

  private describeDice(event: Record<string, unknown>): string {
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
        const die = diceId ? this.sessionService.dice().find((entry) => entry.id === diceId) : null;
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

  private describeResult(event: Record<string, unknown>): string {
    const parts = [
      this.stringValue(event['ability_outcome']),
      this.stringValue(event['affix_outcome']),
    ].filter((value) => value.length > 0);

    const hpAfter = event['target_hp_after'];
    if (typeof hpAfter === 'number') {
      parts.push(`target HP ${hpAfter}`);
    }

    return parts.join(' | ') || this.humanizeId(this.stringValue(event['outcome']) || 'resolved');
  }

  private humanizeId(value: string): string {
    return value
      .split(/[_#]/g)
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
}


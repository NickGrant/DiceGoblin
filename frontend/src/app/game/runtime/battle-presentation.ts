import { BattleRunNodeResolutionResult } from './run-node-resolution-contracts';

export interface BattlePresentationMarker {
  readonly version: 1;
  readonly accountId: string;
  readonly battleId: string;
  readonly runId: string;
  readonly runNodeId: string;
}

export interface BattleMarkerStorage { getItem(key: string): string | null; setItem(key: string, value: string): void; removeItem(key: string): void; }

const STORAGE_KEY = 'dice-goblins:battle-presentation:v1';
const positiveId = /^[1-9][0-9]*$/;

export class BattlePresentationState {
  private currentMarker: BattlePresentationMarker | null = null;
  private resolutionResult: BattleRunNodeResolutionResult | null = null;

  constructor(private readonly storage: BattleMarkerStorage | null = browserSessionStorage()) {}

  get marker(): BattlePresentationMarker | null { return this.currentMarker; }
  get resolution(): BattleRunNodeResolutionResult | null { return this.resolutionResult; }

  restoreForAccount(accountId: string): BattlePresentationMarker | null {
    let raw: string | null = null;
    try { raw = this.storage?.getItem(STORAGE_KEY) ?? null; } catch { this.clear(); return null; }
    if (!raw) { this.currentMarker = null; return null; }
    try {
      const value = JSON.parse(raw) as Record<string, unknown>;
      const keys = Object.keys(value).sort().join(',');
      if (keys !== 'accountId,battleId,runId,runNodeId,version' || value['version'] !== 1
        || value['accountId'] !== accountId || typeof value['accountId'] !== 'string'
        || !positiveId.test(String(value['battleId'])) || !positiveId.test(String(value['runId']))
        || !positiveId.test(String(value['runNodeId']))) throw new Error('invalid marker');
      this.currentMarker = Object.freeze({ version: 1, accountId, battleId: String(value['battleId']),
        runId: String(value['runId']), runNodeId: String(value['runNodeId']) });
      return this.currentMarker;
    } catch { this.clear(); return null; }
  }

  establish(accountId: string, battleId: string, runId: string, runNodeId: string): BattlePresentationMarker {
    if (!positiveId.test(accountId) || !positiveId.test(battleId) || !positiveId.test(runId) || !positiveId.test(runNodeId))
      throw new Error('Battle presentation identity is invalid.');
    const marker = Object.freeze({ version: 1 as const, accountId, battleId, runId, runNodeId });
    this.currentMarker = marker;
    try { this.storage?.setItem(STORAGE_KEY, JSON.stringify(marker)); } catch { /* marker remains valid for this runtime */ }
    return marker;
  }

  retainResolution(result: BattleRunNodeResolutionResult): void { this.resolutionResult = result; }
  clear(): void { this.currentMarker = null; this.resolutionResult = null; try { this.storage?.removeItem(STORAGE_KEY); } catch { /* unavailable storage */ } }
}

function browserSessionStorage(): BattleMarkerStorage | null {
  try { return typeof window === 'undefined' ? null : window.sessionStorage; } catch { return null; }
}

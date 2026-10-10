import { resolveApiBaseUrl } from '../../core/config/runtime-config';
import {
  SquadConfigurationPayload,
  SquadDeleteResult,
  SquadMutationResult,
  WarbandContractError,
  parseSquadDeleteEnvelope,
  parseSquadMutationEnvelope,
} from './warband-contracts';
import { ClientContentRegistry } from './client-content-registry';
import {
  UnitDetailContractError,
  UnitLoadoutPayload,
  UnitMutationResult,
  parseUnitMutationEnvelope,
} from './unit-detail-contracts';
import { WarbandDieSummary } from './warband-contracts';
import {
  CurrentRunResult,
  RunContractError,
  RunAbandonResult,
  RunStartResult,
  parseCurrentRunEnvelope,
  parseRunAbandonEnvelope,
  parseRunStartEnvelope,
} from './run-contracts';
import { BattlePlaybackContractError, BattlePlaybackResult, parseBattlePlaybackEnvelope } from './battle-playback-contracts';
import { RunNodeResolutionContractError, RunNodeResolutionResult, parseRunNodeResolutionEnvelope } from './run-node-resolution-contracts';
import { ShopContractError, ShopPurchasePayload, ShopPurchaseResult, parseShopPurchaseEnvelope } from './shop-contracts';
import { ConsumableContractError, ConsumableUsePayload, EnergyRestoreResult, RunUnitHealResult,
  parseEnergyRestoreEnvelope, parseRunUnitHealEnvelope } from './consumable-contracts';
import { DiceLifecycleContractError, DiceSellResult, DiceSalvageResult,
  parseDiceSellEnvelope, parseDiceSalvageEnvelope } from './dice-lifecycle-contracts';
import { AcademyCatalogResult, AcademyContractError, AcademyUpgradePayload, AcademyUpgradeResult,
  canonicalAcademyUpgradePayload, parseAcademyCatalogEnvelope, parseAcademyUpgradeEnvelope } from './academy-contracts';
import { UnitPromotionContractError, UnitPromotionOptionsResult, canonicalUnitId,
  parseUnitPromotionOptionsEnvelope } from './unit-promotion-contracts';
import { UnitPromotionMutationContractError, UnitPromotionPayload, UnitPromotionResult,
  canonicalUnitPromotionPayload, parseUnitPromotionMutationEnvelope } from './unit-promotion-mutation-contracts';
import { ReconstructionPayload, ReconstructionResult, WrongMachineCatalog, WrongMachineContractError,
  canonicalReconstructionPayload, parseReconstructionEnvelope, parseWrongMachineCatalogEnvelope } from './wrong-machine-contracts';

export type RuntimeFetch = (input: RequestInfo | URL, init?: RequestInit) => Promise<Response>;

export type RuntimeApiErrorKind = 'unauthorized' | 'http' | 'network' | 'malformed-response';

export class RuntimeApiError extends Error {
  constructor(
    readonly kind: RuntimeApiErrorKind,
    readonly status: number | null = null,
    readonly code: string | null = null,
  ) {
    super(`Runtime API request failed: ${kind}`);
    this.name = 'RuntimeApiError';
  }
}

const browserFetch: RuntimeFetch = (input, init) => window.fetch(input, init);

/** Framework-neutral API access owned by the mounted Phaser runtime. */
export class RuntimeApiClient {
  readonly baseUrl: string;

  constructor(
    private readonly fetchRequest: RuntimeFetch = browserFetch,
    baseUrl: string = resolveApiBaseUrl(),
  ) {
    this.baseUrl = baseUrl.replace(/\/+$/, '');
  }

  async getBootstrap(): Promise<unknown> {
    return this.get('/api/v1/game/bootstrap');
  }

  async getUnits(): Promise<unknown> {
    return this.get('/api/v1/units');
  }

  async getUnitDetail(unitId: string): Promise<unknown> {
    return this.get(`/api/v1/units/${encodeURIComponent(unitId)}`);
  }

  async getUnitPromotionOptions(unitId: string, content: ClientContentRegistry): Promise<UnitPromotionOptionsResult> {
    if (!canonicalUnitId(unitId)) throw new RuntimeApiError('malformed-response');
    const value = await this.get(`/api/v1/units/${encodeURIComponent(unitId)}/promotion-options`);
    try { return parseUnitPromotionOptionsEnvelope(value, unitId, content); }
    catch (error) {
      if (error instanceof UnitPromotionContractError) throw new RuntimeApiError('malformed-response', 200);
      throw error;
    }
  }

  async promoteUnit(
    unitId: string, request: UnitPromotionPayload, csrfToken: string, idempotencyKey: string,
    content: ClientContentRegistry, dice: readonly WarbandDieSummary[],
  ): Promise<UnitPromotionResult> {
    if (!canonicalUnitId(unitId) || idempotencyKey.trim() === '') throw new RuntimeApiError('malformed-response');
    let canonical: UnitPromotionPayload;
    try { canonical = canonicalUnitPromotionPayload(request); }
    catch (error) {
      if (error instanceof UnitPromotionMutationContractError) throw new RuntimeApiError('malformed-response');
      throw error;
    }
    return this.mutate(`/api/v1/units/${unitId}/promote`, 'POST', csrfToken, canonical, idempotencyKey,
      (value) => parseUnitPromotionMutationEnvelope(value, unitId, canonical, content, dice));
  }

  async getDice(): Promise<unknown> {
    return this.get('/api/v1/dice');
  }

  async getSquads(): Promise<unknown> {
    return this.get('/api/v1/squads');
  }

  async getItems(): Promise<unknown> {
    return this.get('/api/v1/items');
  }

  async getShop(): Promise<unknown> {
    return this.get('/api/v1/shop');
  }

  async getAcademy(content: ClientContentRegistry): Promise<AcademyCatalogResult> {
    const value = await this.get('/api/v1/academy');
    try { return parseAcademyCatalogEnvelope(value, content); }
    catch (error) {
      if (error instanceof AcademyContractError) throw new RuntimeApiError('malformed-response', 200);
      throw error;
    }
  }

  async upgradeAcademy(request: AcademyUpgradePayload, csrfToken: string, idempotencyKey: string): Promise<AcademyUpgradeResult> {
    if (idempotencyKey.trim() === '') throw new RuntimeApiError('malformed-response');
    let canonical: AcademyUpgradePayload;
    try { canonical = canonicalAcademyUpgradePayload(request); }
    catch (error) {
      if (error instanceof AcademyContractError) throw new RuntimeApiError('malformed-response');
      throw error;
    }
    return this.mutate('/api/v1/academy/upgrade', 'POST', csrfToken, canonical, idempotencyKey,
      (value) => parseAcademyUpgradeEnvelope(value, canonical));
  }

  async getWrongMachine(content: ClientContentRegistry): Promise<WrongMachineCatalog> {
    const value = await this.get('/api/v1/wrong-machine');
    try { return parseWrongMachineCatalogEnvelope(value, content); }
    catch (error) {
      if (error instanceof WrongMachineContractError) throw new RuntimeApiError('malformed-response', 200);
      throw error;
    }
  }

  async reconstructKin(request: ReconstructionPayload, csrfToken: string, idempotencyKey: string,
    content: ClientContentRegistry): Promise<ReconstructionResult> {
    if (idempotencyKey.trim() === '') throw new RuntimeApiError('malformed-response');
    let canonical: ReconstructionPayload;
    try { canonical = canonicalReconstructionPayload(request); }
    catch (error) {
      if (error instanceof WrongMachineContractError) throw new RuntimeApiError('malformed-response');
      throw error;
    }
    return this.mutate('/api/v1/wrong-machine/reconstruct', 'POST', csrfToken, canonical, idempotencyKey,
      (value) => parseReconstructionEnvelope(value, canonical, content));
  }

  async sellDie(diceId: string, csrfToken: string, idempotencyKey: string): Promise<DiceSellResult> {
    if (!/^[1-9][0-9]*$/.test(diceId) || idempotencyKey.trim() === '')
      throw new RuntimeApiError('malformed-response');
    return this.mutate(`/api/v1/dice/${diceId}/sell`, 'POST', csrfToken, undefined, idempotencyKey,
      (value) => parseDiceSellEnvelope(value, diceId));
  }

  async salvageDie(diceId: string, csrfToken: string, idempotencyKey: string): Promise<DiceSalvageResult> {
    if (!/^[1-9][0-9]*$/.test(diceId) || idempotencyKey.trim() === '')
      throw new RuntimeApiError('malformed-response');
    return this.mutate(`/api/v1/dice/${diceId}/salvage`, 'POST', csrfToken, undefined, idempotencyKey,
      (value) => parseDiceSalvageEnvelope(value, diceId));
  }

  async purchaseShopOffer(
    request: ShopPurchasePayload,
    csrfToken: string,
    idempotencyKey: string,
    content: ClientContentRegistry,
  ): Promise<ShopPurchaseResult> {
    if (idempotencyKey.trim() === '') throw new RuntimeApiError('malformed-response');
    return this.mutate('/api/v1/shop/purchase', 'POST', csrfToken, request, idempotencyKey,
      (value) => parseShopPurchaseEnvelope(value, request, content));
  }

  async restoreEnergy(
    itemId: string, csrfToken: string, idempotencyKey: string, content: ClientContentRegistry,
  ): Promise<EnergyRestoreResult> {
    const request: ConsumableUsePayload = { item_id: itemId };
    if (!/^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(itemId) || idempotencyKey.trim() === '')
      throw new RuntimeApiError('malformed-response');
    return this.mutate('/api/v1/energy/restore', 'POST', csrfToken, request, idempotencyKey,
      (value) => parseEnergyRestoreEnvelope(value, request, content));
  }

  async healRunUnit(
    runId: string, unitId: string, itemId: string, csrfToken: string, idempotencyKey: string,
    content: ClientContentRegistry,
  ): Promise<RunUnitHealResult> {
    const request: ConsumableUsePayload = { item_id: itemId };
    if (!/^[1-9][0-9]*$/.test(runId) || !/^[1-9][0-9]*$/.test(unitId)
      || !/^item\.[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*$/.test(itemId) || idempotencyKey.trim() === '')
      throw new RuntimeApiError('malformed-response');
    return this.mutate(`/api/v1/runs/${runId}/units/${unitId}/heal`, 'POST', csrfToken, request, idempotencyKey,
      (value) => parseRunUnitHealEnvelope(value, runId, unitId, request, content));
  }

  async getCurrentRun(content: ClientContentRegistry): Promise<CurrentRunResult> {
    const value = await this.get('/api/v1/runs/current');
    try {
      return parseCurrentRunEnvelope(value, content);
    } catch (error) {
      if (error instanceof RunContractError) throw new RuntimeApiError('malformed-response', 200);
      throw error;
    }
  }

  async getBattlePlayback(battleId: string): Promise<BattlePlaybackResult> {
    if (!/^[1-9][0-9]*$/.test(battleId)) throw new RuntimeApiError('malformed-response');
    const value = await this.get(`/api/v1/battles/${battleId}/playback`);
    try {
      return parseBattlePlaybackEnvelope(value);
    } catch (error) {
      if (error instanceof BattlePlaybackContractError) throw new RuntimeApiError('malformed-response', 200);
      throw error;
    }
  }

  async resolveRunNode(runId: string, nodeId: string, csrfToken: string, idempotencyKey: string): Promise<RunNodeResolutionResult> {
    if (!/^[1-9][0-9]*$/.test(runId) || !/^[1-9][0-9]*$/.test(nodeId) || idempotencyKey.trim() === '')
      throw new RuntimeApiError('malformed-response');
    return this.mutate(`/api/v1/runs/${runId}/nodes/${nodeId}/resolve`, 'POST', csrfToken, undefined, idempotencyKey,
      parseRunNodeResolutionEnvelope);
  }

  async startRun(
    regionId: string,
    csrfToken: string,
    idempotencyKey: string,
    content: ClientContentRegistry,
  ): Promise<RunStartResult> {
    return this.mutate('/api/v1/runs', 'POST', csrfToken, { region_id: regionId }, idempotencyKey,
      (value) => parseRunStartEnvelope(value, content));
  }

  async abandonRun(
    runId: string,
    csrfToken: string,
    content: ClientContentRegistry,
  ): Promise<RunAbandonResult> {
    if (!/^[1-9][0-9]*$/.test(runId)) throw new RuntimeApiError('malformed-response');
    return this.mutate(`/api/v1/runs/${runId}/abandon`, 'POST', csrfToken, undefined, null,
      (value) => parseRunAbandonEnvelope(value, content));
  }

  async createSquad(
    configuration: SquadConfigurationPayload,
    csrfToken: string,
    idempotencyKey: string,
  ): Promise<SquadMutationResult> {
    return this.mutate('/api/v1/squads', 'POST', csrfToken, configuration, idempotencyKey, parseSquadMutationEnvelope);
  }

  async updateSquad(
    squadId: string,
    configuration: SquadConfigurationPayload,
    csrfToken: string,
  ): Promise<SquadMutationResult> {
    return this.mutate(`/api/v1/squads/${encodeURIComponent(squadId)}`, 'PUT', csrfToken, configuration, null, parseSquadMutationEnvelope);
  }

  async activateSquad(squadId: string, csrfToken: string): Promise<SquadMutationResult> {
    return this.mutate(`/api/v1/squads/${encodeURIComponent(squadId)}/activate`, 'POST', csrfToken, undefined, null, parseSquadMutationEnvelope);
  }

  async deleteSquad(squadId: string, csrfToken: string): Promise<SquadDeleteResult> {
    return this.mutate(`/api/v1/squads/${encodeURIComponent(squadId)}`, 'DELETE', csrfToken, undefined, null, parseSquadDeleteEnvelope);
  }

  async renameUnit(
    unitId: string,
    name: string,
    csrfToken: string,
    content: ClientContentRegistry,
    dice: readonly WarbandDieSummary[],
  ): Promise<UnitMutationResult> {
    return this.mutate(
      `/api/v1/units/${encodeURIComponent(unitId)}/name`, 'PATCH', csrfToken, { name }, null,
      (value) => parseUnitMutationEnvelope(value, content, dice, false),
    );
  }

  async replaceUnitLoadout(
    unitId: string,
    configuration: UnitLoadoutPayload,
    csrfToken: string,
    content: ClientContentRegistry,
    dice: readonly WarbandDieSummary[],
  ): Promise<UnitMutationResult> {
    return this.mutate(
      `/api/v1/units/${encodeURIComponent(unitId)}/loadout`, 'PUT', csrfToken, configuration, null,
      (value) => parseUnitMutationEnvelope(value, content, dice),
    );
  }

  private async get(path: string): Promise<unknown> {
    let response: Response;

    try {
      response = await this.fetchRequest(`${this.baseUrl}${path}`, {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'application/json' },
      });
    } catch {
      throw new RuntimeApiError('network');
    }

    if (!response.ok) {
      throw new RuntimeApiError(response.status === 401 ? 'unauthorized' : 'http', response.status);
    }

    try {
      return await response.json();
    } catch {
      throw new RuntimeApiError('malformed-response', response.status);
    }
  }

  private async mutate<T>(
    path: string,
    method: 'POST' | 'PUT' | 'PATCH' | 'DELETE',
    csrfToken: string,
    body: unknown | undefined,
    idempotencyKey: string | null,
    parse: (value: unknown) => T,
  ): Promise<T> {
    let response: Response;
    const headers: Record<string, string> = { Accept: 'application/json', 'X-CSRF-Token': csrfToken };
    if (body) headers['Content-Type'] = 'application/json';
    if (idempotencyKey) headers['Idempotency-Key'] = idempotencyKey;
    try {
      response = await this.fetchRequest(`${this.baseUrl}${path}`, {
        method, credentials: 'include', headers, ...(body ? { body: JSON.stringify(body) } : {}),
      });
    } catch {
      throw new RuntimeApiError('network');
    }
    let value: unknown;
    try {
      value = await response.json();
    } catch {
      throw new RuntimeApiError('malformed-response', response.status);
    }
    if (!response.ok) {
      const code = this.safeErrorCode(value);
      throw new RuntimeApiError(response.status === 401 ? 'unauthorized' : 'http', response.status, code);
    }
    try {
      return parse(value);
    } catch (error) {
      if (error instanceof WarbandContractError || error instanceof UnitDetailContractError || error instanceof RunContractError
        || error instanceof RunNodeResolutionContractError || error instanceof ShopContractError
        || error instanceof ConsumableContractError || error instanceof DiceLifecycleContractError
        || error instanceof AcademyContractError || error instanceof UnitPromotionMutationContractError
        || error instanceof WrongMachineContractError) {
        throw new RuntimeApiError('malformed-response', response.status);
      }
      throw error;
    }
  }

  private safeErrorCode(value: unknown): string | null {
    if (typeof value !== 'object' || value === null || Array.isArray(value)) return null;
    const error = (value as Record<string, unknown>)['error'];
    if (typeof error !== 'object' || error === null || Array.isArray(error)) return null;
    const code = (error as Record<string, unknown>)['code'];
    return typeof code === 'string' && /^[a-z0-9_]+$/.test(code) ? code : null;
  }
}

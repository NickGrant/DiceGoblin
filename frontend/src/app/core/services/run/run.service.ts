import { Injectable, signal } from '@angular/core';
import {
  AbandonRunResponse,
  BattleClaimResponse,
  ChaosEncounterResponse,
  ChaosFinalizeResponse,
  CurrentRunData,
  CurrentRunEdge,
  CurrentRunNode,
  CreateResponse,
  DialogueNodeCompleteResponse,
  ExitRunResponse,
  RunSummaryPayload,
  ResolveNodeResponse,
  RestFinalizeResponse,
  RestOpenResponse,
  RunResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

export type RunSummaryState = {
  title: string;
  status: string;
  rewards: string[];
  progression: string[];
  survivors: string[];
  defeated: string[];
  meta: NonNullable<RunSummaryPayload['meta']> | null;
  rewardDetail: NonNullable<RunSummaryPayload['reward_detail']> | null;
  stolenPages: NonNullable<RunSummaryPayload['stolen_pages']>;
  progressionDetail: NonNullable<RunSummaryPayload['progression_detail']>;
};

@Injectable({ providedIn: 'root' })
export class RunService {
  private readonly summaryState = signal<RunSummaryState | null>(null);
  readonly summary = this.summaryState.asReadonly();

  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  private mapSummaryState(title: string, status: string, summary?: RunSummaryPayload | null): RunSummaryState {
    return {
      title,
      status,
      rewards: summary?.rewards ?? [],
      progression: summary?.progression ?? [],
      survivors: summary?.survivors ?? [],
      defeated: summary?.defeated ?? [],
      meta: summary?.meta ?? null,
      rewardDetail: summary?.reward_detail ?? null,
      stolenPages: summary?.stolen_pages ?? [],
      progressionDetail: summary?.progression_detail ?? [],
    };
  }

  private normalizeCurrentRunData(data: CurrentRunData): CurrentRunData {
    const map = data.map;
    if (!map) {
      return data;
    }

    const nodes = map.nodes.map((node) => this.normalizeCurrentRunNode(node));
    const edges = map.edges.map((edge, index) => this.normalizeCurrentRunEdge(edge, index));

    return {
      ...data,
      map: {
        ...map,
        nodes,
        edges,
      },
    };
  }

  private normalizeCurrentRunNode(node: CurrentRunNode): CurrentRunNode {
    if (node.meta && typeof node.meta === 'object') {
      return node;
    }

    if (!node.meta_json) {
      return node;
    }

    try {
      const parsed = JSON.parse(node.meta_json);
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return node;
      }

      return {
        ...node,
        meta: parsed as Record<string, unknown>,
      };
    } catch {
      return node;
    }
  }

  private normalizeCurrentRunEdge(edge: CurrentRunEdge, index: number): CurrentRunEdge {
    if (edge.edge_id) {
      return edge;
    }

    return {
      ...edge,
      edge_id: `${edge.from_node_id}->${edge.to_node_id}#${index}`,
    };
  }

  async getCurrentRun(): Promise<RunResponse> {
    const response = await this.apiHttp.get<RunResponse>('/api/v1/runs/current');
    if (!response.ok) {
      return response;
    }

    return {
      ...response,
      data: this.normalizeCurrentRunData(response.data),
    };
  }

  async createRun(regionId: number): Promise<CreateResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<CreateResponse>(
      '/api/v1/runs',
      { region_id: regionId },
    ));
  }

  async abandonRun(runId: string): Promise<AbandonRunResponse> {
    const response = await this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<AbandonRunResponse>(
      `/api/v1/runs/${runId}/abandon`,
      {},
    ));
    if (response.ok) {
      this.summaryState.set(this.mapSummaryState('Returned Home', response.data.status, response.data.run_summary));
    }
    return response;
  }

  async exitRun(runId: string): Promise<ExitRunResponse> {
    const response = await this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<ExitRunResponse>(
      `/api/v1/runs/${runId}/exit`,
      {},
    ));
    if (response.ok) {
      this.summaryState.set(this.mapSummaryState('Run Complete', response.data.status, response.data.run_summary));
    }
    return response;
  }

  async resolveNode(runId: string, nodeId: string, teamId?: string): Promise<ResolveNodeResponse> {
    return this.apiHttp.postWithCsrf<ResolveNodeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/resolve`, {
      ...(teamId ? { team_id: Number(teamId) } : {}),
    });
  }

  generateChaosEncounter(runId: string, nodeId: string): Promise<ChaosEncounterResponse> {
    return this.apiHttp.postWithCsrf<ChaosEncounterResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/chaos/generate`, {});
  }

  rerollChaosEncounter(runId: string, nodeId: string, reelIndex: number): Promise<ChaosEncounterResponse> {
    return this.apiHttp.postWithCsrf<ChaosEncounterResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/chaos/reroll`, {
      reel_index: reelIndex,
    });
  }

  finalizeChaosEncounter(runId: string, nodeId: string): Promise<ChaosFinalizeResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<ChaosFinalizeResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/chaos/finalize`,
      {},
    ));
  }

  async completeDialogueNode(runId: string, nodeId: string): Promise<DialogueNodeCompleteResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DialogueNodeCompleteResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/dialogue/complete`,
      {},
    ));
  }

  async claimBattleRewards(battleId: string): Promise<BattleClaimResponse> {
    const response = await this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<BattleClaimResponse>(
      `/api/v1/battles/${battleId}/claim`,
      {},
    ));
    if (response.ok && response.data.run_summary) {
      this.summaryState.set(
        this.mapSummaryState(
          response.data.run_resolution?.status === 'failed' ? 'Run Failed' : 'Run Summary',
          response.data.run_resolution?.status ?? response.data.status,
          response.data.run_summary,
        ),
      );
    }
    return response;
  }

  openRest(runId: string, nodeId: string): Promise<RestOpenResponse> {
    return this.apiHttp.postWithCsrf<RestOpenResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/open`, {});
  }

  async finalizeRest(runId: string, nodeId: string): Promise<RestFinalizeResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<RestFinalizeResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/rest/finalize`,
      {},
    ));
  }

  clearSummary(): void {
    this.summaryState.set(null);
  }
}


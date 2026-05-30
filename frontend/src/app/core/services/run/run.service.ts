import { Injectable, signal } from '@angular/core';
import {
  AbandonRunResponse,
  BattleClaimResponse,
  CreateResponse,
  ExitRunResponse,
  ResolveNodeResponse,
  RestFinalizeResponse,
  RestOpenResponse,
  RestStateResponse,
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
};

@Injectable({ providedIn: 'root' })
export class RunService {
  private readonly summaryState = signal<RunSummaryState | null>(null);
  readonly summary = this.summaryState.asReadonly();

  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getCurrentRun(): Promise<RunResponse> {
    return this.apiHttp.get<RunResponse>('/api/v1/runs/current');
  }

  async createRun(regionId: number): Promise<CreateResponse> {
    const response = await this.apiHttp.postWithCsrf<CreateResponse>('/api/v1/runs', { region_id: regionId });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async abandonRun(runId: string): Promise<AbandonRunResponse> {
    const response = await this.apiHttp.postWithCsrf<AbandonRunResponse>(`/api/v1/runs/${runId}/abandon`, {});
    if (response.ok) {
      this.summaryState.set({
        title: 'Run Abandoned',
        status: response.data.status,
        rewards: response.data.run_summary?.rewards ?? [],
        progression: response.data.run_summary?.progression ?? [],
        survivors: response.data.run_summary?.survivors ?? [],
        defeated: response.data.run_summary?.defeated ?? [],
      });
    }
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async exitRun(runId: string): Promise<ExitRunResponse> {
    const response = await this.apiHttp.postWithCsrf<ExitRunResponse>(`/api/v1/runs/${runId}/exit`, {});
    if (response.ok) {
      this.summaryState.set({
        title: 'Run Complete',
        status: response.data.status,
        rewards: response.data.run_summary?.rewards ?? [],
        progression: response.data.run_summary?.progression ?? [],
        survivors: response.data.run_summary?.survivors ?? [],
        defeated: response.data.run_summary?.defeated ?? [],
      });
    }
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async resolveNode(runId: string, nodeId: string, teamId?: string): Promise<ResolveNodeResponse> {
    return this.apiHttp.postWithCsrf<ResolveNodeResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/resolve`, {
      ...(teamId ? { team_id: Number(teamId) } : {}),
    });
  }

  async claimBattleRewards(battleId: string): Promise<BattleClaimResponse> {
    const response = await this.apiHttp.postWithCsrf<BattleClaimResponse>(`/api/v1/battles/${battleId}/claim`, {});
    if (response.ok && response.data.run_summary) {
      this.summaryState.set({
        title: response.data.run_resolution?.status === 'failed' ? 'Run Failed' : 'Run Summary',
        status: response.data.run_resolution?.status ?? response.data.status,
        rewards: response.data.run_summary.rewards ?? [],
        progression: response.data.run_summary.progression ?? [],
        survivors: response.data.run_summary.survivors ?? [],
        defeated: response.data.run_summary.defeated ?? [],
      });
    }
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  openRest(runId: string, nodeId: string): Promise<RestOpenResponse> {
    return this.apiHttp.postWithCsrf<RestOpenResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/open`, {});
  }

  updateRestState(
    runId: string,
    nodeId: string,
    payload: { unit_ids: string[]; formation: Array<{ cell: string; unit_instance_id: string | null }> },
  ): Promise<RestStateResponse> {
    return this.apiHttp.putWithCsrf<RestStateResponse>(`/api/v1/runs/${runId}/nodes/${nodeId}/rest/state`, payload);
  }

  async finalizeRest(runId: string, nodeId: string): Promise<RestFinalizeResponse> {
    const response = await this.apiHttp.postWithCsrf<RestFinalizeResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/rest/finalize`,
      {},
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  clearSummary(): void {
    this.summaryState.set(null);
  }
}


import { Injectable, signal } from '@angular/core';
import {
  AbandonRunResponse,
  BattleClaimResponse,
  CreateResponse,
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
  rewardDetail: NonNullable<RunSummaryPayload['reward_detail']> | null;
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
      rewardDetail: summary?.reward_detail ?? null,
      progressionDetail: summary?.progression_detail ?? [],
    };
  }

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
    await this.sessionService.refreshProfile({ force: true });
    if (response.ok) {
      this.summaryState.set(this.mapSummaryState('Run Abandoned', response.data.status, response.data.run_summary));
    }
    return response;
  }

  async exitRun(runId: string): Promise<ExitRunResponse> {
    const response = await this.apiHttp.postWithCsrf<ExitRunResponse>(`/api/v1/runs/${runId}/exit`, {});
    await this.sessionService.refreshProfile({ force: true });
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

  async claimBattleRewards(battleId: string): Promise<BattleClaimResponse> {
    const response = await this.apiHttp.postWithCsrf<BattleClaimResponse>(`/api/v1/battles/${battleId}/claim`, {});
    await this.sessionService.refreshProfile({ force: true });
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


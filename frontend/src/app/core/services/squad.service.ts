import { Injectable } from '@angular/core';
import {
  TeamActivateResponse,
  TeamCreateResponse,
  TeamDeleteResponse,
  TeamFormationCell,
  TeamUpdateResponse,
} from '../models/api.models';
import { ApiHttpService } from './api-http.service';
import { SessionService } from './session.service';

export type TeamUpdatePayload = {
  unit_ids: string[];
  formation: TeamFormationCell[];
  name?: string;
};

@Injectable({ providedIn: 'root' })
export class SquadService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  async createTeam(name: string, makeActive = true): Promise<TeamCreateResponse> {
    const response = await this.apiHttp.postWithCsrf<TeamCreateResponse>('/api/v1/teams', {
      name,
      make_active: makeActive,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async activateTeam(teamId: string): Promise<TeamActivateResponse> {
    const response = await this.apiHttp.postWithCsrf<TeamActivateResponse>(`/api/v1/teams/${teamId}/activate`, {});
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async updateTeam(teamId: string, payload: TeamUpdatePayload): Promise<TeamUpdateResponse> {
    const response = await this.apiHttp.putWithCsrf<TeamUpdateResponse>(`/api/v1/teams/${teamId}`, payload);
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async deleteTeam(teamId: string): Promise<TeamDeleteResponse> {
    const response = await this.apiHttp.deleteWithCsrf<TeamDeleteResponse>(`/api/v1/teams/${teamId}`, {});
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }
}

import { Injectable } from '@angular/core';
import {
  TeamActivateResponse,
  TeamCreateResponse,
  TeamDeleteResponse,
  TeamFormationCell,
  TeamUpdateResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

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
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<TeamCreateResponse>(
      '/api/v1/teams',
      {
        name,
        make_active: makeActive,
      },
    ));
  }

  async activateTeam(teamId: string): Promise<TeamActivateResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<TeamActivateResponse>(
      `/api/v1/teams/${teamId}/activate`,
      {},
    ));
  }

  async updateTeam(teamId: string, payload: TeamUpdatePayload): Promise<TeamUpdateResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.putWithCsrf<TeamUpdateResponse>(
      `/api/v1/teams/${teamId}`,
      payload,
    ));
  }

  async deleteTeam(teamId: string): Promise<TeamDeleteResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.deleteWithCsrf<TeamDeleteResponse>(
      `/api/v1/teams/${teamId}`,
      {},
    ));
  }
}


import { Injectable } from '@angular/core';
import {
  WrongMachinePreviewResponse,
  WrongMachineReconstructResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

@Injectable({ providedIn: 'root' })
export class WrongMachineService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getReconstructions(): Promise<WrongMachinePreviewResponse> {
    return this.apiHttp.get<WrongMachinePreviewResponse>('/api/v1/wrong-machine/reconstructions');
  }

  reconstruct(lineageSlug: string): Promise<WrongMachineReconstructResponse> {
    return this.sessionService.runProfileMutation(() =>
      this.apiHttp.postWithCsrf<WrongMachineReconstructResponse>(
        '/api/v1/wrong-machine/reconstruct',
        { lineage_slug: lineageSlug },
      ),
    );
  }
}

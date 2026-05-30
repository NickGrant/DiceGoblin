import { Injectable } from '@angular/core';
import { RestStorePurchaseResponse } from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

@Injectable({ providedIn: 'root' })
export class RestService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  async purchaseStoreItem(runId: string, nodeId: string, itemType: 'basic_unit' | 'basic_dice'): Promise<RestStorePurchaseResponse> {
    const response = await this.apiHttp.postWithCsrf<RestStorePurchaseResponse>(
      `/api/v1/runs/${runId}/nodes/${nodeId}/rest/store/purchase`,
      { item_type: itemType },
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }
}


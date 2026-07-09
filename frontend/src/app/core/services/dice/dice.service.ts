import { Injectable } from '@angular/core';
import { DiceSellResponse } from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

@Injectable({ providedIn: 'root' })
export class DiceService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  async sellDice(diceId: string): Promise<DiceSellResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DiceSellResponse>(
      `/api/v1/dice/${diceId}/sell`,
      {},
    ));
  }
}


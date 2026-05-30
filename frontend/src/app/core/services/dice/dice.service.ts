import { Injectable } from '@angular/core';
import {
  DiceSellResponse,
  UnitEquipmentMutationResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

type MutationContext = {
  runId?: string;
  nodeId?: string;
};

@Injectable({ providedIn: 'root' })
export class DiceService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  async sellDice(diceId: string): Promise<DiceSellResponse> {
    const response = await this.apiHttp.postWithCsrf<DiceSellResponse>(`/api/v1/dice/${diceId}/sell`, {});
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async equipDice(unitId: string, diceId: string, context?: MutationContext): Promise<UnitEquipmentMutationResponse> {
    const response = await this.apiHttp.postWithCsrf<UnitEquipmentMutationResponse>(
      `/api/v1/units/${unitId}/dice/equip`,
      this.withContext({ dice_instance_id: Number(diceId) }, context),
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async unequipDice(unitId: string, diceId: string, context?: MutationContext): Promise<UnitEquipmentMutationResponse> {
    const response = await this.apiHttp.postWithCsrf<UnitEquipmentMutationResponse>(
      `/api/v1/units/${unitId}/dice/unequip`,
      this.withContext({ dice_instance_id: Number(diceId) }, context),
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  private withContext<T extends Record<string, unknown>>(
    payload: T,
    context?: MutationContext,
  ): T & { run_id?: number; node_id?: number } {
    return {
      ...payload,
      ...(context?.runId ? { run_id: Number(context.runId) } : {}),
      ...(context?.nodeId ? { node_id: Number(context.nodeId) } : {}),
    };
  }
}


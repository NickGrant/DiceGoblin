import { Injectable } from '@angular/core';
import {
  AbilitySlotDiceMutationResponse,
  PromotionOptionsResponse,
  PromoteUnitResponse,
  RenameUnitResponse,
  ReplaceEquippedAbilitiesResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

type RestContext = {
  runId?: string;
  nodeId?: string;
};

@Injectable({ providedIn: 'root' })
export class UnitService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getPromotionOptions(unitId: string): Promise<PromotionOptionsResponse> {
    return this.apiHttp.get<PromotionOptionsResponse>(`/api/v1/units/${unitId}/promotion-options`);
  }

  async renameUnit(unitId: string, displayName: string): Promise<RenameUnitResponse> {
    const response = await this.apiHttp.patchWithCsrf<RenameUnitResponse>(`/api/v1/units/${unitId}/name`, {
      display_name: displayName,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async promoteUnit(
    primaryUnitId: string,
    secondaryUnitIds: [string, string],
    destinationUnitTypeId?: string,
    context?: RestContext,
  ): Promise<PromoteUnitResponse> {
    const response = await this.apiHttp.postWithCsrf<PromoteUnitResponse>(`/api/v1/units/${primaryUnitId}/promote`, {
      primary_unit_instance_id: Number(primaryUnitId),
      secondary_unit_instance_ids: secondaryUnitIds.map((id) => Number(id)),
      ...(destinationUnitTypeId ? { destination_unit_type_id: Number(destinationUnitTypeId) } : {}),
      ...(context?.runId ? { run_id: Number(context.runId) } : {}),
      ...(context?.nodeId ? { node_id: Number(context.nodeId) } : {}),
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async replaceEquippedAbilities(
    unitId: string,
    abilityIds: string[],
    context?: RestContext,
  ): Promise<ReplaceEquippedAbilitiesResponse> {
    const response = await this.apiHttp.putWithCsrf<ReplaceEquippedAbilitiesResponse>(`/api/v1/units/${unitId}/loadout`, {
      ability_ids: abilityIds,
      ...(context?.runId ? { run_id: Number(context.runId) } : {}),
      ...(context?.nodeId ? { node_id: Number(context.nodeId) } : {}),
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async assignAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    diceId: string,
    context?: RestContext,
  ): Promise<AbilitySlotDiceMutationResponse> {
    const response = await this.apiHttp.putWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      {
        dice_instance_id: Number(diceId),
        ...(context?.runId ? { run_id: Number(context.runId) } : {}),
        ...(context?.nodeId ? { node_id: Number(context.nodeId) } : {}),
      },
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async clearAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    context?: RestContext,
  ): Promise<AbilitySlotDiceMutationResponse> {
    const response = await this.apiHttp.deleteWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      {
        ...(context?.runId ? { run_id: Number(context.runId) } : {}),
        ...(context?.nodeId ? { node_id: Number(context.nodeId) } : {}),
      },
    );
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }
}


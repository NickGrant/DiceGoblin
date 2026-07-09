import { Injectable } from '@angular/core';
import {
  AbilitySlotDiceMutationResponse,
  PromotionOptionsResponse,
  PromoteUnitResponse,
  RenameUnitResponse,
  ReplaceEquippedAbilitiesResponse,
  SelectCapstoneResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

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
    return this.sessionService.runProfileMutation(() => this.apiHttp.patchWithCsrf<RenameUnitResponse>(
      `/api/v1/units/${unitId}/name`,
      {
        display_name: displayName,
      },
    ));
  }

  async promoteUnit(
    primaryUnitId: string,
    secondaryUnitIds: [string, string],
    destinationUnitTypeId?: string,
  ): Promise<PromoteUnitResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<PromoteUnitResponse>(
      `/api/v1/units/${primaryUnitId}/promote`,
      {
        primary_unit_instance_id: Number(primaryUnitId),
        secondary_unit_instance_ids: secondaryUnitIds.map((id) => Number(id)),
        ...(destinationUnitTypeId ? { destination_unit_type_id: Number(destinationUnitTypeId) } : {}),
      },
    ));
  }

  async selectCapstone(unitId: string, abilityId: string): Promise<SelectCapstoneResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.putWithCsrf<SelectCapstoneResponse>(
      `/api/v1/units/${unitId}/capstone`,
      {
        ability_id: abilityId,
      },
    ));
  }

  async replaceEquippedAbilities(
    unitId: string,
    abilityIds: string[],
  ): Promise<ReplaceEquippedAbilitiesResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.putWithCsrf<ReplaceEquippedAbilitiesResponse>(
      `/api/v1/units/${unitId}/loadout`,
      {
        ability_ids: abilityIds,
      },
    ));
  }

  async assignAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
    diceId: string,
  ): Promise<AbilitySlotDiceMutationResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.putWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      {
        dice_instance_id: Number(diceId),
      },
    ));
  }

  async clearAbilitySlotDie(
    unitId: string,
    abilityId: string,
    slotIndex: number,
  ): Promise<AbilitySlotDiceMutationResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.deleteWithCsrf<AbilitySlotDiceMutationResponse>(
      `/api/v1/units/${unitId}/abilities/${abilityId}/slots/${slotIndex}/dice`,
      {},
    ));
  }
}


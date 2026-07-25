import { Injectable } from '@angular/core';
import {
  DebugCatalogResponse,
  DebugCurrencyGrantResponse,
  DebugGrantDieResponse,
  DebugGrantItemResponse,
  DebugGrantRegionItemResponse,
  DebugGrantUnitResponse,
  DebugResetAccountResponse,
  DebugSetUnitLevelResponse,
  DebugSeedTablesResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

@Injectable({ providedIn: 'root' })
export class DebugService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getCatalog(): Promise<DebugCatalogResponse> {
    return this.apiHttp.get<DebugCatalogResponse>('/api/v1/debug/catalog');
  }

  getSeedTables(tableName?: string): Promise<DebugSeedTablesResponse> {
    const query = tableName ? `?table=${encodeURIComponent(tableName)}` : '';
    return this.apiHttp.get<DebugSeedTablesResponse>(`/api/v1/debug/seed-tables${query}`);
  }

  async grantCurrency(soft: number, hard = 0): Promise<DebugCurrencyGrantResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugCurrencyGrantResponse>(
      '/api/v1/debug/grant/currency',
      { soft, hard },
    ));
  }

  async grantUnit(unitTypeSlug: string, count = 1): Promise<DebugGrantUnitResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugGrantUnitResponse>(
      '/api/v1/debug/grant/unit',
      {
        unit_type_slug: unitTypeSlug,
        count,
      },
    ));
  }

  async grantDie(sides: number, rarity: string, count = 1): Promise<DebugGrantDieResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugGrantDieResponse>(
      '/api/v1/debug/grant/dice',
      {
        sides,
        rarity,
        count,
      },
    ));
  }

  async grantRegionItem(regionItemSlug: string, quantity = 1): Promise<DebugGrantRegionItemResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugGrantRegionItemResponse>(
      '/api/v1/debug/grant/region-item',
      {
        region_item_slug: regionItemSlug,
        quantity,
      },
    ));
  }

  async grantItem(itemSlug: string, quantity = 1): Promise<DebugGrantItemResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugGrantItemResponse>(
      '/api/v1/debug/grant/item',
      {
        item_slug: itemSlug,
        quantity,
      },
    ));
  }

  async setUnitLevel(unitInstanceId: string, level: number): Promise<DebugSetUnitLevelResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugSetUnitLevelResponse>(
      '/api/v1/debug/units/set-level',
      {
        unit_instance_id: unitInstanceId,
        level,
      },
    ));
  }

  async resetAccount(): Promise<DebugResetAccountResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<DebugResetAccountResponse>(
      '/api/v1/debug/reset-account',
      {},
    ));
  }
}


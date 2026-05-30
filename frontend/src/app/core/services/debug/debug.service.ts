import { Injectable } from '@angular/core';
import {
  DebugCatalogResponse,
  DebugCurrencyGrantResponse,
  DebugGrantDieResponse,
  DebugGrantRegionItemResponse,
  DebugGrantUnitResponse,
  DebugResetAccountResponse,
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

  async grantCurrency(soft: number, hard = 0): Promise<DebugCurrencyGrantResponse> {
    const response = await this.apiHttp.postWithCsrf<DebugCurrencyGrantResponse>('/api/v1/debug/grant/currency', { soft, hard });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async grantUnit(unitTypeSlug: string, count = 1): Promise<DebugGrantUnitResponse> {
    const response = await this.apiHttp.postWithCsrf<DebugGrantUnitResponse>('/api/v1/debug/grant/unit', {
      unit_type_slug: unitTypeSlug,
      count,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async grantDie(sides: number, rarity: string, count = 1): Promise<DebugGrantDieResponse> {
    const response = await this.apiHttp.postWithCsrf<DebugGrantDieResponse>('/api/v1/debug/grant/dice', {
      sides,
      rarity,
      count,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async grantRegionItem(regionItemSlug: string, quantity = 1): Promise<DebugGrantRegionItemResponse> {
    const response = await this.apiHttp.postWithCsrf<DebugGrantRegionItemResponse>('/api/v1/debug/grant/region-item', {
      region_item_slug: regionItemSlug,
      quantity,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }

  async resetAccount(): Promise<DebugResetAccountResponse> {
    const response = await this.apiHttp.postWithCsrf<DebugResetAccountResponse>('/api/v1/debug/reset-account', {});
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }
}


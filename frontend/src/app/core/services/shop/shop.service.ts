import { Injectable } from '@angular/core';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';
import { ShopCatalogResponse, ShopPurchaseResponse } from '../../models/api.models';

@Injectable({ providedIn: 'root' })
export class ShopService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getCatalog(): Promise<ShopCatalogResponse> {
    return this.apiHttp.get<ShopCatalogResponse>('/api/v1/shop');
  }

  async purchase(itemType: 'basic_unit' | 'basic_dice' | 'daily_deal', productId = ''): Promise<ShopPurchaseResponse> {
    const response = await this.apiHttp.postWithCsrf<ShopPurchaseResponse>('/api/v1/shop/purchase', {
      item_type: itemType,
      product_id: productId,
    });
    await this.sessionService.refreshProfile({ force: true });
    return response;
  }
}


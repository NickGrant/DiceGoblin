import { Injectable } from '@angular/core';
import {
  AcademyCatalogResponse,
  AcademyUnlockUnitTypeResponse,
} from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';
import { SessionService } from '../session/session.service';

@Injectable({ providedIn: 'root' })
export class AcademyService {
  constructor(
    private readonly apiHttp: ApiHttpService,
    private readonly sessionService: SessionService,
  ) {}

  getCatalog(): Promise<AcademyCatalogResponse> {
    return this.apiHttp.get<AcademyCatalogResponse>('/api/v1/academy');
  }

  async unlockUnitType(unitTypeSlug: string): Promise<AcademyUnlockUnitTypeResponse> {
    return this.sessionService.runProfileMutation(() => this.apiHttp.postWithCsrf<AcademyUnlockUnitTypeResponse>(
      '/api/v1/academy/unlock-unit-type',
      {
        unit_type_slug: unitTypeSlug,
      },
    ));
  }
}

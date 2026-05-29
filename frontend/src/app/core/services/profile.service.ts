import { Injectable } from '@angular/core';
import { ApiHttpService } from './api-http.service';
import { ProfileResponse } from '../models/api.models';

@Injectable({ providedIn: 'root' })
export class ProfileService {
  constructor(private readonly apiHttp: ApiHttpService) {}

  getProfile(): Promise<ProfileResponse> {
    return this.apiHttp.get<ProfileResponse>('/api/v1/profile');
  }
}

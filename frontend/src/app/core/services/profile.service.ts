import { Injectable } from '@angular/core';
import { ApiHttpService } from './api-http.service';
import { ProfileResponse } from '../models/api.models';

const PROFILE_TTL_MS = 30_000;

@Injectable({ providedIn: 'root' })
export class ProfileService {
  private profileCache:
    | {
        value: ProfileResponse;
        fetchedAt: number;
      }
    | null = null;
  private inflightProfilePromise: Promise<ProfileResponse> | null = null;

  constructor(private readonly apiHttp: ApiHttpService) {}

  getProfileRaw(): Promise<ProfileResponse> {
    return this.apiHttp.get<ProfileResponse>('/api/v1/profile');
  }

  async getProfile(options?: { force?: boolean; allowStaleOnError?: boolean }): Promise<ProfileResponse> {
    const now = Date.now();

    if (!options?.force && this.profileCache && now - this.profileCache.fetchedAt < PROFILE_TTL_MS) {
      return this.profileCache.value;
    }

    if (this.inflightProfilePromise) {
      return this.inflightProfilePromise;
    }

    this.inflightProfilePromise = (async () => {
      try {
        const value = await this.getProfileRaw();
        this.profileCache = { value, fetchedAt: Date.now() };
        return value;
      } catch (error) {
        if (options?.allowStaleOnError && this.profileCache) {
          return this.profileCache.value;
        }
        throw error;
      } finally {
        this.inflightProfilePromise = null;
      }
    })();

    return this.inflightProfilePromise;
  }

  invalidateProfileCache(): void {
    this.profileCache = null;
  }

  refreshProfileAfterMutation(): void {
    this.invalidateProfileCache();
    void this.getProfile({ force: true }).catch(() => {});
  }
}

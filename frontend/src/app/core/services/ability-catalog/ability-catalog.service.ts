import { Injectable, computed, signal } from '@angular/core';
import { AbilityCatalogEntry, AbilityCatalogResponse } from '../../models/api.models';
import { ApiHttpService } from '../api-http/api-http.service';

@Injectable({ providedIn: 'root' })
export class AbilityCatalogService {
  private readonly abilitiesState = signal<AbilityCatalogEntry[]>([]);
  private readonly loadingState = signal(false);
  private readonly errorState = signal<string | null>(null);
  private loaded = false;

  readonly abilities = this.abilitiesState.asReadonly();
  readonly isLoading = this.loadingState.asReadonly();
  readonly error = this.errorState.asReadonly();
  readonly abilityMap = computed(
    () => new Map(this.abilitiesState().map((ability) => [ability.ability_id, ability])),
  );

  constructor(private readonly apiHttp: ApiHttpService) {}

  async load(options?: { force?: boolean }): Promise<void> {
    if (this.loadingState()) {
      return;
    }

    if (this.loaded && !options?.force) {
      return;
    }

    this.loadingState.set(true);
    this.errorState.set(null);
    try {
      const response = await this.apiHttp.get<AbilityCatalogResponse>('/api/v1/abilities');
      if (!response.ok) {
        this.errorState.set(response.error.message);
        return;
      }

      this.abilitiesState.set(response.data.abilities ?? []);
      this.loaded = true;
    } catch (error) {
      this.errorState.set(error instanceof Error ? error.message : 'Unable to load abilities.');
    } finally {
      this.loadingState.set(false);
    }
  }
}

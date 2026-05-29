export interface SessionResponse {
  ok: boolean;
  data?: {
    user?: {
      id?: string;
      display_name?: string;
      avatar_url?: string | null;
    };
    csrf_token?: string;
  };
  error?: string;
}

export interface ProfileResponse {
  ok: boolean;
  data?: {
    currency?: {
      soft?: number;
      hard?: number;
    };
    energy?: {
      current?: number;
      max?: number;
    };
    active_run?: {
      id?: string | number;
    } | null;
    squads?: Array<{
      id: string | number;
      name: string;
      is_active?: boolean;
      unit_ids?: Array<string | number>;
    }>;
    units?: Array<{
      id: string | number;
      display_name?: string;
      unit_type_name?: string;
      level?: number;
    }>;
  };
  error?: string;
}

export interface SessionViewModel {
  isAuthenticated: boolean;
  displayName: string;
  userId: string | null;
  csrfToken: string | null;
}

export interface ProfileViewModel {
  energyCurrent: number;
  energyMax: number;
  softCurrency: number;
  activeRunId: string | null;
  squadCount: number;
  unitCount: number;
  activeSquadName: string | null;
}

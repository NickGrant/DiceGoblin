export type ApiError = {
  code: string;
  message: string;
  details?: Record<string, unknown>;
};

export type ApiOk<T> = { ok: true; data: T };
export type ApiErr = { ok: false; error: ApiError };
export type ApiResponse<T> = ApiOk<T> | ApiErr;

export type SessionData = {
  authenticated?: boolean;
  csrf_token: string;
  user?: {
    id: string;
    display_name?: string | null;
    avatar_url?: string | null;
  };
};

export type SessionResponse = ApiResponse<SessionData>;

export type PasswordResetRequestData = {
  message: string;
  reset_token?: string;
  expires_at?: string;
};

export type PasswordResetRequestResponse = ApiResponse<PasswordResetRequestData>;

export interface SessionViewModel {
  isAuthenticated: boolean;
  displayName: string;
  userId: string | null;
  csrfToken: string | null;
}

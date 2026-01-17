// src/state/RegistrySession.ts
import Phaser from "phaser";

export type SessionState = {
  isAuthenticated: boolean;
  user?: {
    id: string;
    displayName: string;
    avatarUrl?: string;
  };
  csrfToken?: string;
};

const SESSION_KEY = "session" as const;

export class RegistrySession {
  static set(registry: Phaser.Data.DataManager, session: SessionState): void {
    registry.set(SESSION_KEY, session);
  }

  static get(registry: Phaser.Data.DataManager): SessionState | null {
    const value = registry.get(SESSION_KEY);
    return (value ?? null) as SessionState | null;
  }

  static clear(registry: Phaser.Data.DataManager): void {
    registry.remove(SESSION_KEY);
  }

  /** Convenience checks */
  static isAuthed(registry: Phaser.Data.DataManager): boolean {
    return Boolean(this.get(registry)?.isAuthenticated);
  }

  static displayName(registry: Phaser.Data.DataManager): string {
    return this.get(registry)?.user?.displayName ?? 'Goblin';
  }
}

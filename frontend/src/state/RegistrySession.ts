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
  /** Write once from BootScene (or your auth gate). */
  static set(registry: Phaser.Data.DataManager, session: SessionState): void {
    registry.set(SESSION_KEY, session);
  }

  /** Read in any scene. Returns null if not initialized. */
  static get(registry: Phaser.Data.DataManager): SessionState | null {
    const value = registry.get(SESSION_KEY);
    return (value ?? null) as SessionState | null;
  }

  /** Read in any scene. Throws if missing (useful for dev / strict scenes). */
  static require(registry: Phaser.Data.DataManager): SessionState {
    const session = this.get(registry);
    if (!session) {
      throw new Error("Session not found in registry. BootScene must run first.");
    }
    return session;
  }

  static clear(registry: Phaser.Data.DataManager): void {
    registry.remove(SESSION_KEY);
  }

  /** Convenience checks */
  static isAuthed(registry: Phaser.Data.DataManager): boolean {
    return Boolean(this.get(registry)?.isAuthenticated);
  }

  static displayName(registry: Phaser.Data.DataManager): string | null {
    return this.get(registry)?.user?.displayName ?? null;
  }
}

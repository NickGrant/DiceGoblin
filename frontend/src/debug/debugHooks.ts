import type Phaser from "phaser";
import type { DebugSceneConfig } from "./debugScene";

declare global {
  interface Window {
    __DG_DEBUG__?: {
      enabled: boolean;
      requestedScene: string | null;
      currentScene: string | null;
      readyScene: string | null;
      ready: boolean;
      sceneData: Record<string, unknown>;
      authMode: string;
      settleMs: number;
      lastReadyAt: string | null;
      lastError: string | null;
      details?: Record<string, unknown>;
    };
  }
}

function getDebugState() {
  if (typeof window === "undefined") {
    return null;
  }

  if (!window.__DG_DEBUG__) {
    window.__DG_DEBUG__ = {
      enabled: false,
      requestedScene: null,
      currentScene: null,
      readyScene: null,
      ready: false,
      sceneData: {},
      authMode: "authenticated",
      settleMs: 0,
      lastReadyAt: null,
      lastError: null,
    };
  }

  return window.__DG_DEBUG__;
}

export function initializeDebugHooks(config: DebugSceneConfig): void {
  const state = getDebugState();
  if (!state) {
    return;
  }

  state.enabled = config.enabled;
  state.requestedScene = config.targetSceneKey;
  state.currentScene = null;
  state.readyScene = null;
  state.ready = false;
  state.sceneData = config.sceneData;
  state.authMode = config.authMode;
  state.settleMs = config.settleMs;
  state.lastReadyAt = null;
  state.lastError = config.errors[0] ?? null;
  state.details = undefined;
}

export function markDebugSceneReady(
  scene: Phaser.Scene,
  details?: Record<string, unknown>
): void {
  const state = getDebugState();
  if (!state) {
    return;
  }

  state.currentScene = scene.scene.key;
  state.readyScene = scene.scene.key;
  state.ready = true;
  state.lastReadyAt = new Date().toISOString();
  state.lastError = null;
  state.details = details;
}

export function reportDebugError(message: string): void {
  const state = getDebugState();
  if (!state) {
    return;
  }

  state.lastError = message;
}

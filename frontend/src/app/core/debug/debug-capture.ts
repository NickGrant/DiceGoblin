import { SessionViewModel } from '../models/api.models';

export type DebugCaptureAuthMode = 'authenticated' | 'guest' | 'live';

export type DebugCaptureRequest = {
  scene: string;
  auth: DebugCaptureAuthMode;
  displayName: string;
  userId: string;
  initialTab: string;
  sceneData: Record<string, unknown>;
};

type DebugState = {
  requestedScene: string;
  readyScene: string | null;
  ready: boolean;
  route: string | null;
  initialTab: string;
  sceneData: Record<string, unknown>;
  safeInsets: string | null;
};

const DEFAULT_DISPLAY_NAME = 'Debug Goblin';
const DEFAULT_USER_ID = 'debug-user';

const DEBUG_SCENE_ROUTE_ALIASES: Record<string, string> = {
  camp: '/game',
  'camp-portrait': '/game',
  warband: '/game',
  shop: '/game',
  inventory: '/game',
  academy: '/game',
  codex: '/game',
  'wrong-machine': '/game',
  'unit-promotion': '/game',
  run: '/game',
  'run-combat-available': '/game',
  'run-branching': '/game',
  'run-modifier': '/game',
  'run-contextual-initial': '/game',
  'run-contextual-step': '/game',
  'run-loot-available': '/game',
  'run-loot-result': '/game',
  'run-rest-available': '/game',
  'run-rest-result': '/game',
  'run-hazard-available': '/game',
  'run-hazard-result': '/game',
  'run-supplies': '/game',
  'run-abandon': '/game',
  'run-portrait': '/game',
  'battle-early': '/game',
  'battle-mid': '/game',
  'battle-complete': '/game',
  'battle-compact': '/game',
  'battle-wide': '/game',
  'battle-portrait': '/game',
  'battle-defeat': '/game',
  'battle-result-victory': '/game',
  'battle-result-defeat': '/game',
  'battle-result-stalemate': '/game',
  'battle-result-compact': '/game',
  'battle-result-wide': '/game',
  'battle-result-error': '/game',
  'battle-result-portrait': '/game',
  'unit-configuration': '/game',
  login: '/login',
  guide: '/guide',
};

declare global {
  interface Window {
    __DG_DEBUG__?: DebugState;
  }
}

export function readDebugCaptureRequest(): DebugCaptureRequest | null {
  if (typeof window === 'undefined') {
    return null;
  }

  const searchParams = new URLSearchParams(window.location.search);
  const scene = searchParams.get('debugScene')?.trim();
  if (!scene) {
    return null;
  }

  const authParam = searchParams.get('debugAuth')?.trim();
  const auth: DebugCaptureAuthMode =
    authParam === 'guest' || authParam === 'live' ? authParam : 'authenticated';

  return {
    scene,
    auth,
    displayName: searchParams.get('debugDisplayName')?.trim() || DEFAULT_DISPLAY_NAME,
    userId: searchParams.get('debugUserId')?.trim() || DEFAULT_USER_ID,
    initialTab: searchParams.get('debugInitialTab')?.trim() || '',
    sceneData: parseDebugSceneData(searchParams.get('debugSceneData')),
  };
}

export function resolveDebugCaptureRoute(request: DebugCaptureRequest): string | null {
  const explicitRoute = stringSceneDataValue(request.sceneData['route']);
  if (explicitRoute) {
    return explicitRoute;
  }

  const alias = DEBUG_SCENE_ROUTE_ALIASES[request.scene.trim().toLowerCase()];
  return alias ?? null;
}

export function publishDebugCaptureState(request: DebugCaptureRequest, route: string | null, ready: boolean): void {
  if (typeof window === 'undefined') {
    return;
  }

  window.__DG_DEBUG__ = {
    requestedScene: request.scene,
    readyScene: ready ? request.scene : null,
    ready,
    route,
    initialTab: request.initialTab,
    sceneData: request.sceneData,
    safeInsets: new URLSearchParams(window.location.search).get('debugSafeInsets') ?? window.__DG_DEBUG__?.safeInsets ?? null,
  };
}

export function isDebugCaptureAuthenticated(request: DebugCaptureRequest | null): boolean {
  return request?.auth === 'authenticated';
}

export function isDebugCaptureGuest(request: DebugCaptureRequest | null): boolean {
  return request?.auth === 'guest';
}

export function createDebugCaptureSession(request: DebugCaptureRequest): SessionViewModel {
  return {
    isAuthenticated: true,
    displayName: request.displayName,
    userId: request.userId,
    csrfToken: 'debug-csrf-token',
  };
}

function parseDebugSceneData(raw: string | null): Record<string, unknown> {
  if (!raw) {
    return {};
  }

  try {
    const parsed = JSON.parse(raw) as unknown;
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, unknown>) : {};
  } catch {
    return {};
  }
}

function stringSceneDataValue(value: unknown): string | null {
  return typeof value === 'string' && value.trim().length > 0 ? value.trim() : null;
}

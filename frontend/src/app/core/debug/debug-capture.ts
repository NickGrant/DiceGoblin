import { ProfileData, SessionViewModel, TeamRecord, UnitRecord } from '../models/api.models';

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
};

const DEFAULT_DISPLAY_NAME = 'Debug Goblin';
const DEFAULT_USER_ID = 'debug-user';

const DEBUG_SCENE_ROUTE_ALIASES: Record<string, string> = {
  login: '/login',
  guide: '/guide',
  codex: '/codex',
  home: '/home',
  'field-guide': '/codex',
  regions: '/regions',
  warband: '/warband',
  dice: '/dice',
  shop: '/shop',
  academy: '/academy',
  'run-map': '/run/map',
  'run-node': '/run/node/n1',
  'run-summary': '/run/summary',
  debug: '/debug',
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

export function createDebugCaptureProfile(_request: DebugCaptureRequest): ProfileData {
  const units: UnitRecord[] = [
    {
      id: 'u1',
      name: 'Ashback',
      level: 2,
      tier: 1,
      unit_type_slug: 'goblin_bruiser',
      unit_type_name: 'Bruiser',
      current_hp: 20,
      max_hp: 20,
      locked: false,
    },
    {
      id: 'u2',
      name: 'Bogwort',
      level: 2,
      tier: 1,
      unit_type_slug: 'goblin_bannerbearer',
      unit_type_name: 'Bannerbearer',
      current_hp: 18,
      max_hp: 18,
      locked: false,
    },
    {
      id: 'u3',
      name: 'Stitch',
      level: 1,
      tier: 1,
      unit_type_slug: 'goblin_deadeye',
      unit_type_name: 'Deadeye',
      current_hp: 16,
      max_hp: 16,
      locked: false,
    },
  ];

  const activeSquad: TeamRecord = {
    id: 'team-1',
    name: 'Bogbreakers',
    is_active: true,
    unit_ids: units.map((unit) => unit.id),
    formation: [
      { cell: 'A1', unit_instance_id: 'u1' },
      { cell: 'A2', unit_instance_id: 'u2' },
      { cell: 'A3', unit_instance_id: 'u3' },
    ],
  };

  return {
    server_time_iso: '2026-07-10T12:00:00Z',
    squads: [activeSquad],
    units,
    dice: [
      { id: 'd1', rarity: 'rare', sides: 8, display_name: 'Burnished d8', slot_capacity: 1, affix_slots: 1, sell_value: 15, value: 30 },
      { id: 'd2', rarity: 'uncommon', sides: 6, display_name: 'Bone d6', slot_capacity: 1, affix_slots: 1, sell_value: 10, value: 22 },
    ],
    currency: {
      soft: 209,
    },
    energy: {
      current: 47,
      max: 50,
    },
    squad_unit_cap: 4,
    feature_unlocks: ['shop', 'academy'],
    unit_type_unlocks: ['goblin_bruiser', 'goblin_bannerbearer', 'goblin_deadeye'],
    seen_dialogues: [],
    regions: [
      {
        id: 'region-farm',
        slug: 'the_farm',
        name: 'The Farm',
        theme: 'farm',
        recommended_level: 1,
        energy_cost: 3,
        is_enabled: true,
        is_unlocked: true,
        is_completed: true,
        unlocked_at: '2026-07-01T00:00:00Z',
      },
      {
        id: 'region-mountains',
        slug: 'the_mountain',
        name: 'The Mountain',
        theme: 'mountain',
        recommended_level: 2,
        energy_cost: 4,
        is_enabled: true,
        is_unlocked: true,
        is_completed: false,
        unlocked_at: '2026-07-02T00:00:00Z',
      },
    ],
    region_unlocks: [
      {
        region_id: 'region-farm',
        region_slug: 'the_farm',
        region_name: 'The Farm',
        region_theme: 'farm',
        unlocked_at: '2026-07-01T00:00:00Z',
      },
      {
        region_id: 'region-mountains',
        region_slug: 'the_mountain',
        region_name: 'The Mountain',
        region_theme: 'mountain',
        unlocked_at: '2026-07-02T00:00:00Z',
      },
    ],
    region_items: [],
    active_run: null,
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

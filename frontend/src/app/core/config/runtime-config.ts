declare global {
  interface Window {
    __DICE_GOBLIN_CONFIG__?: {
      apiBaseUrl?: string;
      enableDevPanel?: boolean;
      enablePhaserBattlePlayback?: boolean;
      enableAudioDirector?: boolean;
    };
  }
}

function sameOriginBaseUrl(): string {
  if (typeof window !== 'undefined' && window.location?.origin) {
    return window.location.origin;
  }

  return '';
}

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '');
}

function readOptionalBooleanFlag(value: boolean | undefined, fallback: boolean): boolean {
  return typeof value === 'boolean' ? value : fallback;
}

export function resolveApiBaseUrl(): string {
  const runtimeConfig = window.__DICE_GOBLIN_CONFIG__?.apiBaseUrl?.trim();
  if (runtimeConfig) {
    return trimTrailingSlash(runtimeConfig);
  }

  const metaValue = document
    .querySelector<HTMLMetaElement>('meta[name="dice-goblin-api-base-url"]')
    ?.content?.trim();
  if (metaValue) {
    return trimTrailingSlash(metaValue);
  }

  return trimTrailingSlash(sameOriginBaseUrl());
}

export function isDevPanelEnabled(): boolean {
  return window.__DICE_GOBLIN_CONFIG__?.enableDevPanel ?? false;
}

export function isPhaserBattlePlaybackEnabled(): boolean {
  return readOptionalBooleanFlag(window.__DICE_GOBLIN_CONFIG__?.enablePhaserBattlePlayback, false);
}

export function isAudioDirectorEnabled(): boolean {
  return readOptionalBooleanFlag(window.__DICE_GOBLIN_CONFIG__?.enableAudioDirector, true);
}

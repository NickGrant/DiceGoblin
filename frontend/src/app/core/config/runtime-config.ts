declare global {
  interface Window {
    __DICE_GOBLIN_CONFIG__?: {
      apiBaseUrl?: string;
      enableDevPanel?: boolean;
    };
  }
}

const DEFAULT_API_BASE_URL = 'http://localhost:8080';

function trimTrailingSlash(value: string): string {
  return value.replace(/\/+$/, '');
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

  return DEFAULT_API_BASE_URL;
}

export function isDevPanelEnabled(): boolean {
  return window.__DICE_GOBLIN_CONFIG__?.enableDevPanel ?? true;
}

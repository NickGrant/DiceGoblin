export type AudioCategory = 'music' | 'ambience' | 'ui' | 'battle' | 'reward' | 'system';

export type AudioPreloadPolicy = 'eager' | 'route' | 'lazy';

export interface AudioManifestEntry {
  readonly key: string;
  readonly category: AudioCategory;
  readonly sources: readonly string[];
  readonly defaultVolume: number;
  readonly loop: boolean;
  readonly preload: AudioPreloadPolicy;
  readonly cooldownMs?: number;
  readonly stream?: boolean;
  readonly description?: string;
}

export interface AudioPreferences {
  readonly muted: boolean;
  readonly masterVolume: number;
  readonly musicVolume: number;
  readonly sfxVolume: number;
  readonly uiVolume: number;
  readonly ambienceVolume: number;
}

export interface RouteAudioContext {
  readonly musicIntent: string | null;
  readonly ambienceIntent: string | null;
}

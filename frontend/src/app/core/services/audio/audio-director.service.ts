import { DOCUMENT, isPlatformBrowser } from '@angular/common';
import { Injectable, PLATFORM_ID, computed, inject, signal } from '@angular/core';
import { Howl, Howler } from 'howler';
import { AUDIO_MANIFEST_BY_KEY } from '../../audio/audio-manifest';
import { AudioManifestEntry, AudioPreferences, RouteAudioContext } from '../../audio/audio.models';
import { isAudioDirectorEnabled } from '../../config/runtime-config';

const AUDIO_PREFERENCES_STORAGE_KEY = 'dice-goblins.audio.preferences.v1';
const AUDIO_UNLOCK_TIMEOUT_MS = 250;

const DEFAULT_AUDIO_PREFERENCES: AudioPreferences = {
  muted: false,
  masterVolume: 1,
  musicVolume: 0.75,
  sfxVolume: 0.85,
  uiVolume: 0.85,
  ambienceVolume: 0.7,
};

type LoopChannel = 'music' | 'ambience';

@Injectable({ providedIn: 'root' })
export class AudioDirectorService {
  private readonly document = inject(DOCUMENT);
  private readonly platformId = inject(PLATFORM_ID);

  private readonly enabled = isAudioDirectorEnabled();
  private readonly initializedState = signal(false);
  private readonly unlockedState = signal(false);
  private readonly preferencesState = signal<AudioPreferences>(this.loadPreferences());
  private readonly currentMusicIntentState = signal<string | null>(null);
  private readonly currentAmbienceIntentState = signal<string | null>(null);
  private readonly loopKeys = new Map<LoopChannel, string | null>();
  private readonly howlCache = new Map<string, Howl>();
  private readonly lastPlaybackAt = new Map<string, number>();

  private readonly gestureUnlockHandler = () => {
    void this.unlockAudio();
  };

  private readonly uiClickHandler = (event: Event) => {
    if (!this.enabled) {
      return;
    }

    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    const interactive = target.closest('button, a, [role="button"]');
    if (!interactive || interactive.getAttribute('aria-disabled') === 'true') {
      return;
    }

    this.playUiClick();
  };

  private readonly visibilityHandler = () => {
    if (!this.enabled) {
      return;
    }

    if (this.document.visibilityState === 'hidden') {
      Howler.mute(true);
      return;
    }

    this.applyMuteState();
    this.resumeRouteLoops();
  };

  readonly isEnabled = computed(() => this.enabled);
  readonly isInitialized = this.initializedState.asReadonly();
  readonly isUnlocked = this.unlockedState.asReadonly();
  readonly preferences = this.preferencesState.asReadonly();
  readonly currentMusicIntent = this.currentMusicIntentState.asReadonly();
  readonly currentAmbienceIntent = this.currentAmbienceIntentState.asReadonly();
  readonly showUnlockPrompt = computed(() => this.enabled && !this.unlockedState());
  readonly isMuted = computed(() => this.preferencesState().muted);

  initialize(): void {
    if (!this.enabled || this.initializedState() || !isPlatformBrowser(this.platformId)) {
      return;
    }

    this.initializedState.set(true);
    this.attachGlobalHandlers();
    this.applyMuteState();
  }

  async enableSound(): Promise<void> {
    if (!this.enabled) {
      return;
    }

    this.setMuted(false);
    await this.unlockAudio();
  }

  toggleMute(): void {
    this.setMuted(!this.preferencesState().muted);
  }

  setMuted(muted: boolean): void {
    this.preferencesState.update((preferences) => ({ ...preferences, muted }));
    this.persistPreferences();
    this.applyMuteState();
  }

  setPreferences(next: Partial<AudioPreferences>): void {
    this.preferencesState.update((preferences) => ({
      ...preferences,
      ...this.normalizePreferences(next),
    }));
    this.persistPreferences();
    this.applyMuteState();
    this.refreshLoadedVolumes();
  }

  setRouteContext(context: RouteAudioContext): void {
    this.currentMusicIntentState.set(context.musicIntent);
    this.currentAmbienceIntentState.set(context.ambienceIntent);

    this.playLoopIntent('music', context.musicIntent);
    this.playLoopIntent('ambience', context.ambienceIntent);
  }

  playUiClick(): void {
    this.playIntent('ui.click');
  }

  playUiConfirm(): void {
    this.playIntent('ui.confirm');
  }

  playUiCancel(): void {
    this.playIntent('ui.cancel');
  }

  emitIntent(key: string): void {
    this.playIntent(key);
  }

  private attachGlobalHandlers(): void {
    this.document.addEventListener('pointerdown', this.gestureUnlockHandler, { passive: true });
    this.document.addEventListener('keydown', this.gestureUnlockHandler, { passive: true });
    this.document.addEventListener('click', this.uiClickHandler, { passive: true, capture: true });
    this.document.addEventListener('visibilitychange', this.visibilityHandler, { passive: true });
  }

  private async unlockAudio(): Promise<void> {
    if (this.unlockedState()) {
      return;
    }

    try {
      const contextState = Howler.ctx?.state;
      if (contextState === 'suspended') {
        await Promise.race([
          Howler.ctx.resume(),
          new Promise<void>((resolve) => window.setTimeout(resolve, AUDIO_UNLOCK_TIMEOUT_MS)),
        ]);
      }

      if (Howler.ctx && Howler.ctx.state === 'suspended') {
        return;
      }

      this.unlockedState.set(true);
      this.resumeRouteLoops();
    } catch {
      // Keep the prompt visible if the browser still blocks audio unlock.
    }
  }

  private playIntent(key: string): void {
    if (!this.enabled || !this.canPlayAudio()) {
      return;
    }

    const entry = AUDIO_MANIFEST_BY_KEY.get(key);
    if (!entry || entry.loop) {
      return;
    }

    const now = Date.now();
    const lastPlayedAt = this.lastPlaybackAt.get(key) ?? 0;
    if (entry.cooldownMs !== undefined && now - lastPlayedAt < entry.cooldownMs) {
      return;
    }

    const howl = this.getHowl(entry);
    if (!howl) {
      return;
    }

    this.lastPlaybackAt.set(key, now);
    howl.volume(this.resolveVolume(entry));
    howl.play();
  }

  private playLoopIntent(channel: LoopChannel, key: string | null): void {
    const currentKey = this.loopKeys.get(channel) ?? null;
    if (currentKey === key) {
      return;
    }

    if (currentKey) {
      this.stopLoop(currentKey);
      this.loopKeys.set(channel, null);
    }

    if (!key || !this.canPlayAudio()) {
      return;
    }

    const entry = AUDIO_MANIFEST_BY_KEY.get(key);
    if (!entry || !entry.loop) {
      return;
    }

    const howl = this.getHowl(entry);
    if (!howl) {
      return;
    }

    howl.volume(this.resolveVolume(entry));
    howl.play();
    this.loopKeys.set(channel, key);
  }

  private resumeRouteLoops(): void {
    this.playLoopIntent('music', this.currentMusicIntentState());
    this.playLoopIntent('ambience', this.currentAmbienceIntentState());
  }

  private stopLoop(key: string): void {
    this.howlCache.get(key)?.stop();
  }

  private getHowl(entry: AudioManifestEntry): Howl | null {
    if (entry.sources.length === 0) {
      return null;
    }

    const existing = this.howlCache.get(entry.key);
    if (existing) {
      return existing;
    }

    const howl = new Howl({
      src: [...entry.sources],
      loop: entry.loop,
      html5: entry.stream ?? false,
      volume: this.resolveVolume(entry),
    });
    this.howlCache.set(entry.key, howl);
    return howl;
  }

  private refreshLoadedVolumes(): void {
    for (const [key, howl] of this.howlCache.entries()) {
      const entry = AUDIO_MANIFEST_BY_KEY.get(key);
      if (!entry) {
        continue;
      }

      howl.volume(this.resolveVolume(entry));
    }
  }

  private canPlayAudio(): boolean {
    return this.unlockedState() && !this.preferencesState().muted && this.document.visibilityState !== 'hidden';
  }

  private applyMuteState(): void {
    Howler.mute(this.preferencesState().muted || this.document.visibilityState === 'hidden');
  }

  private resolveVolume(entry: AudioManifestEntry): number {
    const preferences = this.preferencesState();
    const categoryVolume = this.categoryVolume(entry.category, preferences);
    return this.clampVolume(entry.defaultVolume * preferences.masterVolume * categoryVolume);
  }

  private categoryVolume(category: AudioManifestEntry['category'], preferences: AudioPreferences): number {
    switch (category) {
      case 'music':
        return preferences.musicVolume;
      case 'ambience':
        return preferences.ambienceVolume;
      case 'ui':
        return preferences.uiVolume;
      default:
        return preferences.sfxVolume;
    }
  }

  private loadPreferences(): AudioPreferences {
    if (!isPlatformBrowser(this.platformId)) {
      return DEFAULT_AUDIO_PREFERENCES;
    }

    try {
      const raw = window.localStorage.getItem(AUDIO_PREFERENCES_STORAGE_KEY);
      if (!raw) {
        return DEFAULT_AUDIO_PREFERENCES;
      }

      const parsed = JSON.parse(raw) as Partial<AudioPreferences>;
      return {
        ...DEFAULT_AUDIO_PREFERENCES,
        ...this.normalizePreferences(parsed),
      };
    } catch {
      return DEFAULT_AUDIO_PREFERENCES;
    }
  }

  private persistPreferences(): void {
    if (!isPlatformBrowser(this.platformId)) {
      return;
    }

    try {
      window.localStorage.setItem(AUDIO_PREFERENCES_STORAGE_KEY, JSON.stringify(this.preferencesState()));
    } catch {
      // Ignore storage failures so audio controls still work in-memory.
    }
  }

  private normalizePreferences(preferences: Partial<AudioPreferences>): Partial<AudioPreferences> {
    return {
      muted: preferences.muted ?? undefined,
      masterVolume: this.normalizeVolume(preferences.masterVolume),
      musicVolume: this.normalizeVolume(preferences.musicVolume),
      sfxVolume: this.normalizeVolume(preferences.sfxVolume),
      uiVolume: this.normalizeVolume(preferences.uiVolume),
      ambienceVolume: this.normalizeVolume(preferences.ambienceVolume),
    };
  }

  private normalizeVolume(value: number | undefined): number | undefined {
    if (typeof value !== 'number' || Number.isNaN(value)) {
      return undefined;
    }

    return this.clampVolume(value);
  }

  private clampVolume(value: number): number {
    return Math.max(0, Math.min(1, value));
  }
}

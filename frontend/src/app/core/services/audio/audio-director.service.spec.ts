import { TestBed } from '@angular/core/testing';
import { AudioDirectorService } from './audio-director.service';

describe('AudioDirectorService', () => {
  beforeEach(() => {
    window.localStorage.clear();
    TestBed.configureTestingModule({});
  });

  it('starts disabled until a gesture unlock occurs', () => {
    const service = TestBed.inject(AudioDirectorService);
    service.initialize();

    expect(service.isEnabled()).toBeTrue();
    expect(service.isUnlocked()).toBeFalse();
    expect(service.showUnlockPrompt()).toBeTrue();
  });

  it('can enable sound and unmute from the shell control', async () => {
    const service = TestBed.inject(AudioDirectorService);
    service.initialize();
    service.setMuted(true);

    await service.enableSound();

    expect(service.isMuted()).toBeFalse();
  });

  it('tracks route-level music intents without requiring audio assets yet', () => {
    const service = TestBed.inject(AudioDirectorService);
    service.initialize();

    service.setRouteContext({
      musicIntent: 'music.home',
      ambienceIntent: null,
    });

    expect(service.currentMusicIntent()).toBe('music.home');
    expect(service.currentAmbienceIntent()).toBeNull();
  });
});

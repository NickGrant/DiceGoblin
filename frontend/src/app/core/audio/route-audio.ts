import { ActivatedRouteSnapshot } from '@angular/router';
import { RouteAudioContext } from './audio.models';

type RouteAudioData = Partial<RouteAudioContext> | undefined;

const DEFAULT_ROUTE_AUDIO_CONTEXT: RouteAudioContext = {
  musicIntent: null,
  ambienceIntent: null,
};

export function resolveRouteAudioContext(snapshot: ActivatedRouteSnapshot): RouteAudioContext {
  const merged: { musicIntent: string | null; ambienceIntent: string | null } = {
    ...DEFAULT_ROUTE_AUDIO_CONTEXT,
  };
  let current: ActivatedRouteSnapshot | null = snapshot;

  while (current) {
    const routeAudio = current.data['audio'] as RouteAudioData;
    if (routeAudio?.musicIntent !== undefined) {
      merged.musicIntent = routeAudio.musicIntent ?? null;
    }
    if (routeAudio?.ambienceIntent !== undefined) {
      merged.ambienceIntent = routeAudio.ambienceIntent ?? null;
    }

    current = current.firstChild ?? null;
  }

  return merged;
}

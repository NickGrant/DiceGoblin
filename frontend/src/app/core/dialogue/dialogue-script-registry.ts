import {
  DialogueChoice,
  DialogueLibraryDefinition,
  DialogueScript,
  DialogueScriptDefinition,
  DialogueSpeaker,
  DialogueStep,
  DialogueStepEnterEffect,
  DialogueTrigger,
  DialogueTriggerContext,
} from './dialogue.models';
import { resolveUnitImageUrl } from '../../shared/ui/unit-art/unit-art';

export function findDialogueScript(
  library: DialogueLibraryDefinition,
  context: DialogueTriggerContext,
): DialogueScript | null {
  const script = library.scripts.find((candidate) => dialogueTriggerMatches(candidate.trigger, context));
  return script ? materializeDialogueScript(script, context) : null;
}

export function dialogueTriggerMatches(trigger: DialogueTrigger, context: DialogueTriggerContext): boolean {
  if (trigger.scene !== context.scene) {
    return false;
  }

  if (trigger.node_type && trigger.node_type !== (context.nodeType ?? undefined)) {
    return false;
  }

  if (trigger.region_slug && trigger.region_slug !== (context.regionSlug ?? undefined)) {
    return false;
  }

  if (trigger.region_id && trigger.region_id !== (context.regionId ?? undefined)) {
    return false;
  }

  if (trigger.encounter_template_id && trigger.encounter_template_id !== (context.encounterTemplateId ?? undefined)) {
    return false;
  }

  if ((trigger.tags?.length ?? 0) > 0) {
    const contextTags = new Set(context.tags ?? []);
    if (!trigger.tags!.every((tag) => contextTags.has(tag))) {
      return false;
    }
  }

  return true;
}

export function materializeDialogueScript(
  definition: DialogueScriptDefinition,
  context: DialogueTriggerContext,
): DialogueScript {
  const speakers = definition.speakers.map((speaker): DialogueSpeaker => ({
    id: speaker.id,
    side: resolveSpeakerSide(speaker),
    name: resolveSpeakerName(speaker.name, speaker.role, context),
    portraitUrl: resolveSpeakerPortrait(speaker.portrait_url, speaker.portrait_unit_slug, speaker.role, context),
    party: speaker.party ?? null,
    role: speaker.role ?? null,
  }));

  const steps = definition.steps.map((step): DialogueStep => ({
    id: step.id,
    speakerId: step.speaker_id,
    text: step.text,
    nextStepId: step.next_step_id ?? null,
    choices: (step.choices ?? []).map(
      (choice): DialogueChoice => ({
        id: choice.id,
        label: choice.label,
        nextStepId: choice.next_step_id,
      }),
    ),
    enterEffect: materializeEnterEffect(step.enter_effect),
  }));

  return {
    id: definition.id,
    backgroundUrl: definition.presentation?.background_url ?? null,
    speakers,
    startStepId: definition.start_step_id ?? definition.steps[0]?.id ?? '',
    steps,
  };
}

function resolveSpeakerSide(speaker: DialogueScriptDefinition['speakers'][number]): 'left' | 'right' {
  if (speaker.party === 'player' || speaker.role === 'player') {
    return 'left';
  }

  if (speaker.party === 'enemy') {
    return 'right';
  }

  return speaker.side;
}

function resolveSpeakerName(
  explicitName: string | undefined,
  role: string | undefined,
  context: DialogueTriggerContext,
): string {
  if (explicitName && explicitName.trim().length > 0) {
    return explicitName.trim();
  }

  if (role === 'player') {
    return context.playerName?.trim() || 'Player';
  }

  return 'Speaker';
}

function resolveSpeakerPortrait(
  portraitUrl: string | null | undefined,
  portraitUnitSlug: string | null | undefined,
  role: string | undefined,
  context: DialogueTriggerContext,
): string | null {
  if (portraitUrl && portraitUrl.trim().length > 0) {
    return portraitUrl;
  }

  if (role === 'player') {
    return context.playerPortraitUrl ?? null;
  }

  if (portraitUnitSlug && portraitUnitSlug.trim().length > 0) {
    return resolveUnitImageUrl(portraitUnitSlug);
  }

  return null;
}

function materializeEnterEffect(effect: DialogueScriptDefinition['steps'][number]['enter_effect']): DialogueStepEnterEffect | null {
  if (!effect || effect.kind !== 'player_reveal') {
    return null;
  }

  return {
    kind: 'player_reveal',
    initialOverlayUrl: effect.initial_overlay_url,
    finalOverlayUrl: effect.final_overlay_url,
    resultingPlayerPortraitUrl: effect.resulting_player_portrait_url,
    initialDurationMs: Math.max(200, effect.initial_duration_ms ?? 700),
    flashCount: Math.max(1, effect.flash_count ?? 2),
    flashIntervalMs: Math.max(80, effect.flash_interval_ms ?? 180),
    betweenOverlaysMs: Math.max(0, effect.between_overlays_ms ?? 220),
    finalHoldMs: Math.max(300, effect.final_hold_ms ?? 2000),
  };
}

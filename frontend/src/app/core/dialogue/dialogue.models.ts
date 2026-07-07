export type DialogueSceneType = 'run-node' | string;
export type DialogueSpeakerSide = 'left' | 'right';

export type DialogueTrigger = {
  scene: DialogueSceneType;
  node_type?: string;
  region_slug?: string;
  region_id?: string;
  encounter_template_id?: string;
  tags?: string[];
};

export type DialogueSpeakerDefinition = {
  id: string;
  side: DialogueSpeakerSide;
  name?: string;
  portrait_url?: string | null;
  portrait_unit_slug?: string | null;
  party?: 'player' | 'enemy' | 'neutral' | string;
  role?: 'player' | 'npc' | string;
};

export type DialogueChoiceDefinition = {
  id: string;
  label: string;
  next_step_id: string;
};

export type DialogueStepDefinition = {
  id: string;
  speaker_id: string;
  text: string;
  next_step_id?: string | null;
  choices?: DialogueChoiceDefinition[];
};

export type DialoguePresentationDefinition = {
  background_url?: string | null;
};

export type DialogueScriptDefinition = {
  id: string;
  trigger: DialogueTrigger;
  presentation?: DialoguePresentationDefinition;
  speakers: DialogueSpeakerDefinition[];
  start_step_id?: string;
  steps: DialogueStepDefinition[];
};

export type DialogueLibraryDefinition = {
  scripts: DialogueScriptDefinition[];
};

export type DialogueTriggerContext = {
  scene: DialogueSceneType;
  nodeType?: string | null;
  regionSlug?: string | null;
  regionId?: string | null;
  encounterTemplateId?: string | null;
  tags?: string[];
  playerName?: string | null;
  playerPortraitUrl?: string | null;
};

export type DialogueSpeaker = {
  id: string;
  side: DialogueSpeakerSide;
  name: string;
  portraitUrl: string | null;
  party: string | null;
  role: string | null;
};

export type DialogueChoice = {
  id: string;
  label: string;
  nextStepId: string;
};

export type DialogueStep = {
  id: string;
  speakerId: string;
  text: string;
  nextStepId: string | null;
  choices: DialogueChoice[];
};

export type DialogueScript = {
  id: string;
  backgroundUrl: string | null;
  speakers: DialogueSpeaker[];
  startStepId: string;
  steps: DialogueStep[];
};

export type DialogueChoiceSelection = {
  stepId: string;
  choiceId: string;
};

import { ResolveNodeData, UnitRecord } from '../models/api.models';

export type BattlePlaybackIntent = 'music.battle.normal' | 'music.battle.boss';

export type BattlePlaybackResultSegment = {
  text: string;
  tooltip: string | null;
};

export type BattlePlaybackParticipantRef = {
  participantId: string | null;
  unitId: string | null;
  enemySlug: string | null;
  name: string;
  currentHp: number;
  maxHp: number;
};

export type BattlePlaybackActionStep = {
  type: 'action';
  round: number;
  tick: number;
  side: 'player' | 'enemy';
  actor: BattlePlaybackParticipantRef;
  target: BattlePlaybackParticipantRef;
  abilityId: string;
  abilityName: string;
  diceSummary: string;
  resultSummary: string;
  resultSegments: BattlePlaybackResultSegment[];
};

export type BattlePlaybackParticipant = {
  participantId: string;
  side: 'player' | 'enemy';
  unitId: string | null;
  enemySlug: string | null;
  name: string;
  spriteKey: string;
  portraitKey: string | null;
  maxHp: number;
  startingHp: number;
};

export type BattlePlaybackSnapshot = {
  metadata: {
    runId: string | null;
    nodeId: string;
    battleId: string;
    nodeType: string;
    battleResult: string;
    status: string;
    rounds: number;
    ticks: number;
    regionTheme: string | null;
  };
  source: ResolveNodeData;
  participants: {
    player: BattlePlaybackParticipant[];
    enemy: BattlePlaybackParticipant[];
  };
  timeline: BattlePlaybackActionStep[];
  rewards: {
    nodeType: string;
    xpTotal: number;
    currencySoft: number;
    newUnitLabels: string[];
    newDiceLabels: string[];
  } | null;
  presentation: {
    backgroundKey: string | null;
    musicIntent: BattlePlaybackIntent;
    ambienceIntent: string | null;
    reducedMotionMode: 'standard';
  };
};

export type BattlePlaybackSnapshotInput = {
  runId: string | null;
  nodeId: string;
  regionTheme?: string | null;
  result: ResolveNodeData | null;
  playerUnits: UnitRecord[];
  diceInventory: ReadonlyArray<{
    id: string;
    rarity?: string;
    sides?: number;
  }>;
  abilityNames: ReadonlyMap<string, string>;
};

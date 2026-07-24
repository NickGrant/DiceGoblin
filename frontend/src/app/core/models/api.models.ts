export type ApiError = {
  code: string;
  message: string;
  details?: Record<string, unknown>;
};

export type ApiOk<T> = { ok: true; data: T };
export type ApiErr = { ok: false; error: ApiError };
export type ApiResponse<T> = ApiOk<T> | ApiErr;

export type SessionData = {
  authenticated?: boolean;
  csrf_token: string;
  user?: {
    id: string;
    display_name?: string | null;
    avatar_url?: string | null;
  };
};

export type SessionResponse = ApiResponse<SessionData>;

export type PasswordResetRequestData = {
  message: string;
  reset_token?: string;
  expires_at?: string;
};

export type PasswordResetRequestResponse = ApiResponse<PasswordResetRequestData>;

export type ProfileActiveRun = {
  run_id: string;
  region_id: string;
  region_slug?: string;
  region_name?: string;
  region_theme?: string;
  recommended_level?: number;
  energy_cost?: number;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

export type UnitEquippedDie = {
  dice_instance_id: string;
  slot_index: number;
};

export type UnitAbilityRecord = {
  ability_id: string;
  type?: 'active' | 'passive' | string;
  display_name?: string;
  order?: number;
};

export type UnitUnlockedAbilityRecord = {
  ability_id: string;
  source_unit_type_id?: string;
  source_unit_type_slug?: string;
  source_unit_type_name?: string;
};

export type UnitEquippedAbilityRecord = {
  ability_id: string;
  equip_order: number;
  speed_cost: number;
};

export type UnitAbilityDieRecord = {
  ability_id: string;
  slot_index: number;
  dice_instance_id: string;
};

export type UnitCapstoneChoiceRecord = {
  ability_id: string;
};

export type UnitSelectedCapstoneRecord = {
  source_unit_type_id: string;
  source_unit_type_slug: string;
  source_unit_type_name: string;
  ability_id: string;
};

export type UnitPromotionGrantsRecord = {
  actives: string[];
  passives: string[];
};

export type UnitRecord = {
  id: string;
  name: string;
  level: number;
  unit_type_id?: string;
  unit_type_slug?: string;
  unit_type_name?: string;
  tier?: number;
  xp?: number;
  max_level?: number;
  promotion_level?: number | null;
  promotion_eligible?: boolean;
  is_mastered?: boolean;
  max_tier?: number;
  total_attack?: number;
  total_defense?: number;
  max_hp?: number;
  current_hp?: number;
  xp_to_next_level?: number;
  locked?: boolean;
  formation_width?: number;
  formation_height?: number;
  equipped_dice?: UnitEquippedDie[];
  abilities?: UnitAbilityRecord[];
  unlocked_abilities?: UnitUnlockedAbilityRecord[];
  equipped_abilities?: UnitEquippedAbilityRecord[];
  ability_dice?: UnitAbilityDieRecord[];
  promotion_grants?: UnitPromotionGrantsRecord;
  capstone_choices?: UnitCapstoneChoiceRecord[];
  current_capstone_state?: 'none' | 'unearned' | 'ready_to_select' | 'selected' | string;
  selected_capstone?: UnitSelectedCapstoneRecord | null;
  capstone_selections?: UnitSelectedCapstoneRecord[];
  inherited_passive_abilities?: UnitUnlockedAbilityRecord[];
  [key: string]: unknown;
};

export type TeamFormationCell = {
  cell: string;
  unit_instance_id: string | null;
};

export type TeamRecord = {
  id: string;
  name: string;
  is_active: boolean;
  unit_ids: string[];
  formation: TeamFormationCell[];
  [key: string]: unknown;
};

export type DiceAffixRecord = {
  affix_definition_id: string;
  affix_slug?: string;
  name?: string;
  rarity?: string;
  kind?: 'passive' | 'triggered' | string;
  description?: string;
  value: number;
};

export type DiceRecord = {
  id: string;
  dice_definition_id?: string;
  display_name?: string | null;
  rarity?: string;
  sides?: number;
  slot_capacity?: number;
  affix_slots?: number;
  value?: number;
  sell_value?: number;
  affixes?: DiceAffixRecord[];
  [key: string]: unknown;
};

export type RewardPreviewUnit = {
  unit_instance_id?: string | null;
  name: string;
  unit_type_slug?: string | null;
  unit_type_name: string;
  tier: number;
  level: number;
};

export type RewardPreviewDice = {
  dice_instance_id?: string | null;
  label: string;
  rarity: string;
  material: string;
  sides: number;
  affixes: DiceAffixRecord[];
};

export type RegionUnlockRecord = {
  region_id: string;
  region_slug: string;
  region_name: string;
  region_theme?: string;
  recommended_level?: number;
  energy_cost?: number;
  is_completed?: boolean;
  unlocked_at: string;
};

export type RegionRecord = {
  id: string;
  slug: string;
  name: string;
  theme: string;
  recommended_level: number;
  energy_cost: number;
  is_enabled: boolean;
  is_unlocked: boolean;
  is_completed: boolean;
  unlocked_at: string | null;
};

export type RegionItemRecord = {
  region_item_id: string;
  quantity: number;
};

export type ProfileData = {
  server_time_iso: string;
  squads: TeamRecord[];
  units: UnitRecord[];
  dice: DiceRecord[];
  currency: {
    soft: number;
    hard?: number;
  };
  energy: {
    current: number;
    max: number;
    regen_rate_per_hour?: number;
    last_regen_at?: string;
  };
  squad_unit_cap: number;
  feature_unlocks: string[];
  unit_type_unlocks: string[];
  seen_dialogues?: string[];
  regions: RegionRecord[];
  region_unlocks: RegionUnlockRecord[];
  region_items: RegionItemRecord[];
  active_run: ProfileActiveRun | null;
};

export type ProfileResponse = ApiResponse<ProfileData>;

export type AbilityCatalogEntry = {
  ability_id: string;
  type: 'active' | 'passive' | string;
  display_name: string;
  short_desc: string;
  icon_key: string;
  tags: string[];
  default_params: Record<string, unknown>;
  order: number;
  speed?: number;
  dice_cost?: number;
  default_target?: string | null;
};

export type AbilityCatalogData = {
  catalog_version: number;
  abilities: AbilityCatalogEntry[];
};

export type AbilityCatalogResponse = ApiResponse<AbilityCatalogData>;

export type CurrentRunRecord = {
  run_id: string;
  region_id: string;
  region_slug?: string;
  region_name?: string;
  region_theme?: string;
  recommended_level?: number;
  energy_cost?: number;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

export type RunNodeStatus = 'available' | 'locked' | 'cleared' | string;
export type RunNodeType = 'combat' | 'loot' | 'rest' | 'boss' | 'exit' | 'dialogue' | 'hazard' | string;

export type CurrentRunNode = {
  id: string;
  run_id: string;
  node_index: number;
  node_type: RunNodeType;
  status: RunNodeStatus;
  meta_json?: string | null;
  meta?: Record<string, unknown> | null;
};

export type CurrentRunEdge = {
  edge_id?: string;
  run_id: string;
  from_node_id: string;
  to_node_id: string;
};

export type CurrentRunMap = {
  nodes: CurrentRunNode[];
  edges: CurrentRunEdge[];
};

export type RestRunUnitState = {
  unit_instance_id: string;
  hp?: number;
  current_hp?: number;
  is_defeated: boolean;
  status_effects: unknown[];
  cooldowns_json?: string;
  status_effects_json?: string;
  updated_at?: string;
};

export type CurrentRunData = {
  run: CurrentRunRecord | null;
  map: CurrentRunMap | null;
  run_unit_state?: RestRunUnitState[];
};

export type RunResponse = ApiResponse<CurrentRunData>;

export type RunSummaryPayload = {
  rewards: string[];
  progression: string[];
  survivors: string[];
  defeated: string[];
  meta?: {
    completed_region_slug?: string | null;
    completed_region_name?: string | null;
    new_feature_unlocks?: string[];
    new_region_unlocks?: string[];
  };
  reward_detail?: {
    currency_soft: number;
    units: Array<{ unit_instance_id: string | null; label: string }>;
    dice: Array<{ dice_instance_id: string | null; label: string }>;
  };
  progression_detail?: Array<{
    unit_instance_id: string;
    label: string;
    xp_gained: number;
    is_defeated?: boolean;
    level_gain_count?: number;
    final_level?: number;
    final_xp?: number;
    xp_to_next_level?: number;
    tier?: number;
    max_level?: number;
    unit_type_name?: string;
  }>;
};

export type RestOpenData = {
  run_id: string;
  node_id: string;
  status: 'open' | string;
  run_unit_state: RestRunUnitState[];
};

export type RestFinalizeProgression = {
  id: string;
  from_level: number;
  to_level: number;
  from_xp: number;
  to_xp: number;
};

export type RestFinalizeData = {
  run_id: string;
  node: { id: string; status: string };
  next: { unlocked_node_ids: string[] };
  progression: RestFinalizeProgression[];
};

export type RestOpenResponse = ApiResponse<RestOpenData>;
export type RestFinalizeResponse = ApiResponse<RestFinalizeData>;

export type ExitRunData = {
  run_id: string;
  status: string;
  exit_node_id: string;
  run_summary?: RunSummaryPayload;
};

export type ExitRunResponse = ApiResponse<ExitRunData>;

export type AbandonRunData = {
  run_id: string;
  status: string;
  run_summary?: RunSummaryPayload;
};

export type AbandonRunResponse = ApiResponse<AbandonRunData>;

export type ResolveNodeData = {
  node: {
    id: string;
    status: string;
  };
  battle: {
    battle_id: string;
    outcome: string;
    rounds: number;
    ticks: number;
    status: string;
    reward_preview?: {
      node_type: string;
      xp_total: number;
      currency_soft: number;
      new_unit_labels: string[];
      new_dice_labels: string[];
      units?: RewardPreviewUnit[];
      dice?: RewardPreviewDice[];
    } | null;
    log: {
      meta?: Record<string, unknown>;
      events?: Array<Record<string, unknown>>;
      [key: string]: unknown;
    } | null;
  };
  next: {
    unlocked_node_ids: string[];
  };
};

export type ResolveNodeResponse = ApiResponse<ResolveNodeData>;

export type DialogueNodeCompleteData = {
  node: {
    id: string;
    status: string;
    dialogue_id: string;
  };
  next: {
    unlocked_node_ids: string[];
  };
};

export type DialogueNodeCompleteResponse = ApiResponse<DialogueNodeCompleteData>;

export type BattleClaimData = {
  battle_id: string;
  status: string;
  rewards: {
    xp_total?: number;
    currency_soft?: number;
    new_unit_instance_ids?: string[];
    new_dice_instance_ids?: string[];
    new_unit_labels?: string[];
    new_dice_labels?: string[];
    [key: string]: unknown;
  };
  updated_run_unit_state?: RestRunUnitState[];
  run_resolution?: { run_id: string; status: string } | null;
  xp?: {
    award_per_unit: number;
    applied_unit_instance_ids: string[];
    ignored_at_cap_unit_instance_ids: string[];
  };
  updated_units?: Array<{ id: string; xp: number; level: number; name?: string }>;
  run_summary?: RunSummaryPayload;
};

export type BattleClaimResponse = ApiResponse<BattleClaimData>;

export type ShopDiceItem = {
  product_id: string;
  label: string;
  rarity: string;
  sides: number;
  cost: number;
};

export type ShopUnitItem = {
  product_id: string;
  unit_type_slug: string;
  name: string;
  role: string;
  cost: number;
};

export type ShopFeatureUnlockItem = {
  product_id: string;
  name: string;
  description: string;
  cost: number;
  is_unlocked: boolean;
  category: string;
  requires_unlock_key?: string | null;
  is_available?: boolean;
};

export type ShopDailyDeal = {
  product_id: string;
  shop_date: string;
  slot?: number;
  sides: number;
  rarity: string;
  cost: number;
  is_purchased: boolean;
  affix: {
    slug: string;
    name: string;
    description: string;
    rarity: string;
    value: number;
  };
};

export type ShopCatalogData = {
  server_date: string;
  currency_soft: number;
  basic_dice: ShopDiceItem[];
  basic_units: ShopUnitItem[];
  feature_unlocks: ShopFeatureUnlockItem[];
  daily_deal: ShopDailyDeal | null;
  daily_deals?: ShopDailyDeal[];
};

export type ShopCatalogResponse = ApiResponse<ShopCatalogData>;

export type ShopPurchaseData = {
  item_type: 'basic_unit' | 'basic_dice' | 'daily_deal' | 'feature_unlock';
  product_id: string;
  cost: number;
  currency_soft: number;
  purchase:
    | {
        unit_instance_id: string;
        unit_type_slug: string;
        tier: number;
        level: number;
      }
    | {
        dice_instance_id: string;
        slot?: number;
        rarity: string;
        sides: number;
        affix?: {
          slug: string;
          name: string;
          description: string;
          rarity: string;
          value: number;
        };
      }
    | {
        unlock_namespace: string;
        unlock_key: string;
      };
};

export type ShopPurchaseResponse = ApiResponse<ShopPurchaseData>;

export type AcademyUnitUnlockItem = {
  unit_type_slug: string;
  name: string;
  role: string;
  cost: number;
  is_unlocked: boolean;
};

export type AcademyCatalogData = {
  currency_soft: number;
  unit_unlocks: AcademyUnitUnlockItem[];
};

export type AcademyCatalogResponse = ApiResponse<AcademyCatalogData>;

export type AcademyUnlockUnitTypeData = {
  unit_type_slug: string;
  cost: number;
  currency_soft: number;
};

export type AcademyUnlockUnitTypeResponse = ApiResponse<AcademyUnlockUnitTypeData>;

export type PromotionOptionRecord = {
  branch_unit_type_id: string;
  branch_unit_type_slug: string;
  branch_unit_type_name: string;
  target_unit_type_id: string;
  target_unit_type_slug: string;
  target_unit_type_name: string;
  target_tier: number;
  mode: 'chain' | 'sideways' | string;
  promotion_grants?: UnitPromotionGrantsRecord;
  will_skip_current_capstone?: boolean;
  current_capstone_state?: 'none' | 'unearned' | 'ready_to_select' | 'selected' | string;
};

export type PromotionOptionsData = {
  unit_id: string;
  current_tier: number;
  current_level?: number;
  current_max_level?: number;
  current_promotion_level?: number | null;
  promotion_eligible?: boolean;
  is_mastered?: boolean;
  current_capstone_state?: 'none' | 'unearned' | 'ready_to_select' | 'selected' | string;
  capstone_choices?: UnitCapstoneChoiceRecord[];
  selected_capstone?: UnitSelectedCapstoneRecord | null;
  options: PromotionOptionRecord[];
};

export type PromotionOptionsResponse = ApiResponse<PromotionOptionsData>;

export type PromoteUnitData = {
  unit: { id: string; tier: number; level: number; xp: number };
  consumed_units: string[];
  destination?: PromotionOptionRecord;
};

export type PromoteUnitResponse = ApiResponse<PromoteUnitData>;

export type SelectCapstoneData = {
  unit_id: string;
  selected_capstone: UnitSelectedCapstoneRecord;
};

export type SelectCapstoneResponse = ApiResponse<SelectCapstoneData>;

export type RenameUnitData = {
  unit_id: string;
  display_name: string;
};

export type RenameUnitResponse = ApiResponse<RenameUnitData>;

export type ReplaceEquippedAbilitiesData = {
  unit_id: string;
  equipped_abilities: UnitEquippedAbilityRecord[];
};

export type ReplaceEquippedAbilitiesResponse = ApiResponse<ReplaceEquippedAbilitiesData>;

export type AbilitySlotDiceMutationData = {
  unit_id: string;
  ability_dice: UnitAbilityDieRecord[];
};

export type AbilitySlotDiceMutationResponse = ApiResponse<AbilitySlotDiceMutationData>;

export type DiceSellData = {
  dice_id: string;
  sell_value: number;
  currency_soft: number;
};

export type DiceSellResponse = ApiResponse<DiceSellData>;

export type TeamCreateData = {
  team_id: string;
};

export type TeamMutationData = Record<string, never>;

export type TeamCreateResponse = ApiResponse<TeamCreateData>;
export type TeamActivateResponse = ApiResponse<TeamMutationData>;
export type TeamUpdateResponse = ApiResponse<TeamMutationData>;
export type TeamDeleteResponse = ApiResponse<TeamCreateData>;

export type CreateRunData = Record<string, never>;
export type CreateResponse = ApiResponse<CreateRunData>;

export type DebugUnitTypeRecord = {
  id: string;
  slug: string;
  name: string;
  role: string;
};

export type DebugDiceDefinition = {
  id: string;
  sides: number;
  rarity: string;
  slot_capacity: number;
};

export type DebugRegionItemRecord = {
  id: string;
  slug: string;
  name: string;
  region_slug: string;
  region_name: string;
};

export type DebugOwnedUnitRecord = {
  id: string;
  name: string;
  unit_type_slug: string;
  level: number;
  max_level: number;
};

export type DebugCatalogData = {
  unit_types: DebugUnitTypeRecord[];
  dice_definitions: DebugDiceDefinition[];
  region_items: DebugRegionItemRecord[];
  owned_units: DebugOwnedUnitRecord[];
};

export type DebugCatalogResponse = ApiResponse<DebugCatalogData>;
export type DebugCurrencyGrantResponse = ApiResponse<{ currency: { soft: number; hard?: number } }>;
export type DebugGrantUnitResponse = ApiResponse<{ granted_units: Array<{ id: string; unit_type_slug: string }> }>;
export type DebugGrantDieResponse = ApiResponse<{ granted_dice: Array<{ id: string; sides: number; rarity: string }> }>;
export type DebugGrantRegionItemResponse = ApiResponse<{ region_item: { region_item_slug: string; quantity: number } }>;
export type DebugSetUnitLevelResponse = ApiResponse<{ unit: { id: string; level: number; max_level: number } }>;
export type DebugResetAccountResponse = ApiResponse<{
  reset: {
    user_id: string;
    squads: number;
    units: number;
    dice: number;
    region_unlocks: number;
    active_run: boolean;
  };
}>;

export interface SessionViewModel {
  isAuthenticated: boolean;
  displayName: string;
  userId: string | null;
  csrfToken: string | null;
}

export interface ProfileViewModel {
  energyCurrent: number;
  energyMax: number;
  softCurrency: number;
  activeRunId: string | null;
  squadCount: number;
  unitCount: number;
  activeSquadName: string | null;
}

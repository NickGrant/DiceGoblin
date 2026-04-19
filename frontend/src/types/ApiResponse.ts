/**
 * Generic API envelope used across endpoints.
 * - On success: { ok: true, data: T }
 * - On error:   { ok: false, error: { code, message, details? } }
 */
export type ApiError = {
  code: string;
  message: string;
  details?: Record<string, unknown>;
};

export type ApiOk<T> = { ok: true; data: T };
export type ApiErr = { ok: false; error: ApiError };
export type ApiResponse<T> = ApiOk<T> | ApiErr;

/**
 * ----------------------------------------
 * GET /api/v1/session
 * ----------------------------------------
 */

export type SessionData = {
  authenticated: boolean;
  csrf_token: string;
  user?: {
    id: string;
    display_name?: string | null;
    avatar_url?: string | null;
  };
};

export type SessionResponse = ApiResponse<SessionData>;

/**
 * ----------------------------------------
 * GET /api/v1/profile
 * ----------------------------------------
 */

export type ProfileActiveRun = {
  run_id: string;
  region_id: string;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

// ----------------------------------------
// /profile payload helpers
// ----------------------------------------

export type UnitEquippedDie = {
  dice_instance_id: string;
  slot_index: number;
};

export type UnitAbilityRecord = {
  ability_id: string;
  type?: "active" | "passive" | string;
  display_name?: string;
  order?: number;
};

export type UnitUnlockedAbilityRecord = {
  ability_id: string;
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

export type UnitRecord = {
  // Minimum fields used by UnitListPanel + Warband screen
  id: string;
  name: string;
  level: number;

  // Optional (safe while backend stabilizes)
  unit_type_id?: string;
  unit_type_name?: string;
  tier?: number;
  xp?: number;
  max_level?: number;
  max_tier?: number;
  total_attack?: number;
  total_defense?: number;
  max_hp?: number;
  current_hp?: number;
  xp_to_next_level?: number;
  locked?: boolean;
  equipped_dice?: UnitEquippedDie[];
  abilities?: UnitAbilityRecord[];
  unlocked_abilities?: UnitUnlockedAbilityRecord[];
  equipped_abilities?: UnitEquippedAbilityRecord[];
  ability_dice?: UnitAbilityDieRecord[];

  [key: string]: unknown;
};

export type TeamFormationCell = {
  cell: string; // "A1".."C3"
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
  kind?: "passive" | "triggered" | string;
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

export type ProfileData = {
  server_time_iso: string; // ISO timestamp
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
    regen_rate_per_hour: number;
    last_regen_at: string; // ISO timestamp
  };
  region_unlocks: Array<unknown>;
  region_items: Array<unknown>;
  active_run: ProfileActiveRun | null;
};

export type ProfileResponse = ApiResponse<ProfileData>;

/**
 * ----------------------------------------
 * GET /api/v1/abilities
 * ----------------------------------------
 *
 * Canonical ability catalog payload (from backend AbilityRegistry::toCatalogPayload()).
 */

export type AbilityCatalogEntry = {
  ability_id: string;
  type: "active" | "passive" | string;

  display_name: string;
  short_desc: string;
  icon_key: string;

  tags: string[];
  default_params: Record<string, unknown>;

  /**
   * Present for both active and passive (passives include it for stable sorting).
   */
  order: number;

  /**
   * Active-only fields (backend includes these only when type === "active").
   */
  speed?: number;
  dice_cost?: number;
  default_target?: string | null;
};

export type AbilityCatalogData = {
  catalog_version: number;
  abilities: AbilityCatalogEntry[];
};

export type AbilityCatalogResponse = ApiResponse<AbilityCatalogData>;

/**
 * ----------------------------------------
 * GET /api/v1/runs/current
 * ----------------------------------------
 */

export type RunNodeStatus = "available" | "locked" | "cleared" | string;
export type RunNodeType = "combat" | "loot" | "rest" | "boss" | "exit" | string;

export type CurrentRunRecord = {
  run_id: string;
  region_id: string;
  seed: string;
  status: string;
  started_at: string;
  ended_at: string | null;
};

export type CurrentRunNode = {
  id: string;
  run_id: string;
  node_index: number;
  node_type: RunNodeType;
  status: RunNodeStatus;

  // DB field
  meta_json?: string | null;

  // Controller convenience field (decoded from meta_json)
  meta?: Record<string, unknown> | null;
};

export type CurrentRunEdge = {
  edge_id: string;
  run_id: string;
  from_node_id: string;
  to_node_id: string;
};

export type CurrentRunMap = {
  nodes: CurrentRunNode[];
  edges: CurrentRunEdge[];
};

export type CurrentRunData = {
  run: CurrentRunRecord | null;
  map: CurrentRunMap | null;
};

export type RunResponse = ApiResponse<CurrentRunData>;

export type RestRunUnitState = {
  unit_instance_id: string;
  hp: number;
  is_defeated: boolean;
  status_effects: unknown[];
};

export type RestOpenData = {
  run_id: string;
  node_id: string;
  status: "open" | string;
  team_id: string;
  unit_ids: string[];
  formation: TeamFormationCell[];
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
export type RestStateResponse = ApiResponse<RestOpenData>;
export type RestFinalizeResponse = ApiResponse<RestFinalizeData>;

export type RestStorePurchaseData = {
  run_id: string;
  node_id: string;
  item_type: "basic_unit" | "basic_dice";
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
        rarity: string;
        sides: number;
      };
};

export type RestStorePurchaseResponse = ApiResponse<RestStorePurchaseData>;

export type ExitRunData = {
  run_id: string;
  status: string;
  exit_node_id: string;
};

export type ExitRunResponse = ApiResponse<ExitRunData>;

export type AbandonRunData = {
  run_id: string;
  status: string;
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

export type BattleClaimData = {
  battle_id: string;
  status: string;
  rewards: {
    xp_total?: number;
    currency_soft?: number;
    new_unit_instance_ids?: string[];
    new_dice_instance_ids?: string[];
    [key: string]: unknown;
  };
  updated_run_unit_state?: RestRunUnitState[];
  run_resolution?: { run_id: string; status: string } | null;
  xp?: {
    award_per_unit: number;
    applied_unit_instance_ids: string[];
    ignored_at_cap_unit_instance_ids: string[];
  };
  updated_units?: Array<{ id: string; xp: number; level: number }>;
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

export type ShopDailyDeal = {
  product_id: string;
  shop_date: string;
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
  daily_deal: ShopDailyDeal | null;
};

export type ShopCatalogResponse = ApiResponse<ShopCatalogData>;

export type ShopPurchaseData = {
  item_type: "basic_unit" | "basic_dice" | "daily_deal";
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
        rarity: string;
        sides: number;
        affix?: {
          slug: string;
          name: string;
          description: string;
          rarity: string;
          value: number;
        };
      };
};

export type ShopPurchaseResponse = ApiResponse<ShopPurchaseData>;

export type PromotionOptionRecord = {
  branch_unit_type_id: string;
  branch_unit_type_slug: string;
  branch_unit_type_name: string;
  target_unit_type_id: string;
  target_unit_type_slug: string;
  target_unit_type_name: string;
  target_tier: number;
  mode: "chain" | "sideways" | string;
};

export type PromotionOptionsData = {
  unit_id: string;
  current_tier: number;
  options: PromotionOptionRecord[];
};

export type PromotionOptionsResponse = ApiResponse<PromotionOptionsData>;

export type PromoteUnitData = {
  unit: { id: string; tier: number; level: number; xp: number };
  consumed_units: string[];
  destination?: PromotionOptionRecord;
};

export type PromoteUnitResponse = ApiResponse<PromoteUnitData>;

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

/**
 * ----------------------------------------
 * POST /api/v1/runs
 * ----------------------------------------
 *
 * Success: { ok: true, data: {} } OR in some implementations { ok: true }.
 * To keep the client strictly typed, treat success as an empty object payload.
 */
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

export type DebugCatalogData = {
  unit_types: DebugUnitTypeRecord[];
  dice_definitions: DebugDiceDefinition[];
  region_items: DebugRegionItemRecord[];
};

export type DebugCatalogResponse = ApiResponse<DebugCatalogData>;
export type DebugCurrencyGrantResponse = ApiResponse<{ currency: { soft: number; hard?: number } }>;
export type DebugGrantUnitResponse = ApiResponse<{ granted_units: Array<{ id: string; unit_type_slug: string }> }>;
export type DebugGrantDieResponse = ApiResponse<{ granted_dice: Array<{ id: string; sides: number; rarity: string }> }>;
export type DebugGrantRegionItemResponse = ApiResponse<{ region_item: { region_item_slug: string; quantity: number } }>;
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

import type {
  DiceRecord,
  ProfileData,
  ProfileResponse,
  TeamRecord,
  UnitRecord,
} from "../types/ApiResponse";

const FIXTURE_UNITS: UnitRecord[] = [
  { id: "1", name: "Bogblade", level: 8, unit_type_id: "rogue", unit_type_name: "Skirmisher", tier: 1, xp: 180, max_level: 10, equipped_dice: [{ dice_instance_id: "101", slot_index: 0 }] },
  { id: "2", name: "Grizzle Hex", level: 10, unit_type_id: "shaman", unit_type_name: "Hexer", tier: 1, xp: 260, max_level: 10, equipped_dice: [{ dice_instance_id: "102", slot_index: 0 }] },
  { id: "3", name: "Rivet Fang", level: 9, unit_type_id: "brute", unit_type_name: "Bruiser", tier: 1, xp: 210, max_level: 10 },
  { id: "4", name: "Murk Scout", level: 7, unit_type_id: "rogue", unit_type_name: "Skirmisher", tier: 1, xp: 130, max_level: 10 },
  { id: "5", name: "Tallow Priest", level: 10, unit_type_id: "shaman", unit_type_name: "Hexer", tier: 1, xp: 255, max_level: 10 },
  { id: "6", name: "Scrap Banner", level: 6, unit_type_id: "brute", unit_type_name: "Bruiser", tier: 1, xp: 90, max_level: 10 },
];

const FIXTURE_SQUADS: TeamRecord[] = [
  {
    id: "1",
    name: "Cinder March",
    is_active: true,
    unit_ids: ["1", "2", "3", "4"],
    formation: [
      { cell: "A1", unit_instance_id: "1" },
      { cell: "B1", unit_instance_id: "2" },
      { cell: "A2", unit_instance_id: "3" },
      { cell: "B2", unit_instance_id: "4" },
      { cell: "C1", unit_instance_id: null },
      { cell: "C2", unit_instance_id: null },
      { cell: "A3", unit_instance_id: null },
      { cell: "B3", unit_instance_id: null },
      { cell: "C3", unit_instance_id: null },
    ],
  },
  {
    id: "2",
    name: "Lantern Teeth",
    is_active: false,
    unit_ids: ["2", "5", "6"],
    formation: [
      { cell: "A1", unit_instance_id: "2" },
      { cell: "B1", unit_instance_id: "5" },
      { cell: "A2", unit_instance_id: "6" },
    ],
  },
  {
    id: "3",
    name: "Mire Choir",
    is_active: false,
    unit_ids: ["1", "4", "5"],
    formation: [
      { cell: "A1", unit_instance_id: "4" },
      { cell: "B1", unit_instance_id: "5" },
      { cell: "A2", unit_instance_id: "1" },
    ],
  },
];

const FIXTURE_DICE: DiceRecord[] = [
  { id: "101", display_name: "Rust D6", rarity: "common", sides: 6, slot_capacity: 1, affixes: [] },
  { id: "102", display_name: "Marrow D8", rarity: "rare", sides: 8, slot_capacity: 2, affixes: [] },
  { id: "103", display_name: "Cinder D10", rarity: "epic", sides: 10, slot_capacity: 2, affixes: [] },
  { id: "104", display_name: "Bogglass D12", rarity: "uncommon", sides: 12, slot_capacity: 1, affixes: [] },
];

export function getDebugProfileFixture(): ProfileResponse {
  const data: ProfileData = {
    server_time_iso: "2026-03-12T12:00:00Z",
    squads: FIXTURE_SQUADS,
    units: FIXTURE_UNITS,
    dice: FIXTURE_DICE,
    currency: { soft: 1200, hard: 25 },
    energy: {
      current: 8,
      max: 12,
      regen_rate_per_hour: 6,
      last_regen_at: "2026-03-12T11:45:00Z",
    },
    region_unlocks: [],
    region_items: [],
    active_run: null,
  };

  return { ok: true, data };
}

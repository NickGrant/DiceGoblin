ALTER TABLE `chaos_encounter_results`
  ADD COLUMN `finalized_rewards_json` JSON NULL AFTER `reward_multiplier`,
  ADD COLUMN `finalized_at` TIMESTAMP NULL DEFAULT NULL AFTER `manipulation_count`;

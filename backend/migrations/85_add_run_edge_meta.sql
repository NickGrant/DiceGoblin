ALTER TABLE `run_edges`
  ADD COLUMN `meta_json` JSON NULL AFTER `to_node_id`;

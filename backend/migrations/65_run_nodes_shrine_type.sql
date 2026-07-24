ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit','dialogue','hazard','shrine') NOT NULL;

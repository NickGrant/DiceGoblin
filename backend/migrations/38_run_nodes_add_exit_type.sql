-- Migration: allow explicit run exit node type in run graph.
ALTER TABLE `run_nodes`
  MODIFY COLUMN `node_type` ENUM('combat','loot','rest','boss','exit') NOT NULL;


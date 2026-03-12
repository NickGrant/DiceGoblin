import Phaser from "phaser";
import { createGameConfig } from "./game/config";
import { initializeDebugHooks } from "./debug/debugHooks";
import { getDebugSceneConfig } from "./debug/debugScene";

initializeDebugHooks(getDebugSceneConfig());
new Phaser.Game(createGameConfig());

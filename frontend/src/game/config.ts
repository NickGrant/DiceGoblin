import Phaser from "phaser";
import BootScene from "../scenes/BootScene";
import LandingScene from "../scenes/LandingScene";
import HomeScene from "../scenes/HomeScene";
import PreloadScene from "../scenes/PreloadScene";
import RegionSelectScene from "../scenes/RegionSelectScene";
import WarbandManagementScene from "../scenes/WarbandManagementScene";
import DiceInventoryScene from "../scenes/DiceInventoryScene";
import MapExplorationScene from "../scenes/MapExplorationScene";
import RestManagementScene from "../scenes/RestManagementScene";
import RunEndSummaryScene from "../scenes/RunEndSummaryScene";

export const GAME_WIDTH = 960;
export const GAME_HEIGHT = 540;

export function createGameConfig(): Phaser.Types.Core.GameConfig {
  return {
    type: Phaser.AUTO,
    parent: "app",
    width: GAME_WIDTH,
    height: GAME_HEIGHT,
    backgroundColor: "#1A0E0A",
    pixelArt: false,
    physics: { default: "arcade" },
    scene: [
      BootScene, PreloadScene, LandingScene, HomeScene, RegionSelectScene, 
      WarbandManagementScene, DiceInventoryScene, MapExplorationScene, RestManagementScene, RunEndSummaryScene
    ]
  };
}

import Phaser from "phaser";
import BootScene from "../scenes/BootScene";
import LandingScene from "../scenes/LandingScene";

export const GAME_WIDTH = 960;
export const GAME_HEIGHT = 540;

export function createGameConfig(): Phaser.Types.Core.GameConfig {
  return {
    type: Phaser.AUTO,
    parent: "app",
    width: GAME_WIDTH,
    height: GAME_HEIGHT,
    backgroundColor: "#0b0b0f",
    pixelArt: false,
    physics: { default: "arcade" },
    scene: [BootScene, LandingScene]
  };
}

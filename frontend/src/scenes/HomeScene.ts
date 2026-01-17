import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { apiClient } from "../services/apiClient";
import BackgroundImage from "../components/BackgroundImage";
import UiButton from "../components/Button";
import HudPanel from "../components/HudPanel";
import RegionSelect from "../components/clickable-panel/RegionSelect";
import WarbandManagement from "../components/clickable-panel/WarbandManagement";
import DiceInventory from "../components/clickable-panel/DiceInventory";

export default class HomeScene extends Phaser.Scene {

  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    // Background / frame
    new BackgroundImage(this, 'background_workbench');
    new HudPanel(this);

    new RegionSelect(this, {x: 300,y: 256});
    new WarbandManagement(this,{x: 774, y: 145});
    new DiceInventory(this, {x: 774, y: 245});
    new UiButton({
      small: true,
      scene: this,
      x: GAME_WIDTH - 166,
      y: GAME_HEIGHT - 110,
      label: "Log out",
      onClick: async () => {
      try {
        await apiClient.logout();
      } finally {
        window.location.reload();
      }
    }
    });
  }
}

import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { apiClient, type SessionResponse } from "../services/apiClient";
import BackgroundImage from "../components/BackgroundImage";
import UiButton from "../components/button";
import HudPanel from "../components/HudPanel";
import ClickablePanelRegionSelect from "../components/ClickablePanel-RegionSelect";
import ClickablePanelWarbandManagement from "../components/ClickablePanel-WarbandManagement";
import ClickablePanelDiceInventory from "../components/ClickablePanel-DiceInventory";

type HomeData = {
  session?: SessionResponse;
};

export default class HomeScene extends Phaser.Scene {
  private session: SessionResponse | null = null;

  constructor() {
    super({ key: "HomeScene" });
  }

  init(data: HomeData): void {
    this.session = data.session ?? null;
  }

  create(): void {
    // Defensive: BootScene should only route here when authenticated, but don't crash if it doesn't.
    const displayName = this.session?.data.user?.display_name ?? "Goblin";

    // Background / frame
    new BackgroundImage(this, 'background_workbench');

    new HudPanel(this, displayName,100,100);

    new ClickablePanelRegionSelect(this, {x: 300,y: 256});
    new ClickablePanelWarbandManagement(this,{x: 774, y: 145});
    new ClickablePanelDiceInventory(this, {x: 774, y: 245});
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
        // simplest reset: reload app and let BootScene decide where to go
        window.location.reload();
      }
    }
    });
  }
}

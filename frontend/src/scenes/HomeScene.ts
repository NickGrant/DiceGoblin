import Phaser from "phaser";
import { apiClient } from "../services/apiClient";
import BackgroundImage from "../components/BackgroundImage";
import ActionButton from "../components/clickable-panel/ActionButton";
import ActionButtonList from "../components/clickable-panel/ActionButtonList";
import HudPanel from "../components/HudPanel";
import RegionSelect from "../components/clickable-panel/RegionSelect";
import { getPageLayout } from "../layout/pageLayout";

export default class HomeScene extends Phaser.Scene {

  constructor() {
    super({ key: "HomeScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_workbench');
    new HudPanel(this);
    const layout = getPageLayout(this);
    const buttonX = layout.buttons.x + 10;

    new RegionSelect(this, {
      x: layout.content.x + 80,
      y: layout.content.y + 32
    });
    new ActionButtonList({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + 24,
      buttons: [
        {
          label: "Warband",
          iconKey: "icon_warband",
          targetSceneKey: "WarbandManagementScene",
        },
        {
          label: "Inventory",
          iconKey: "icon_inventory",
          targetSceneKey: "DiceInventoryScene",
        },
      ],
    });
    new ActionButton({
      scene: this,
      x: buttonX,
      y: layout.buttons.y + layout.buttons.height - 100,
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



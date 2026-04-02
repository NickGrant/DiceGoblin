import type Phaser from "phaser";
import { getPageLayout } from "../layout/pageLayout";
import { apiClient } from "../services/apiClient";
import { RegistrySession } from "../state/RegistrySession";

const STRIP_DEPTH = 100000;
const STRIP_WIDTH = 980;
const STRIP_HEIGHT = 84;
const ICON_SIZE = 40;
const RESOURCE_ICON_SIZE = 16;
const ENERGY_COLOR = "#00e015";
const CURRENCY_COLOR = "#e0d300";
const PLAYER_NAME_COLOR = "#23272A";

const NAV_BUTTONS = [
  { key: "home", tooltipText: "Home", targetScene: "HomeScene", iconKey: "icon_home", fallbackColor: 0x1683ff },
  { key: "warband", tooltipText: "Warband", targetScene: "WarbandManagementScene", iconKey: "icon_warband", fallbackColor: 0x02e0c8 },
  { key: "inventory", tooltipText: "Inventory", targetScene: "InventoryScene", iconKey: "icon_inventory", fallbackColor: 0x3a00e0 },
  { key: "shop", tooltipText: "Shop", targetScene: "ShopScene", fallbackColor: 0xc903e0 },
] as const;

type StripButton = {
  visual: Phaser.GameObjects.Image | Phaser.GameObjects.Rectangle;
  hitZone: Phaser.GameObjects.Zone;
  tooltip: Phaser.GameObjects.Text;
  targetScene: string;
};

export function mountBottomCommandStrip(scene: Phaser.Scene): void {
  const addApi = (scene as unknown as {
    add?: { image?: unknown; rectangle?: unknown; text?: unknown; zone?: unknown };
  }).add;
  if (!addApi) return;
  if (typeof addApi.text !== "function") return;
  if (typeof addApi.zone !== "function") return;
  if (typeof addApi.image !== "function" && typeof addApi.rectangle !== "function") return;
  new BottomCommandStrip(scene);
}

export default class BottomCommandStrip {
  private readonly scene: Phaser.Scene;
  private readonly stripBackground: Phaser.GameObjects.Image;
  private readonly navButtons: StripButton[];
  private readonly logoutButton: StripButton;
  private readonly energyIcon?: Phaser.GameObjects.Image;
  private readonly energyText: Phaser.GameObjects.Text;
  private readonly teethIcon?: Phaser.GameObjects.Image;
  private readonly currencyText: Phaser.GameObjects.Text;
  private readonly playerNameText: Phaser.GameObjects.Text;

  constructor(scene: Phaser.Scene) {
    this.scene = scene;

    const backgroundKey = scene.textures.exists("base_bar") ? "base_bar" : "manifest_strip";
    this.stripBackground = scene.add.image(0, 0, backgroundKey).setOrigin(0.5, 0.5);

    this.navButtons = NAV_BUTTONS.map((config) => this.createStripButton(config));
    this.logoutButton = this.createStripButton({
      tooltipText: "Logout",
      targetScene: "LandingScene",
      iconKey: "icon_logout",
      fallbackColor: 0xe09d70,
    });

    this.energyIcon = scene.textures.exists("icon_energy_small")
      ? scene.add.image(0, 0, "icon_energy_small").setOrigin(0.5, 0.5).setDisplaySize(RESOURCE_ICON_SIZE, RESOURCE_ICON_SIZE)
      : undefined;
    this.energyText = scene.add.text(0, 0, "-- / --", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: ENERGY_COLOR,
    }).setOrigin(0, 0.5);
    this.teethIcon = scene.textures.exists("icon_tooth_small")
      ? scene.add.image(0, 0, "icon_tooth_small").setOrigin(0.5, 0.5).setDisplaySize(RESOURCE_ICON_SIZE, RESOURCE_ICON_SIZE)
      : undefined;
    this.currencyText = scene.add.text(0, 0, "0", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: CURRENCY_COLOR,
    }).setOrigin(0, 0.5);
    this.playerNameText = scene.add.text(0, 0, this.resolvePlayerName(), {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: PLAYER_NAME_COLOR,
      align: "left",
    }).setOrigin(0, 0.5);

    this.setLayerProps();
    this.reposition();
    this.syncProfileData();

    scene.scale.on("resize", this.reposition, this);
    scene.events.once("shutdown", () => this.destroy());
    scene.events.once("destroy", () => this.destroy());
  }

  private destroy(): void {
    this.scene.scale.off("resize", this.reposition, this);
    this.stripBackground.destroy();
    this.energyIcon?.destroy();
    this.energyText.destroy();
    this.teethIcon?.destroy();
    this.currencyText.destroy();
    this.playerNameText.destroy();
    this.destroyStripButton(this.logoutButton);
    for (const button of this.navButtons) {
      this.destroyStripButton(button);
    }
  }

  private setLayerProps(): void {
    const objects: Array<{ setScrollFactor: (factor: number) => unknown; setDepth: (depth: number) => unknown }> = [
      this.stripBackground,
      ...(this.energyIcon ? [this.energyIcon] : []),
      this.energyText,
      ...(this.teethIcon ? [this.teethIcon] : []),
      this.currencyText,
      this.playerNameText,
      ...this.navButtons.flatMap((button) => [button.visual, button.hitZone, button.tooltip]),
      this.logoutButton.visual,
      this.logoutButton.hitZone,
      this.logoutButton.tooltip,
    ];

    for (const object of objects) {
      object.setScrollFactor(0);
      object.setDepth(STRIP_DEPTH);
    }
  }

  private createStripButton(config: {
    tooltipText: string;
    targetScene: string;
    iconKey?: string;
    fallbackColor: number;
  }): StripButton {
    const visual = config.iconKey && this.scene.textures.exists(config.iconKey)
      ? this.scene.add.image(0, 0, config.iconKey).setOrigin(0, 0).setDisplaySize(ICON_SIZE, ICON_SIZE)
      // Missing icon asset for this strip entry: use a flat color block until art is generated.
      : this.scene.add.rectangle(0, 0, ICON_SIZE, ICON_SIZE, config.fallbackColor, 1).setOrigin(0, 0);

    const hitZone = this.scene.add.zone(0, 0, ICON_SIZE, ICON_SIZE).setOrigin(0, 0).setInteractive({ useHandCursor: true });
    const tooltip = this.scene.add.text(0, 0, config.tooltipText, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "14px",
      color: "#ffffff",
      backgroundColor: "#22131b",
      padding: { left: 6, right: 6, top: 4, bottom: 4 },
    }).setOrigin(0.5, 1).setVisible(false);

    hitZone.on("pointerover", () => {
      visual.setAlpha(0.82);
      tooltip.setVisible(true);
    });
    hitZone.on("pointerout", () => {
      visual.setAlpha(1);
      tooltip.setVisible(false);
    });
    hitZone.on("pointerdown", () => {
      visual.setAlpha(0.68);
    });
    hitZone.on("pointerup", () => {
      visual.setAlpha(0.82);
      if (config.targetScene === "LandingScene") {
        void this.handleLogout();
        return;
      }
      this.scene.scene.start(config.targetScene);
    });

    return {
      visual,
      hitZone,
      tooltip,
      targetScene: config.targetScene,
    };
  }

  private destroyStripButton(button: StripButton): void {
    button.visual.destroy();
    button.hitZone.destroy();
    button.tooltip.destroy();
  }

  private reposition(): void {
    const layout = getPageLayout(this.scene);
    const centerX = layout.bottomStrip.x + layout.bottomStrip.width / 2;
    const centerY = layout.bottomStrip.y + layout.bottomStrip.height / 2;
    const left = centerX - STRIP_WIDTH / 2;
    const top = centerY - STRIP_HEIGHT / 2;

    this.stripBackground.setPosition(centerX, centerY).setDisplaySize(STRIP_WIDTH, STRIP_HEIGHT);

    const navTop = top + 14;
    const navSlots = [left + 59, left + 129, left + 199, left + 269];

    for (let index = 0; index < this.navButtons.length; index += 1) {
      const button = this.navButtons[index];
      if (!button) continue;
      const x = navSlots[index] ?? (left + 59 + index * 70);
      button.visual.setPosition(x, navTop);
      button.hitZone.setPosition(x, navTop);
      button.tooltip.setPosition(x + ICON_SIZE / 2, navTop - 8);
    }

    const textY = top + 29;
    this.energyIcon?.setPosition(left + 413, textY);
    this.energyText.setPosition(left + 425, textY);
    this.teethIcon?.setPosition(left + 503, textY);
    this.currencyText.setPosition(left + 515, textY);
    this.playerNameText.setPosition(left + 684, textY);

    const logoutX = left + 878;
    const logoutY = top + 14;
    this.logoutButton.visual.setPosition(logoutX, logoutY);
    this.logoutButton.hitZone.setPosition(logoutX, logoutY);
    this.logoutButton.tooltip.setPosition(logoutX + ICON_SIZE / 2, logoutY - 8);
  }

  private async handleLogout(): Promise<void> {
    try {
      await apiClient.logout();
    } catch {
      // Continue local logout flow even if network/logout endpoint fails.
    }
    RegistrySession.clear(this.scene.registry);
    this.scene.scene.start("LandingScene");
  }

  private resolvePlayerName(): string {
    return RegistrySession.displayName(this.scene.registry).toUpperCase();
  }

  private syncProfileData(): void {
    void apiClient.getProfile({ allowStaleOnError: true }).then((profile) => {
      if (!profile.ok) {
        this.playerNameText.setText(this.resolvePlayerName());
        return;
      }
      this.energyText.setText(`${profile.data.energy.current} / ${profile.data.energy.max}`);
      this.currencyText.setText(String(profile.data.currency.soft));
      this.playerNameText.setText(this.resolvePlayerName());
    }).catch(() => {
      this.playerNameText.setText(this.resolvePlayerName());
    });
  }
}

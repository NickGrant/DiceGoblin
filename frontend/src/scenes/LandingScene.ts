import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";

type LandingData = {
  authenticated?: boolean;
};

export default class LandingScene extends Phaser.Scene {
  private authenticated = false;

  constructor() {
    super({ key: "LandingScene" });
  }

  init(data: LandingData): void {
    this.authenticated = Boolean(data?.authenticated);
  }

  create(): void {
    const title = this.add
      .text(GAME_WIDTH / 2, 120, "Dice Goblins", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "48px",
        color: "#e8e8ff"
      })
      .setOrigin(0.5);

    this.add
      .text(GAME_WIDTH / 2, 170, "Tactics. Loot. Questionable management.", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "18px",
        color: "#b8b8d8"
      })
      .setOrigin(0.5);

    const buttonY = 280;

    if (this.authenticated) {
      this.createButton(GAME_WIDTH / 2, buttonY, "Continue", () => {
        // Next scene later: HomeScene
        this.flashMessage("TODO: HomeScene");
      });
    } else {
      this.createButton(GAME_WIDTH / 2, buttonY, "Log in with Discord", () => {
        // Next scene later: AuthScene or direct OAuth redirect
        this.flashMessage("TODO: Discord OAuth");
      });
    }

    // Footer
    this.add
      .text(GAME_WIDTH / 2, GAME_HEIGHT - 24, "MVP build", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "14px",
        color: "#6e6e90"
      })
      .setOrigin(0.5);
  }

  private createButton(x: number, y: number, label: string, onClick: () => void): void {
    const width = 260;
    const height = 52;

    const bg = this.add
      .rectangle(x, y, width, height, 0x1a1a2a, 1)
      .setStrokeStyle(2, 0x3a3a66, 1);

    const text = this.add
      .text(x, y, label, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "20px",
        color: "#ffffff"
      })
      .setOrigin(0.5);

    const hitArea = new Phaser.Geom.Rectangle(x - width / 2, y - height / 2, width, height);
    bg.setInteractive(hitArea, Phaser.Geom.Rectangle.Contains);

    bg.on("pointerover", () => bg.setFillStyle(0x24243a, 1));
    bg.on("pointerout", () => bg.setFillStyle(0x1a1a2a, 1));
    bg.on("pointerdown", () => bg.setFillStyle(0x2f2f55, 1));
    bg.on("pointerup", () => {
      bg.setFillStyle(0x24243a, 1);
      onClick();
    });

    // Keep text above background
    text.setDepth(1);
  }

  private flashMessage(message: string): void {
    const toast = this.add
      .text(GAME_WIDTH / 2, 220, message, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "16px",
        color: "#ffffff",
        backgroundColor: "#000000",
        padding: { x: 10, y: 6 }
      })
      .setOrigin(0.5);

    this.tweens.add({
      targets: toast,
      alpha: 0,
      duration: 900,
      delay: 800,
      onComplete: () => toast.destroy()
    });
  }
}

import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { API_BASE_URL } from "../services/apiClient";

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
    console.log("Input enabled:", this.input.enabled);
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
        this.scene.start("HomeScene");
      });
    } else {
      this.createButton(GAME_WIDTH / 2, buttonY, "Log in with Discord", () => {
        this.flashMessage("Redirecting…");
        window.location.href = `${API_BASE_URL}/auth/discord/start`;
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

  private createButton(
    x: number,
    y: number,
    label: string,
    onClick: () => void
  ): void {
    const width = 260;
    const height = 52;

    const bg = this.add
      .rectangle(0, 0, width, height, 0x1a1a2a, 1)
      .setStrokeStyle(2, 0x3a3a66, 1);

    const text = this.add
      .text(0, 0, label, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "20px",
        color: "#ffffff"
      })
      .setOrigin(0.5);

    const container = this.add.container(x, y, [bg, text]);
    container.setSize(width, height);

    // Important: interactive on container with explicit hit area
    container.setInteractive(
      new Phaser.Geom.Rectangle(-width / 2, -height / 2, width, height),
      Phaser.Geom.Rectangle.Contains
    );

    // Nice UX: show hand cursor
    container.on("pointerover", () => {
      bg.setFillStyle(0x24243a, 1);
      this.input.setDefaultCursor("pointer");
    });

    container.on("pointerout", () => {
      bg.setFillStyle(0x1a1a2a, 1);
      this.input.setDefaultCursor("default");
    });

    container.on("pointerdown", () => bg.setFillStyle(0x2f2f55, 1));

    container.on("pointerup", () => {
      bg.setFillStyle(0x24243a, 1);
      onClick();
    });
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

import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { apiClient, type SessionResponse } from "../services/apiClient";

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
    const displayName = this.session?.user?.display_name ?? "Goblin";

    this.add
      .text(GAME_WIDTH / 2, 120, `Welcome, ${displayName}`, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "32px",
        color: "#e8e8ff"
      })
      .setOrigin(0.5);

    this.add
      .text(GAME_WIDTH / 2, 170, "HomeScene (MVP).", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "18px",
        color: "#b8b8d8"
      })
      .setOrigin(0.5);

    this.createButton(GAME_WIDTH / 2, 280, "Log out", async () => {
      try {
        await apiClient.logout();
      } finally {
        // simplest reset: reload app and let BootScene decide where to go
        window.location.reload();
      }
    });

    this.createButton(GAME_WIDTH / 2, 350, "Back to Landing", () => {
      this.scene.start("LandingScene", { authenticated: true });
    });
  }

  private createButton(x: number, y: number, label: string, onClick: () => void): void {
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

    container.setInteractive(
      new Phaser.Geom.Rectangle(-width / 2, -height / 2, width, height),
      Phaser.Geom.Rectangle.Contains
    );

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
}

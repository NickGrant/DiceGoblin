import Phaser from "phaser";
import { TEXT_HEADER, TEXT_BODY } from "../const/Text";
import type { SessionResponse } from "../services/apiClient";

type PreloadData = {
  authenticated: boolean;
  session: SessionResponse | null;
  offline: boolean;
};

export default class PreloadScene extends Phaser.Scene {
  private dataFromBoot: PreloadData | null = null;

  constructor() {
    super({ key: "PreloadScene" });
  }

  init(data: PreloadData): void {
    this.dataFromBoot = data;
  }

  preload(): void {
    const centerX = this.cameras.main.centerX;
    const centerY = this.cameras.main.centerY;

    const title = this.add
      .text(centerX, centerY - 40, "Loading assets…", TEXT_HEADER)
      .setOrigin(0.5);

    const progressText = this.add
      .text(centerX, centerY + 10, "0%", TEXT_BODY)
      .setOrigin(0.5);

    // Optional: show what is currently being loaded
    const fileText = this.add
      .text(centerX, centerY + 40, "", TEXT_BODY)
      .setOrigin(0.5);

    this.load.on(Phaser.Loader.Events.PROGRESS, (value: number) => {
      const pct = Math.round(value * 100);
      progressText.setText(`${pct}%`);
    });

    this.load.on(
      Phaser.Loader.Events.FILE_PROGRESS,
      (file: Phaser.Loader.File) => {
        fileText.setText(file.key ? `Loading: ${file.key}` : "Loading…");
      }
    );

    this.load.on(Phaser.Loader.Events.COMPLETE, () => {
      title.setText("Loaded");
      fileText.setText("");
    });

    this.load.pack("ui", "/assets/packs/ui.json");
  }

  create(): void {
    console.log('!!data', this.dataFromBoot);
    const boot = this.dataFromBoot ?? {
      authenticated: false,
      session: null,
      offline: false
    };

    // Route based on auth result (assets are now available globally).
    if (boot.authenticated && boot.session) {
      this.scene.start("HomeScene", { session: boot.session, offline: boot.offline });
    } else {
      this.scene.start("LandingScene", { authenticated: false, offline: boot.offline });
    }
  }
}

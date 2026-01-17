import Phaser from "phaser";
import { TEXT_HEADER, TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";

export default class PreloadScene extends Phaser.Scene {

  constructor() {
    super({ key: "PreloadScene" });
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
    const nextScene = RegistrySession.get(this.registry)?.isAuthenticated ? 'HomeScene' : 'LandingScene';
    this.scene.start(nextScene);
  }
}

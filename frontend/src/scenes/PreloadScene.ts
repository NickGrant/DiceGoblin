import Phaser from "phaser";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";

export default class PreloadScene extends Phaser.Scene {
  constructor() {
    super({ key: "PreloadScene" });
  }

  preload(): void {
    const centerX = this.cameras.main.centerX;
    const centerY = this.cameras.main.centerY;

    if (this.textures.exists("hero_logo")) {
      const logo = this.add.image(0, 0, "hero_logo").setOrigin(0, 0);
      const targetW = Math.min(this.scale.width * 0.72, 680);
      const scale = targetW / logo.width;
      logo.setScale(scale);
      logo.setPosition((this.scale.width - logo.displayWidth) / 2, Math.max(32, centerY - 250));
    }

    const title = this.add.text(0, 0, "Loading assets...", TEXT_HEADER).setOrigin(0, 0);
    const progressText = this.add.text(0, 0, "0%", TEXT_BODY).setOrigin(0, 0);
    const fileText = this.add.text(0, 0, "", TEXT_BODY).setOrigin(0, 0);

    title.setPosition(centerX - title.width / 2, centerY + 40);
    progressText.setPosition(centerX - progressText.width / 2, centerY + 92);
    fileText.setPosition(centerX - 240, centerY + 126);

    this.load.on(Phaser.Loader.Events.PROGRESS, (value: number) => {
      const pct = Math.round(value * 100);
      progressText.setText(`${pct}%`);
      progressText.setPosition(centerX - progressText.width / 2, centerY + 92);
    });

    this.load.on(Phaser.Loader.Events.FILE_PROGRESS, (file: Phaser.Loader.File) => {
      fileText.setText(file.key ? `Loading: ${file.key}` : "Loading...");
      fileText.setPosition(centerX - Math.min(320, fileText.width / 2), centerY + 126);
    });

    this.load.on(Phaser.Loader.Events.COMPLETE, () => {
      title.setText("Loaded");
      title.setPosition(centerX - title.width / 2, centerY + 40);
      fileText.setText("");
    });

    this.load.pack("ui", "/assets/packs/ui.json");
  }

  create(): void {
    const nextScene = RegistrySession.get(this.registry)?.isAuthenticated ? "HomeScene" : "LandingScene";
    this.scene.start(nextScene);
  }
}


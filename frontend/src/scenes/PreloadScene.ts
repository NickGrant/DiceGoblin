import Phaser from "phaser";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";

export default class PreloadScene extends Phaser.Scene {
  constructor() {
    super({ key: "PreloadScene" });
  }

  preload(): void {
    this.renderBackground();

    const centerX = this.cameras.main.centerX;
    const centerY = this.cameras.main.centerY;
    const screenW = this.scale.width;
    const screenH = this.scale.height;
    let textBaseY = centerY + 40;

    if (this.textures.exists("hero_logo")) {
      const logo = this.add.image(0, 0, "hero_logo").setOrigin(0, 0);
      const targetW = Math.min(screenW * 0.62, 560);
      const scale = targetW / logo.width;
      logo.setScale(scale);
      const logoY = Math.max(20, centerY - 240);
      logo.setPosition((screenW - logo.displayWidth) / 2, logoY);
      textBaseY = Math.min(screenH - 170, logoY + logo.displayHeight + 36);
    }

    const title = this.add.text(0, 0, "Loading assets...", TEXT_HEADER).setOrigin(0, 0);
    const progressText = this.add.text(0, 0, "0%", TEXT_BODY).setOrigin(0, 0);
    const fileText = this.add.text(0, 0, "", TEXT_BODY).setOrigin(0, 0);
    fileText.setStyle({ fontSize: "24px" });

    title.setPosition(centerX - title.width / 2, textBaseY);
    progressText.setPosition(centerX - progressText.width / 2, textBaseY + 58);
    fileText.setPosition(centerX - Math.min(300, fileText.width / 2), textBaseY + 98);

    this.load.on(Phaser.Loader.Events.PROGRESS, (value: number) => {
      const pct = Math.round(value * 100);
      progressText.setText(`${pct}%`);
      progressText.setPosition(centerX - progressText.width / 2, textBaseY + 58);
    });

    this.load.on(Phaser.Loader.Events.FILE_PROGRESS, (file: Phaser.Loader.File) => {
      fileText.setText(file.key ? `Loading: ${file.key}` : "Loading...");
      fileText.setPosition(centerX - Math.min(320, fileText.width / 2), textBaseY + 98);
    });

    this.load.on(Phaser.Loader.Events.COMPLETE, () => {
      title.setText("Loaded");
      title.setPosition(centerX - title.width / 2, textBaseY);
      fileText.setText("");
    });

    this.load.pack("ui", "/assets/packs/ui.json");
  }

  create(): void {
    const nextScene = RegistrySession.get(this.registry)?.isAuthenticated ? "HomeScene" : "LandingScene";
    this.scene.start(nextScene);
  }

  private renderBackground(): void {
    if (!this.textures.exists("texture_paper")) return;
    const bg = this.add.image(0, 0, "texture_paper").setOrigin(0, 0);
    const scale = Math.max(this.scale.width / bg.width, this.scale.height / bg.height);
    bg.setScale(scale);
    bg.setDepth(-1000);
  }
}




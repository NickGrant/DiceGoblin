import Phaser from "phaser";
import { apiClient } from "../services/apiClient";
import { TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";

export default class BootScene extends Phaser.Scene {
  constructor() {
    super({ key: "BootScene" });
  }

  preload(): void {
    this.load.image("hero_logo", "/assets/hero_logo.png");
    this.load.image("texture_paper", "/assets/ui/textures/paper.png");
  }

  create(): void {
    this.renderBackground();

    const statusText = this.add
      .text(this.cameras.main.centerX, this.cameras.main.centerY, "Loading...", TEXT_BODY)
      .setOrigin(0, 0);
    this.centerText(statusText);

    void this.checkSessionAndContinue(statusText);
  }

  private async checkSessionAndContinue(
    statusText: Phaser.GameObjects.Text
  ): Promise<void> {
    try {
      const session = await apiClient.getSession();
      if (!session.ok) {
        throw new Error(session.error.message);
      }

      RegistrySession.set(this.registry, {
        isAuthenticated: !!session.data.authenticated,
        user: {
          id: session.data.user?.id || "",
          displayName: session.data.user?.display_name || "",
          avatarUrl: session.data.user?.avatar_url || "",
        },
        csrfToken: session.data.csrf_token,
      });

      statusText.setText(!!session.data.authenticated ? "Welcome back..." : "Welcome...");
      this.centerText(statusText);
    } catch {
      RegistrySession.set(this.registry, { isAuthenticated: false });
      statusText.setText("Offline mode (API unavailable)");
      this.centerText(statusText);
    }

    this.time.delayedCall(250, () => {
      this.scene.start("PreloadScene");
    });
  }

  private centerText(text: Phaser.GameObjects.Text): void {
    text.setPosition(
      this.cameras.main.centerX - text.width / 2,
      this.cameras.main.centerY - text.height / 2
    );
  }

  private renderBackground(): void {
    if (!this.textures.exists("texture_paper")) return;
    const bg = this.add.image(0, 0, "texture_paper").setOrigin(0, 0);
    const scale = Math.max(this.scale.width / bg.width, this.scale.height / bg.height);
    bg.setScale(scale);
    bg.setDepth(-1000);
  }
}




import Phaser from "phaser";
import { GAME_HEIGHT } from "../game/config";
import { API_BASE_URL } from "../services/apiClient";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import BackgroundImage from "../components/BackgroundImage";
import UiButton from "../components/Button";
import { RegistrySession } from "../state/RegistrySession";

export default class LandingScene extends Phaser.Scene {

  constructor() {
    super({ key: "LandingScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_concrete');
    
    this.add
      .text(this.cameras.main.centerX, 120, "Let's get started", TEXT_HEADER)
      .setOrigin(0.5);

    if (RegistrySession.get(this.registry)?.isAuthenticated) {
      this.createButton("Continue", () => {
        this.scene.start("HomeScene");
      });
    } else {
      this.createButton("Log in with Discord", () => {
        this.flashMessage("Redirecting…");
        window.location.href = `${API_BASE_URL}/auth/discord/start`;
      });
    }

    // Footer
    this.add
      .text(this.cameras.main.centerX, GAME_HEIGHT - 24, "MVP build", TEXT_BODY)
      .setOrigin(0.5);
  }

  private createButton(
    label: string,
    onClick: () => void
  ): UiButton {
    return new UiButton({
      scene: this,
      x: this.cameras.main.centerX,
      y: 280,
      label: label,
      onClick: onClick
    })
  }


  private flashMessage(message: string): void {
    const toast = this.add
      .text(this.cameras.main.centerX, 220, message, TEXT_BODY)
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

import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { API_BASE_URL } from "../services/apiClient";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import BackgroundImage from "../components/BackgroundImage";
import UiButton from "../components/button";

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
    new BackgroundImage(this, 'background_concrete');
    const title = this.add
      .text(GAME_WIDTH / 2, 120, "Let's get started", TEXT_HEADER)
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
      .text(GAME_WIDTH / 2, GAME_HEIGHT - 24, "MVP build", TEXT_BODY)
      .setOrigin(0.5);
  }

  private createButton(
    x: number,
    y: number,
    label: string,
    onClick: () => void
  ): UiButton {
    return new UiButton({
      scene: this,
      x: x,
      y: y,
      label: label,
      onClick: onClick
    })
  }


  private flashMessage(message: string): void {
    const toast = this.add
      .text(GAME_WIDTH / 2, 220, message, TEXT_BODY)
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

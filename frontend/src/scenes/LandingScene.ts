import Phaser from "phaser";
import { GAME_HEIGHT } from "../game/config";
import { API_BASE_URL } from "../services/apiClient";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import BackgroundImage from "../components/BackgroundImage";
import ActionButton from "../components/clickable-panel/ActionButton";
import { RegistrySession } from "../state/RegistrySession";

export default class LandingScene extends Phaser.Scene {
  constructor() {
    super({ key: "LandingScene" });
  }

  create(): void {
    new BackgroundImage(this);

    const title = this.add.text(0, 120, "Let's get started", TEXT_HEADER).setOrigin(0, 0);
    title.setPosition(this.cameras.main.centerX - title.width / 2, 120);

    if (RegistrySession.isAuthed(this.registry)) {
      this.createButton("Continue", () => {
        this.scene.start("HomeScene");
      });
    } else {
      this.createButton("Log in with Discord", () => {
        this.flashMessage("Redirecting...");
        window.location.href = `${API_BASE_URL}/auth/discord/start`;
      });
    }

    const footer = this.add.text(0, GAME_HEIGHT - 24, "MVP build", TEXT_BODY).setOrigin(0, 0);
    footer.setPosition(this.cameras.main.centerX - footer.width / 2, GAME_HEIGHT - 24);
  }

  private createButton(label: string, onClick: () => void): ActionButton {
    return new ActionButton({
      scene: this,
      x: this.cameras.main.centerX - 150,
      y: 280,
      label,
      onClick,
    });
  }

  private flashMessage(message: string): void {
    const toast = this.add.text(0, 220, message, TEXT_BODY).setOrigin(0, 0);
    toast.setPosition(this.cameras.main.centerX - toast.width / 2, 220);

    this.tweens.add({
      targets: toast,
      alpha: 0,
      duration: 900,
      delay: 800,
      onComplete: () => toast.destroy(),
    });
  }
}






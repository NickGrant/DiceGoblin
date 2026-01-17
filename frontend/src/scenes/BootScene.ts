import Phaser from "phaser";
import { apiClient, type SessionResponse } from "../services/apiClient";
import { TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";
import { RegistryEnergy } from "../state/RegistryEnergy";

export default class BootScene extends Phaser.Scene {
  constructor() {
    super({ key: "BootScene" });
  }

  preload(): void {}

  create(): void {
    const statusText = this.add
      .text(this.cameras.main.centerX, this.cameras.main.centerY, "Loading…", TEXT_BODY)
      .setOrigin(0.5);

    void this.checkSessionAndContinue(statusText);
  }

  private async checkSessionAndContinue(
    statusText: Phaser.GameObjects.Text
  ): Promise<void> {
    try {
      const session = await apiClient.getSession();
      RegistrySession.set(this.registry, {
        isAuthenticated: !!session.data.authenticated,
        user: {
          id: session.data.user?.id || '',
          displayName: session.data.user?.display_name || '',
          avatarUrl: session.data.user?.avatar_url || ''
        },
        csrfToken: session.data.csrf_token
      });

      statusText.setText(!!session.data.authenticated ? "Welcome back…" : "Welcome…");
    } catch {
      RegistrySession.set(this.registry, { isAuthenticated: false });
      statusText.setText("Offline mode (API unavailable)");
    }
    RegistryEnergy.setCurrent(this.registry, 100);
    RegistryEnergy.setMax(this.registry, 100);
    // Small delay so the transition is visible.
    this.time.delayedCall(250, () => {
      this.scene.start("PreloadScene");
    });
  }
}

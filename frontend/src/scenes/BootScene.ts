import Phaser from "phaser";
import { apiClient, type SessionResponse } from "../services/apiClient";
import { TEXT_BODY } from "../const/Text";

type BootResult = {
  authenticated: boolean;
  session: SessionResponse | null;
  offline: boolean;
};

export default class BootScene extends Phaser.Scene {
  constructor() {
    super({ key: "BootScene" });
  }

  preload(): void {
    // Keep this minimal. If you later want a branded loading bar/logo,
    // load those assets here so PreloadScene can use them immediately.
    // Example:
    // this.load.image("loading_bar", "assets/ui/loading_bar.png");
  }

  create(): void {
    const centerX = this.cameras.main.centerX;
    const centerY = this.cameras.main.centerY;

    const statusText = this.add
      .text(centerX, centerY, "Loading…", TEXT_BODY)
      .setOrigin(0.5);

    // Do async work without making create() async (Phaser doesn't "await" it).
    void this.checkSessionAndContinue(statusText);
  }

  private async checkSessionAndContinue(
    statusText: Phaser.GameObjects.Text
  ): Promise<void> {
    let result: BootResult = {
      
      authenticated: false,
      session: null,
      offline: false
    };

    try {
      const session = await apiClient.getSession();
      console.log('API session', session, !!session.data.authenticated);
      result = {
        authenticated: !!session.data.authenticated,
        session,
        offline: false
      };

      statusText.setText(result.authenticated ? "Welcome back…" : "Welcome…");
    } catch {
      // Backend unavailable; proceed in offline mode
      result = {
        authenticated: false,
        session: null,
        offline: true
      };

      statusText.setText("Offline mode (API unavailable)");
    }

    // Small delay so the transition is visible.
    this.time.delayedCall(250, () => {
      // Always go to PreloadScene next so assets are loaded exactly once.
      this.scene.start("PreloadScene", result);
    });
  }
}

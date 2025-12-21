import Phaser from "phaser";
import { apiClient } from "../services/apiClient";

export default class BootScene extends Phaser.Scene {
  constructor() {
    super({ key: "BootScene" });
  }

  preload(): void {
    // Placeholder for future asset loading.
    // this.load.image("logo", "assets/logo.png");
  }

  async create(): Promise<void> {
    const centerX = this.cameras.main.centerX;
    const centerY = this.cameras.main.centerY;

    const statusText = this.add
      .text(centerX, centerY, "Loading…", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "20px",
        color: "#ffffff"
      })
      .setOrigin(0.5);

    let authenticated = false;

    try {
      const session = await apiClient.getSession();
      authenticated = session.authenticated;
      statusText.setText(authenticated ? "Welcome back…" : "Welcome…");
    } catch (err) {
      // If the backend isn't running yet, still let the game proceed.
      statusText.setText("Offline mode (API unavailable)");
      // You can log for debugging:
      // console.error(err);
    }

    // Small delay so the transition is visible.
    this.time.delayedCall(250, () => {
      this.scene.start("LandingScene", { authenticated });
    });
  }
}

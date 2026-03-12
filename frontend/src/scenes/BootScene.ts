import Phaser from "phaser";
import { apiClient } from "../services/apiClient";
import { TEXT_BODY } from "../const/Text";
import { RegistrySession } from "../state/RegistrySession";
import BackgroundImage from "../components/BackgroundImage";
import { markDebugSceneReady, reportDebugError } from "../debug/debugHooks";
import { buildDebugSession, getDebugSceneConfig } from "../debug/debugScene";

export default class BootScene extends Phaser.Scene {
  constructor() {
    super({ key: "BootScene" });
  }

  preload(): void {
    this.load.image("hero_logo", "/assets/hero_logo.png");
    this.load.image("texture_paper", "/assets/ui/textures/paper.png");
  }

  create(): void {
    new BackgroundImage(this);

    const statusText = this.add
      .text(this.cameras.main.centerX, this.cameras.main.centerY, "Loading...", TEXT_BODY)
      .setOrigin(0, 0);
    this.centerText(statusText);

    void this.checkSessionAndContinue(statusText);
  }

  private async checkSessionAndContinue(
    statusText: Phaser.GameObjects.Text
  ): Promise<void> {
    const debugConfig = getDebugSceneConfig();
    if (debugConfig.enabled && debugConfig.skipSessionCheck) {
      const debugSession = buildDebugSession(debugConfig);
      if (debugSession) {
        RegistrySession.set(this.registry, debugSession);
      }

      statusText.setText("Debug bootstrap...");
      this.centerText(statusText);
      markDebugSceneReady(this, { mode: "debug-bootstrap" });
      this.scene.start("PreloadScene");
      return;
    }

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
      reportDebugError("BootScene session request failed; continuing in offline mode.");
    }

    markDebugSceneReady(this, { mode: "session-bootstrap" });
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

}





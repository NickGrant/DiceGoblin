import Phaser from "phaser";
import { GAME_HEIGHT } from "../game/config";
import { API_BASE_URL } from "../services/apiClient";
import { TEXT_BODY, TEXT_HEADER } from "../const/Text";
import BackgroundImage from "../components/BackgroundImage";
import { markDebugSceneReady } from "../debug/debugHooks";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { RegistrySession } from "../state/RegistrySession";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";

const INTRO_COPY =
  "Build a scrappy warband, push through hostile routes, and come home with dice, recruits, and enough teeth to make the next run possible.";
const ACCOUNT_COPY = "Discord login keeps your warband and run progress tied to one account.";
const RETURNING_COPY = "Your account is ready. Head back to camp and choose the next route.";

export default class LandingScene extends Phaser.Scene {
  constructor() {
    super({ key: "LandingScene" });
  }

  create(): void {
    new BackgroundImage(this);

    const layout = getPageLayout(this);
    const introFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height,
      title: "Welcome to Dice Goblins",
      bodyColor: 0x252d33,
    });
    introFrame.setDepth(-600);

    const actionFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: RegistrySession.isAuthed(this.registry) ? "Ready to Return" : "Account Link",
      bodyColor: 0x38434a,
    });
    actionFrame.setDepth(-600);

    const strap = this.add
      .text(layout.content.x + 28, layout.content.y + 92, "TACTICS. LOOT. QUESTIONABLE MANAGEMENT.", {
        ...TEXT_BODY,
        fontSize: "21px",
        color: "#f0d38a",
        strokeThickness: 2,
      })
      .setOrigin(0, 0);

    const title = this.add
      .text(layout.content.x + 28, layout.content.y + 126, "Start small, survive ugly, come back richer.", {
        fontFamily: '"Big Shoulders Stencil Text", "IBM Plex Sans Condensed", Arial',
        fontSize: "42px",
        color: "#f1f4f5",
        stroke: "#121212",
        strokeThickness: 4,
        wordWrap: { width: layout.content.width - 56 },
      })
      .setOrigin(0, 0);

    const intro = this.add
      .text(layout.content.x + 28, layout.content.y + 226, INTRO_COPY, {
        ...TEXT_BODY,
        fontSize: "22px",
        color: "#edf3f4",
        stroke: "#11181d",
        strokeThickness: 2,
        lineSpacing: 10,
        wordWrap: { width: layout.content.width - 70 },
      })
      .setOrigin(0, 0);

    this.renderFeatureCallouts(layout.content.x + 28, layout.content.y + 332, layout.content.width - 56);

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

    const actionTitle = this.add
      .text(layout.buttons.x + 24, layout.buttons.y + 92, RegistrySession.isAuthed(this.registry) ? "Camp Ready" : "Persistent Progress", {
        ...TEXT_HEADER,
        fontSize: "28px",
        color: "#f3efe4",
        wordWrap: { width: layout.buttons.width - 48 },
      })
      .setOrigin(0, 0);

    const actionBody = this.add
      .text(
        layout.buttons.x + 24,
        layout.buttons.y + 146,
        RegistrySession.isAuthed(this.registry) ? RETURNING_COPY : ACCOUNT_COPY,
        {
          ...TEXT_BODY,
          fontSize: "20px",
          color: "#eef3f4",
          stroke: "#11181d",
          strokeThickness: 2,
          lineSpacing: 8,
          wordWrap: { width: layout.buttons.width - 48 },
        }
      )
      .setOrigin(0, 0);

    this.add
      .rectangle(layout.buttons.x + 20, layout.buttons.y + layout.buttons.height - 182, layout.buttons.width - 40, 88, 0x161d22, 0.9)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xcaa860, 0.35);

    this.add
      .text(layout.buttons.x + 36, layout.buttons.y + layout.buttons.height - 168, "FIRST RUN FLOW", {
        ...TEXT_BODY,
        fontSize: "18px",
        color: "#d7c18e",
      })
      .setOrigin(0, 0);

    this.add
      .text(layout.buttons.x + 36, layout.buttons.y + layout.buttons.height - 138, "Farm tutorial -> Mountains -> Swamp", {
        ...TEXT_BODY,
        fontSize: "24px",
        wordWrap: { width: layout.buttons.width - 72 },
      })
      .setOrigin(0, 0);

    const footer = this.add.text(this.cameras.main.centerX, GAME_HEIGHT - 28, "MVP build", TEXT_BODY).setOrigin(0.5, 1);
    markDebugSceneReady(this);
  }

  private createButton(label: string, onClick: () => void): SharedActionButton {
    const layout = getPageLayout(this);
    return new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(0, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + 224,
      label,
      onClick,
    });
  }

  private renderFeatureCallouts(x: number, y: number, width: number): void {
    const gap = 14;
    const cardWidth = Math.floor((width - gap * 2) / 3);
    const cards = [
      {
        title: "Runs",
        body: "Pick a route, survive each node, and decide when to push or cash out.",
      },
      {
        title: "Warband",
        body: "Position units front-to-back, equip dice, and promote recruits into stronger roles.",
      },
      {
        title: "Rewards",
        body: "Bring back teeth, dice, and new bodies to improve the next expedition.",
      },
    ];

    cards.forEach((card, index) => {
      const cardX = x + index * (cardWidth + gap);
      this.add
        .rectangle(cardX, y, cardWidth, 132, 0x11181d, 0.88)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xcaa860, 0.22);
      this.add
        .text(cardX + 18, y + 16, card.title.toUpperCase(), {
          ...TEXT_BODY,
          fontSize: "21px",
          color: "#f0d38a",
        })
        .setOrigin(0, 0);
      this.add
        .text(cardX + 18, y + 48, card.body, {
          ...TEXT_BODY,
          fontSize: "18px",
          color: "#edf3f4",
          stroke: "#11181d",
          strokeThickness: 1,
          lineSpacing: 6,
          wordWrap: { width: cardWidth - 36 },
        })
        .setOrigin(0, 0);
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


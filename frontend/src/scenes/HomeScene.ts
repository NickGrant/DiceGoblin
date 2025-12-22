import Phaser from "phaser";
import { GAME_HEIGHT, GAME_WIDTH } from "../game/config";
import { apiClient, type SessionResponse } from "../services/apiClient";

type HomeData = {
  session?: SessionResponse;
};

type NavTarget = {
  label: string;
  sceneKey: string;
  description: string;
};

export default class HomeScene extends Phaser.Scene {
  private session: SessionResponse | null = null;

  // Basic menu state for keyboard navigation
  private menuButtons: Phaser.GameObjects.Container[] = [];
  private selectedIndex = 0;

  constructor() {
    super({ key: "HomeScene" });
  }

  init(data: HomeData): void {
    this.session = data.session ?? null;
  }

  create(): void {
    // Defensive: BootScene should only route here when authenticated, but don't crash if it doesn't.
    const displayName = this.session?.user?.display_name ?? "Goblin";

    // Background / frame
    this.add.rectangle(GAME_WIDTH / 2, GAME_HEIGHT / 2, GAME_WIDTH, GAME_HEIGHT, 0x0b1020, 1);

    // Header
    this.add
      .text(GAME_WIDTH / 2, 70, "DICE GOBLINS", {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "40px",
        color: "#e8e8ff"
      })
      .setOrigin(0.5);

    this.add
      .text(GAME_WIDTH / 2, 115, `Welcome, ${displayName}`, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "20px",
        color: "#b8b8d8"
      })
      .setOrigin(0.5);

    // Hub actions (scenes can be stubs for now; rename scene keys when you implement them)
    const targets: NavTarget[] = [
      {
        label: "Region Select",
        sceneKey: "RegionSelectScene",
        description: "Choose where to deploy next."
      },
      {
        label: "Squad Management",
        sceneKey: "SquadScene",
        description: "Pick your active squad and roles."
      },
      {
        label: "Unit Management",
        sceneKey: "UnitScene",
        description: "Review units, stats, and upgrades."
      },
      {
        label: "Resource Management",
        sceneKey: "ResourcesScene",
        description: "Spend and allocate resources."
      }
    ];

    // Layout constants
    const gridTopY = 190;
    const gridLeftX = GAME_WIDTH / 2 - 170;
    const colGap = 340;
    const rowGap = 110;

    // Create menu buttons (2x2 grid)
    this.menuButtons = [];
    targets.forEach((t, i) => {
      const col = i % 2;
      const row = Math.floor(i / 2);
      const x = gridLeftX + col * colGap;
      const y = gridTopY + row * rowGap;

      const btn = this.createMenuButton(x, y, t.label, t.description, () => {
        this.tryStartScene(t.sceneKey);
      });

      this.menuButtons.push(btn);
    });

    // Footer actions
    const footerY = GAME_HEIGHT - 90;
    const logoutBtn = this.createButton(GAME_WIDTH / 2 - 160, footerY, "Log out", async () => {
      try {
        await apiClient.logout();
      } finally {
        // simplest reset: reload app and let BootScene decide where to go
        window.location.reload();
      }
    });

    const backBtn = this.createButton(GAME_WIDTH / 2 + 160, footerY, "Back to Landing", () => {
      // This is mostly for dev convenience; BootScene should usually control unauth routing.
      this.scene.start("LandingScene", { authenticated: true });
    });

    // Add footer buttons to menu nav list after the grid
    this.menuButtons.push(logoutBtn, backBtn);

    // Set initial focus and wire keyboard navigation
    this.selectedIndex = 0;
    this.applyFocus();

    this.setupKeyboardNavigation();
  }

  private tryStartScene(sceneKey: string): void {
    // MVP-friendly: if scene doesn't exist yet, show a toast-like message
    const exists = this.scene.get(sceneKey) !== null;
    if (!exists) {
      this.showTransientMessage(`"${sceneKey}" not implemented yet.`);
      return;
    }

    // Pass session forward if you want downstream scenes to have immediate user context
    this.scene.start(sceneKey, { session: this.session });
  }

  private setupKeyboardNavigation(): void {
    const up = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.UP);
    const down = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.DOWN);
    const left = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.LEFT);
    const right = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.RIGHT);
    const enter = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.ENTER);
    const space = this.input.keyboard?.addKey(Phaser.Input.Keyboard.KeyCodes.SPACE);

    const moveFocus = (delta: number) => {
      if (this.menuButtons.length === 0) return;
      this.selectedIndex = (this.selectedIndex + delta + this.menuButtons.length) % this.menuButtons.length;
      this.applyFocus();
    };

    // For a simple hub: treat arrows as sequential navigation (stable even if layout changes)
    up?.on("down", () => moveFocus(-1));
    left?.on("down", () => moveFocus(-1));
    down?.on("down", () => moveFocus(1));
    right?.on("down", () => moveFocus(1));

    const activate = () => {
      const btn = this.menuButtons[this.selectedIndex];
      const handler = (btn as any)._handler as (() => void) | undefined;
      if (handler) handler();
    };

    enter?.on("down", activate);
    space?.on("down", activate);
  }

  private applyFocus(): void {
    this.menuButtons.forEach((btn, i) => {
      const bg = (btn as any)._bg as Phaser.GameObjects.Rectangle | undefined;
      if (!bg) return;

      // Focus ring / highlight
      if (i === this.selectedIndex) {
        bg.setStrokeStyle(3, 0xa7b0ff, 1);
        bg.setFillStyle(0x202848, 1);
      } else {
        bg.setStrokeStyle(2, 0x3a3a66, 1);
        // Only reset fill for menu buttons; footer buttons share the same base styling
        bg.setFillStyle(0x1a1a2a, 1);
      }
    });
  }

  private showTransientMessage(message: string): void {
    const toast = this.add
      .text(GAME_WIDTH / 2, GAME_HEIGHT - 140, message, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "16px",
        color: "#e8e8ff",
        backgroundColor: "rgba(15, 23, 42, 0.8)",
        padding: { left: 12, right: 12, top: 8, bottom: 8 }
      })
      .setOrigin(0.5);

    this.tweens.add({
      targets: toast,
      alpha: 0,
      duration: 900,
      delay: 900,
      onComplete: () => toast.destroy()
    });
  }

  // A more informative “menu button” variant: title + small description
  private createMenuButton(
    x: number,
    y: number,
    title: string,
    description: string,
    onClick: () => void
  ): Phaser.GameObjects.Container {
    const width = 320;
    const height = 84;

    const bg = this.add
      .rectangle(0, 0, width, height, 0x1a1a2a, 1)
      .setStrokeStyle(2, 0x3a3a66, 1);

    const titleText = this.add
      .text(-width / 2 + 16, -18, title, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "20px",
        color: "#ffffff"
      })
      .setOrigin(0, 0.5);

    const descText = this.add
      .text(-width / 2 + 16, 16, description, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "14px",
        color: "#b8b8d8",
        wordWrap: { width: width - 32 }
      })
      .setOrigin(0, 0.5);

    const container = this.add.container(x, y, [bg, titleText, descText]);
    container.setSize(width, height);

    this.makeInteractive(container, bg, onClick);
    (container as any)._bg = bg;
    (container as any)._handler = onClick;

    return container;
  }

  // Your existing button style (slightly refactored to be reusable)
  private createButton(
    x: number,
    y: number,
    label: string,
    onClick: () => void
  ): Phaser.GameObjects.Container {
    const width = 260;
    const height = 52;

    const bg = this.add
      .rectangle(0, 0, width, height, 0x1a1a2a, 1)
      .setStrokeStyle(2, 0x3a3a66, 1);

    const text = this.add
      .text(0, 0, label, {
        fontFamily: "system-ui, -apple-system, Segoe UI, Roboto, Arial",
        fontSize: "18px",
        color: "#ffffff"
      })
      .setOrigin(0.5);

    const container = this.add.container(x, y, [bg, text]);
    container.setSize(width, height);

    this.makeInteractive(container, bg, onClick);
    (container as any)._bg = bg;
    (container as any)._handler = onClick;

    return container;
  }

  private makeInteractive(
    container: Phaser.GameObjects.Container,
    bg: Phaser.GameObjects.Rectangle,
    onClick: () => void
  ): void {
    const width = container.width || 1;
    const height = container.height || 1;

    container.setInteractive(
      new Phaser.Geom.Rectangle(-width / 2, -height / 2, width, height),
      Phaser.Geom.Rectangle.Contains
    );

    container.on("pointerover", () => {
      bg.setFillStyle(0x24243a, 1);
      this.input.setDefaultCursor("pointer");
    });

    container.on("pointerout", () => {
      bg.setFillStyle(0x1a1a2a, 1);
      this.input.setDefaultCursor("default");
    });

    container.on("pointerdown", () => {
      bg.setFillStyle(0x2f2f55, 1);
    });

    container.on("pointerup", () => {
      bg.setFillStyle(0x24243a, 1);
      onClick();
    });
  }
}

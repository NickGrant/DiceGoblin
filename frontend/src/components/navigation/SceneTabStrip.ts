import Phaser from "phaser";

export type SceneTabStripItem<T extends string = string> = {
  id: T;
  label: string;
};

type SceneTabStripConfig<T extends string = string> = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height?: number;
  tabs: SceneTabStripItem<T>[];
  activeId: T;
  onChange?: (id: T) => void;
};

const DEFAULT_HEIGHT = 38;
const TAB_GAP = 10;

export default class SceneTabStrip<T extends string = string> extends Phaser.GameObjects.Container {
  private readonly sceneRef: Phaser.Scene;
  private readonly stripWidth: number;
  private readonly stripHeight: number;
  private readonly tabs: SceneTabStripItem<T>[];
  private readonly onChange?: (id: T) => void;

  private activeId: T;
  private readonly buttons = new Map<T, { bg: Phaser.GameObjects.Rectangle; label: Phaser.GameObjects.Text }>();

  constructor(config: SceneTabStripConfig<T>) {
    super(config.scene, config.x, config.y);

    this.sceneRef = config.scene;
    this.stripWidth = config.width;
    this.stripHeight = config.height ?? DEFAULT_HEIGHT;
    this.tabs = config.tabs;
    this.activeId = config.activeId;
    this.onChange = config.onChange;

    this.build();
    config.scene.add.existing(this);
  }

  public setActiveId(id: T): this {
    this.activeId = id;
    this.refresh();
    return this;
  }

  private build(): void {
    const tabCount = Math.max(1, this.tabs.length);
    const tabWidth = Math.max(96, Math.floor((this.stripWidth - TAB_GAP * (tabCount - 1)) / tabCount));

    this.tabs.forEach((tab, index) => {
      const tabX = index * (tabWidth + TAB_GAP);
      const bg = this.sceneRef.add
        .rectangle(tabX, 0, tabWidth, this.stripHeight, 0x102125, 0.82)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0x8db8bc, 0.35)
        .setInteractive({ useHandCursor: true });
      const label = this.sceneRef.add
        .text(tabX + tabWidth / 2, this.stripHeight / 2, tab.label.toUpperCase(), {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "18px",
          color: "#dce7e8",
          align: "center",
        })
        .setOrigin(0.5, 0.5);

      bg.on("pointerdown", () => {
        if (this.activeId === tab.id) {
          return;
        }
        this.activeId = tab.id;
        this.refresh();
        this.onChange?.(tab.id);
      });

      bg.on("pointerover", () => {
        if (this.activeId !== tab.id) {
          bg.setFillStyle(0x163038, 0.92);
        }
      });
      bg.on("pointerout", () => {
        if (this.activeId !== tab.id) {
          bg.setFillStyle(0x102125, 0.82);
        }
      });

      this.buttons.set(tab.id, { bg, label });
      this.add([bg, label]);
    });

    this.refresh();
  }

  private refresh(): void {
    this.tabs.forEach((tab) => {
      const button = this.buttons.get(tab.id);
      if (!button) {
        return;
      }

      const selected = tab.id === this.activeId;
      button.bg
        .setFillStyle(selected ? 0xf0d38a : 0x102125, selected ? 0.95 : 0.82)
        .setStrokeStyle(2, selected ? 0x7a5f39 : 0x8db8bc, selected ? 0.9 : 0.35);
      button.label.setColor(selected ? "#3e2b16" : "#dce7e8");
    });
  }
}

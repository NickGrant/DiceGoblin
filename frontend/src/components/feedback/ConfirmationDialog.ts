import Phaser from "phaser";
import AcceptButton from "../clickable-panel/AcceptButton";
import RejectButton from "../clickable-panel/RejectButton";
import { TEXT_OVERLAY_BODY, TEXT_OVERLAY_TITLE } from "../../const/Text";

export type ConfirmationDialogConfig = {
  scene: Phaser.Scene;
  title: string;
  message: string;
  acceptLabel?: string;
  rejectLabel?: string;
  onAccept: () => void | Promise<void>;
  onAcceptInput?: (value: string) => void | Promise<void>;
  onReject: () => void;
  width?: number;
  height?: number;
  input?: {
    initialValue?: string;
    placeholder?: string;
    maxLength?: number;
    allowedCharacterPattern?: RegExp;
  };
};

export default class ConfirmationDialog extends Phaser.GameObjects.Container {
  private readonly overlay: Phaser.GameObjects.Rectangle;
  private readonly panel: Phaser.GameObjects.Rectangle;
  private readonly titleText: Phaser.GameObjects.Text;
  private readonly messageText: Phaser.GameObjects.Text;
  private readonly acceptButton: AcceptButton;
  private readonly rejectButton: RejectButton;
  private readonly inputBackground?: Phaser.GameObjects.Rectangle;
  private readonly inputValueText?: Phaser.GameObjects.Text;
  private readonly inputHintText?: Phaser.GameObjects.Text;
  private inputValue = "";
  private inputCaretIndex = 0;
  private keydownHandler?: (event: KeyboardEvent) => void;
  private readonly sceneRef: Phaser.Scene;

  constructor(cfg: ConfirmationDialogConfig) {
    super(cfg.scene, 0, 0);
    this.sceneRef = cfg.scene;

    const width = cfg.width ?? 520;
    const height = cfg.height ?? 280;
    const hasInput = !!cfg.input;
    const centerX = cfg.scene.scale.width / 2;
    const centerY = cfg.scene.scale.height / 2;
    const left = centerX - width / 2;
    const top = centerY - height / 2;

    this.overlay = cfg.scene.add
      .rectangle(0, 0, cfg.scene.scale.width, cfg.scene.scale.height, 0x000000, 0.55)
      .setOrigin(0, 0)
      .setInteractive();
    this.panel = cfg.scene.add
      .rectangle(left, top, width, height, 0x1d1d1d, 0.98)
      .setOrigin(0, 0)
      .setStrokeStyle(2, 0xffffff, 0.25);

    this.titleText = cfg.scene.add.text(left + 20, top + 18, cfg.title, {
      ...TEXT_OVERLAY_TITLE,
    }).setOrigin(0, 0);

    this.messageText = cfg.scene.add.text(left + 20, top + 66, cfg.message, {
      ...TEXT_OVERLAY_BODY,
      wordWrap: { width: width - 40 },
    }).setOrigin(0, 0);

    if (hasInput) {
      const inputTop = top + 128;
      const inputHeight = 44;
      const inputWidth = width - 40;
      const maxLength = Math.max(1, cfg.input?.maxLength ?? 24);
      const allowedPattern = cfg.input?.allowedCharacterPattern;
      const initialRaw = String(cfg.input?.initialValue ?? "").slice(0, maxLength);
      this.inputValue = allowedPattern
        ? Array.from(initialRaw).filter((char) => allowedPattern.test(char)).join("")
        : initialRaw;
      this.inputCaretIndex = this.inputValue.length;

      this.inputBackground = cfg.scene.add
        .rectangle(left + 20, inputTop, inputWidth, inputHeight, 0x0a0a0a, 0.92)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xa0a0a0, 0.45);

      this.inputValueText = cfg.scene.add.text(left + 32, inputTop + 9, this.inputValue, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "24px",
        color: "#f5f5f5",
      }).setOrigin(0, 0);

      this.inputHintText = cfg.scene.add.text(left + 32, inputTop + 11, cfg.input?.placeholder ?? "Enter name...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "20px",
        color: "#8e8e8e",
      }).setOrigin(0, 0);

      this.syncInputText();

      this.keydownHandler = (event: KeyboardEvent) => {
        if (!this.active) return;

        if (event.key === "Enter") {
          event.preventDefault();
          void this.handleAccept(cfg);
          return;
        }
        if (event.key === "Escape") {
          event.preventDefault();
          this.handleReject(cfg);
          return;
        }
        if (event.key === "Backspace") {
          event.preventDefault();
          if (this.inputCaretIndex <= 0) return;
          this.inputValue =
            this.inputValue.slice(0, this.inputCaretIndex - 1) +
            this.inputValue.slice(this.inputCaretIndex);
          this.inputCaretIndex -= 1;
          this.syncInputText();
          return;
        }
        if (event.key === "Delete") {
          event.preventDefault();
          if (this.inputCaretIndex >= this.inputValue.length) return;
          this.inputValue =
            this.inputValue.slice(0, this.inputCaretIndex) +
            this.inputValue.slice(this.inputCaretIndex + 1);
          this.syncInputText();
          return;
        }
        if (event.key === "ArrowLeft") {
          event.preventDefault();
          this.inputCaretIndex = Math.max(0, this.inputCaretIndex - 1);
          this.syncInputText();
          return;
        }
        if (event.key === "ArrowRight") {
          event.preventDefault();
          this.inputCaretIndex = Math.min(this.inputValue.length, this.inputCaretIndex + 1);
          this.syncInputText();
          return;
        }
        if (event.key === "Home") {
          event.preventDefault();
          this.inputCaretIndex = 0;
          this.syncInputText();
          return;
        }
        if (event.key === "End") {
          event.preventDefault();
          this.inputCaretIndex = this.inputValue.length;
          this.syncInputText();
          return;
        }
        if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
          if (this.inputValue.length >= maxLength) return;
          if (allowedPattern && !allowedPattern.test(event.key)) return;
          this.inputValue =
            this.inputValue.slice(0, this.inputCaretIndex) +
            event.key +
            this.inputValue.slice(this.inputCaretIndex);
          this.inputCaretIndex += 1;
          this.syncInputText();
        }
      };

      cfg.scene.input.keyboard?.on("keydown", this.keydownHandler);
    }

    const buttonWidth = 280;
    const buttonGap = 16;
    const sideBySide = width >= buttonWidth * 2 + buttonGap + 40;
    const rowY = top + height - 95;
    const stackedTopY = top + height - 164;
    const stackedBottomY = top + height - 86;
    const leftButtonX = sideBySide
      ? left + 20
      : left + Math.max(0, Math.floor((width - buttonWidth) / 2));
    const rightButtonX = sideBySide
      ? left + width - buttonWidth - 20
      : leftButtonX;

    this.acceptButton = new AcceptButton({
      scene: cfg.scene,
      x: leftButtonX,
      y: sideBySide ? rowY : stackedTopY,
      label: cfg.acceptLabel ?? "Accept",
      onClick: () => {
        void this.handleAccept(cfg);
      },
    });
    this.rejectButton = new RejectButton({
      scene: cfg.scene,
      x: rightButtonX,
      y: sideBySide ? rowY : stackedBottomY,
      label: cfg.rejectLabel ?? "Cancel",
      onClick: () => {
        this.handleReject(cfg);
      },
    });

    this.add([
      this.overlay,
      this.panel,
      this.titleText,
      this.messageText,
      ...(this.inputBackground ? [this.inputBackground] : []),
      ...(this.inputValueText ? [this.inputValueText] : []),
      ...(this.inputHintText ? [this.inputHintText] : []),
      this.acceptButton,
      this.rejectButton,
    ]);
    cfg.scene.add.existing(this);
    this.setDepth(1200);
  }

  close(): void {
    if (this.keydownHandler) {
      this.sceneRef.input.keyboard?.off("keydown", this.keydownHandler);
      this.keydownHandler = undefined;
    }
    this.destroy();
  }

  private async handleAccept(cfg: ConfirmationDialogConfig): Promise<void> {
    if (cfg.onAcceptInput) {
      await cfg.onAcceptInput(this.inputValue);
    }
    await cfg.onAccept();
  }

  private handleReject(cfg: ConfirmationDialogConfig): void {
    cfg.onReject();
    this.close();
  }

  private syncInputText(): void {
    const clampedCaret = Math.max(0, Math.min(this.inputCaretIndex, this.inputValue.length));
    this.inputCaretIndex = clampedCaret;
    const withCaret = `${this.inputValue.slice(0, clampedCaret)}|${this.inputValue.slice(clampedCaret)}`;
    this.inputValueText?.setText(withCaret);
    this.inputHintText?.setVisible(false);
  }
}


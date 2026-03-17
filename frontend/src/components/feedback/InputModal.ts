import Phaser from "phaser";
import ConfirmModal, { type ConfirmModalConfig } from "./ConfirmModal";

export type InputModalConfig = ConfirmModalConfig & {
  onAcceptInput?: (value: string) => void | Promise<void>;
  input?: {
    initialValue?: string;
    placeholder?: string;
    maxLength?: number;
    allowedCharacterPattern?: RegExp;
  };
};

export default class InputModal extends ConfirmModal {
  private readonly inputBackground?: Phaser.GameObjects.Rectangle;
  private readonly inputValueText?: Phaser.GameObjects.Text;
  private readonly inputHintText?: Phaser.GameObjects.Text;
  private inputValue = "";
  private inputCaretIndex = 0;
  private keydownHandler?: (event: KeyboardEvent) => void;
  private readonly inputCfg: InputModalConfig;

  constructor(cfg: InputModalConfig) {
    super(cfg);
    this.inputCfg = cfg;

    if (!cfg.input) {
      return;
    }

    const inputTop = this.topPx + 128;
    const inputHeight = 44;
    const inputWidth = this.widthPx - 40;
    const maxLength = Math.max(1, cfg.input.maxLength ?? 24);
    const allowedPattern = cfg.input.allowedCharacterPattern;
    const initialRaw = String(cfg.input.initialValue ?? "").slice(0, maxLength);
    this.inputValue = allowedPattern
      ? Array.from(initialRaw).filter((char) => allowedPattern.test(char)).join("")
      : initialRaw;
    this.inputCaretIndex = this.inputValue.length;

    this.inputBackground = this.sceneRef.add
      .rectangle(this.leftPx + 20, inputTop, inputWidth, inputHeight, 0x0a0a0a, 0.92)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xa0a0a0, 0.45);

    this.inputValueText = this.sceneRef.add.text(this.leftPx + 32, inputTop + 9, this.inputValue, {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "24px",
      color: "#f5f5f5",
    }).setOrigin(0, 0);

    this.inputHintText = this.sceneRef.add.text(this.leftPx + 32, inputTop + 11, cfg.input.placeholder ?? "Enter name...", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "20px",
      color: "#8e8e8e",
    }).setOrigin(0, 0);

    this.add([
      this.inputBackground,
      this.inputValueText,
      this.inputHintText,
    ]);

    this.syncInputText();

    this.keydownHandler = (event: KeyboardEvent) => {
      if (!this.active) return;
      const input = this.inputCfg.input;
      if (!input) return;

      const max = Math.max(1, input.maxLength ?? 24);
      const allowedPatternInner = input.allowedCharacterPattern;

      if (event.key === "Enter") {
        event.preventDefault();
        void this.handleAccept();
        return;
      }
      if (event.key === "Escape") {
        event.preventDefault();
        this.handleReject();
        return;
      }
      if (event.key === "Backspace") {
        event.preventDefault();
        if (this.inputCaretIndex <= 0) return;
        this.inputValue = this.inputValue.slice(0, this.inputCaretIndex - 1) + this.inputValue.slice(this.inputCaretIndex);
        this.inputCaretIndex -= 1;
        this.syncInputText();
        return;
      }
      if (event.key === "Delete") {
        event.preventDefault();
        if (this.inputCaretIndex >= this.inputValue.length) return;
        this.inputValue = this.inputValue.slice(0, this.inputCaretIndex) + this.inputValue.slice(this.inputCaretIndex + 1);
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
        if (this.inputValue.length >= max) return;
        if (allowedPatternInner && !allowedPatternInner.test(event.key)) return;
        this.inputValue = this.inputValue.slice(0, this.inputCaretIndex) + event.key + this.inputValue.slice(this.inputCaretIndex);
        this.inputCaretIndex += 1;
        this.syncInputText();
      }
    };

    this.sceneRef.input.keyboard?.on("keydown", this.keydownHandler);
  }

  override close(): void {
    if (this.keydownHandler) {
      this.sceneRef.input.keyboard?.off("keydown", this.keydownHandler);
      this.keydownHandler = undefined;
    }
    super.close();
  }

  protected override async beforeAccept(): Promise<void> {
    if (this.inputCfg.onAcceptInput) {
      await this.inputCfg.onAcceptInput(this.inputValue);
    }
  }

  private syncInputText(): void {
    const clampedCaret = Math.max(0, Math.min(this.inputCaretIndex, this.inputValue.length));
    this.inputCaretIndex = clampedCaret;
    const withCaret = `${this.inputValue.slice(0, clampedCaret)}|${this.inputValue.slice(clampedCaret)}`;
    this.inputValueText?.setText(withCaret);
    this.inputHintText?.setVisible(false);
  }
}

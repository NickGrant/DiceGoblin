import SharedActionButton from "../clickable-panel/SharedActionButton";
import BaseModal, { type BaseModalConfig } from "./BaseModal";

export type ConfirmModalConfig = BaseModalConfig & {
  acceptLabel?: string;
  rejectLabel?: string;
  onAccept: () => void | Promise<void>;
  onReject: () => void;
};

export default class ConfirmModal extends BaseModal {
  protected readonly acceptButton: SharedActionButton;
  protected readonly rejectButton: SharedActionButton;
  protected readonly cfg: ConfirmModalConfig;

  constructor(cfg: ConfirmModalConfig) {
    super(cfg);
    this.cfg = cfg;

    const buttonMetrics = SharedActionButton.getVariantMetrics("compact");
    const layout = this.getButtonLayout(buttonMetrics.width, 16);
    this.acceptButton = new SharedActionButton({
      scene: this.sceneRef,
      x: layout.leftButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedTopY,
      label: cfg.acceptLabel ?? "Accept",
      variant: "compact",
      textStyle: {
        color: "#1f2f1f",
        stroke: "rgba(206,255,206,0.55)",
      },
      onClick: () => {
        void this.handleAccept();
      },
    });
    this.rejectButton = new SharedActionButton({
      scene: this.sceneRef,
      x: layout.rightButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedBottomY,
      label: cfg.rejectLabel ?? "Cancel",
      variant: "compact",
      textStyle: {
        color: "#3b1f1f",
        stroke: "rgba(255,206,206,0.65)",
      },
      onClick: () => {
        this.handleReject();
      },
    });

    this.add([this.acceptButton, this.rejectButton]);
  }

  protected async beforeAccept(): Promise<void> {
    // Hook for subclasses.
  }

  protected async handleAccept(): Promise<void> {
    await this.beforeAccept();
    await this.cfg.onAccept();
  }

  protected handleReject(): void {
    this.cfg.onReject();
    this.close();
  }
}

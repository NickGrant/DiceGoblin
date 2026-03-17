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

    const layout = this.getButtonLayout();
    this.acceptButton = new SharedActionButton({
      scene: this.sceneRef,
      x: layout.leftButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedTopY,
      label: cfg.acceptLabel ?? "Accept",
      variant: "accept",
      onClick: () => {
        void this.handleAccept();
      },
    });
    this.rejectButton = new SharedActionButton({
      scene: this.sceneRef,
      x: layout.rightButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedBottomY,
      label: cfg.rejectLabel ?? "Cancel",
      variant: "reject",
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

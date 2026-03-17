import AcceptButton from "../clickable-panel/AcceptButton";
import RejectButton from "../clickable-panel/RejectButton";
import BaseModal, { type BaseModalConfig } from "./BaseModal";

export type ConfirmModalConfig = BaseModalConfig & {
  acceptLabel?: string;
  rejectLabel?: string;
  onAccept: () => void | Promise<void>;
  onReject: () => void;
};

export default class ConfirmModal extends BaseModal {
  protected readonly acceptButton: AcceptButton;
  protected readonly rejectButton: RejectButton;
  protected readonly cfg: ConfirmModalConfig;

  constructor(cfg: ConfirmModalConfig) {
    super(cfg);
    this.cfg = cfg;

    const layout = this.getButtonLayout();
    this.acceptButton = new AcceptButton({
      scene: this.sceneRef,
      x: layout.leftButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedTopY,
      label: cfg.acceptLabel ?? "Accept",
      onClick: () => {
        void this.handleAccept();
      },
    });
    this.rejectButton = new RejectButton({
      scene: this.sceneRef,
      x: layout.rightButtonX,
      y: layout.sideBySide ? layout.rowY : layout.stackedBottomY,
      label: cfg.rejectLabel ?? "Cancel",
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

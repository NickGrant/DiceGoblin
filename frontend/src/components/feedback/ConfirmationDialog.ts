import InputModal, { type InputModalConfig } from "./InputModal";

export type ConfirmationDialogConfig = InputModalConfig;

// Backward-compatible alias while call sites migrate to ConfirmModal/InputModal.
export default class ConfirmationDialog extends InputModal {
  constructor(cfg: ConfirmationDialogConfig) {
    super(cfg);
  }
}

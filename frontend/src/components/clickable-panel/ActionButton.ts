import SharedActionButton from "./SharedActionButton";

type ActionButtonConfig = ConstructorParameters<typeof SharedActionButton>[0];

export default class ActionButton extends SharedActionButton {
  constructor(cfg: ActionButtonConfig) {
    super({
      ...cfg,
      variant: "default",
    });
  }
}

import SharedActionButton from "./SharedActionButton";

type MetalActionButtonConfig = ConstructorParameters<typeof SharedActionButton>[0];

export default class MetalActionButton extends SharedActionButton {
  constructor(cfg: MetalActionButtonConfig) {
    super({
      ...cfg,
      variant: "metal",
    });
  }
}

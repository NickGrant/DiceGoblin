import SharedActionButton from "./SharedActionButton";

type AcceptButtonConfig = Omit<ConstructorParameters<typeof SharedActionButton>[0], "textStyle" | "variant">;

export default class AcceptButton extends SharedActionButton {
  constructor(cfg: AcceptButtonConfig) {
    super({
      ...cfg,
      variant: "accept",
    });
  }
}

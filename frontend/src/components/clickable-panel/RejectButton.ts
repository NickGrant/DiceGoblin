import SharedActionButton from "./SharedActionButton";

type RejectButtonConfig = Omit<ConstructorParameters<typeof SharedActionButton>[0], "textStyle" | "variant">;

export default class RejectButton extends SharedActionButton {
  constructor(cfg: RejectButtonConfig) {
    super({
      ...cfg,
      variant: "reject",
    });
  }
}

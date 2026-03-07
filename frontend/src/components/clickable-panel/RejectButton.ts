import ActionButton from "./ActionButton";

type RejectButtonConfig = Omit<ConstructorParameters<typeof ActionButton>[0], "textStyle">;

export default class RejectButton extends ActionButton {
  constructor(cfg: RejectButtonConfig) {
    super({
      ...cfg,
      textStyle: {
        color: "#3b1f1f",
        stroke: "rgba(255,206,206,0.65)",
      },
    });
  }
}

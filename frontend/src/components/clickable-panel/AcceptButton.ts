import ActionButton from "./ActionButton";

type AcceptButtonConfig = Omit<ConstructorParameters<typeof ActionButton>[0], "textStyle">;

export default class AcceptButton extends ActionButton {
  constructor(cfg: AcceptButtonConfig) {
    super({
      ...cfg,
      textStyle: {
        color: "#1f2f1f",
        stroke: "rgba(206,255,206,0.55)",
      },
    });
  }
}

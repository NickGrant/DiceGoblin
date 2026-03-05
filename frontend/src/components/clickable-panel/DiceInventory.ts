import ActionButton from "./ActionButton";
import { type ClickablePanelConfig } from "./ClickablePanel";

export default class DiceInventory extends ActionButton {
    constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
        super({
            scene,
            ...cfg,
            targetSceneKey: 'DiceInventoryScene',
            label: "Inventory",
            iconKey: "icon_inventory",
        })
    }
}

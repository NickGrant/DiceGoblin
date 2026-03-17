import SharedActionButton from "./SharedActionButton";
import { type ClickablePanelConfig } from "./ClickablePanel";

export default class DiceInventory extends SharedActionButton {
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

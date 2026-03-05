import ActionButton from "./ActionButton";
import { type ClickablePanelConfig } from "./ClickablePanel";

export default class WarbandManagement extends ActionButton {
    constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
        super({
            scene,
            ...cfg,
            targetSceneKey: 'WarbandManagementScene',
            label: "Warband",
            iconKey: "icon_warband",
        })
    }
}

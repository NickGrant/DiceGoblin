import SharedActionButton from "./SharedActionButton";
import { type ClickablePanelConfig } from "./ClickablePanel";

export default class WarbandManagement extends SharedActionButton {
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

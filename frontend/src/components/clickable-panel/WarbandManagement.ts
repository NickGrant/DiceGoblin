import { TEXT_BUTTON } from "../../const/Text";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

export default class WarbandManagement extends ClickablePanel{
    constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
        super(scene, {
            ...cfg,
            targetSceneKey: 'WarbandManagementScene',
            textureKey: 'manifest_strip',
            width: 343,
            height: 75
        })
    }

    override addOverlay(): void {
        const label = this.scene.add.text(74, 0, "WARBAND", { ...TEXT_BUTTON, ...{wordWrap: {width: 343}}}).setOrigin(1, 0.5);
        const icon = this.scene.add.image(80, 0 ,'icon_skull').setScale(.5, .5).setOrigin(0, 0.5);
        this.add(label);
        this.add(icon);
    }
}
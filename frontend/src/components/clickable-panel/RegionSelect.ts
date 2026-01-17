import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

export default class RegionSelect extends ClickablePanel {
    constructor (scene: Phaser.Scene, cfg: ClickablePanelConfig) {
        super(scene, {
            ...cfg,
            textureKey: 'panel_begin_run',
            targetSceneKey: 'RegionSelectScene',
            width: 559,
            height: 469
        })
    }
}
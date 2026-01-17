import ClickablePanel, { type ClickablePanelConfig } from "./clickable-panel/ClickablePanel";

export default class HomeButton extends ClickablePanel {
    constructor(stage: Phaser.Stage, cfg: ClickablePanelConfig) {
        super(stage, {
            ...cfg,
            targetSceneKey: 'HomeScene',
            textureKey: 'icon_home',
            width: 128,
            height: 128
        })
    }
}
import ClickablePanel, { type ClickablePanelConfig } from "./clickable-panel/ClickablePanel";

export default class HomeButton extends ClickablePanel {
    constructor(scene: Phaser.Scene, cfg: ClickablePanelConfig) {
        super(scene, {
            ...cfg,
            targetSceneKey: 'HomeScene',
            textureKey: 'icon_home',
            width: 100,
            height: 100
        })
    }
}

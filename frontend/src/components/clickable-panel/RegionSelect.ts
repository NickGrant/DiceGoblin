import { apiClient } from "../../services/apiClient";
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

        apiClient.getProfile().then((profile) => {
            if (profile.data.active_run) {
                this.updateImage('panel_continue_run');
                this.targetSceneKey = 'MapExplorationScene'
                this.dataToPass = {run_id: profile.data.active_run.run_id}
            }
        })
    }
}
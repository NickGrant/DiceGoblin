import { TEXT_HEADER, UI_COLORS } from "../../const/Text";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

export default class ClickablePanelRegionColumn extends ClickablePanel {
    constructor(stage: Phaser.Stage, cfg: ClickablePanelConfig, biome: "mountain" | "swamp") {
        const dims = biome === 'mountain' ? {width: 1013, height: 1420} : {width: 1016,height: 1382}
        super(stage, {
            ...cfg,
            targetSceneKey: 'MapExplorationScene',
            textureKey: `column_${biome}`,
            width: dims.width,
            height: dims.height,
            dataToPass: {biome: biome}
        })
    }

    override addOverlay(): void {
        const _string = this.dataToPass?.biome === 'mountain' ? 'KOBOLDS' : 'FROGMEN'
        const _style = {
            ...TEXT_HEADER,
            fontSize: "128px",
            color: UI_COLORS.parchment
        }
        const text = this.scene.add.text(0, 300, _string, _style).setOrigin(.5, .5);
        this.add(text);
    }
}
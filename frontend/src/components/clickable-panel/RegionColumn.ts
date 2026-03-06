import { TEXT_HEADER, UI_COLORS } from "../../const/Text";
import { apiClient } from "../../services/apiClient";
import ClickablePanel, { type ClickablePanelConfig } from "./ClickablePanel";

export default class ClickablePanelRegionColumn extends ClickablePanel {
    biome: string;
    private feedbackText?: Phaser.GameObjects.Text;


    constructor(stage: Phaser.Scene, cfg: ClickablePanelConfig, biome: "mountain" | "swamp") {
        const dims = biome === 'mountain' ? {width: 1013, height: 1420} : {width: 1016,height: 1382}
        super(stage, {
            ...cfg,
            targetSceneKey: 'MapExplorationScene',
            textureKey: `column_${biome}`,
            width: dims.width,
            height: dims.height,
            dataToPass: {biome: biome},
        });
        this.biome = biome;
    }

    override handleClick(scene: Phaser.Scene): void {
        void (async () => {
            try {
                const res = await apiClient.createRun(this.biome);
                if (!res.ok) {
                    this.showFeedback(scene, `Cannot start run: ${res.error.message}`, "#ffb3b3");
                    return;
                }
                scene.scene.start(this.targetSceneKey, this.dataToPass);
            } catch {
                this.showFeedback(scene, "Cannot start run right now. Please retry.", "#ffb3b3");
            }
        })();
    }

    override addOverlay(): void {
        const _string = this.dataToPass?.biome === 'mountain' ? 'KOBOLDS' : 'FROGMEN'
        const dims = this.dataToPass?.biome === 'mountain'
          ? { width: 1013, height: 1420 }
          : { width: 1016, height: 1382 };
        const _style = {
            ...TEXT_HEADER,
            fontSize: "128px",
            color: UI_COLORS.parchment
        }
        const text = this.scene.add.text(0, 300, _string, _style).setOrigin(0, 0);
        text.setPosition((dims.width - text.width) / 2, 300);
        this.add(text);
    }

    private showFeedback(scene: Phaser.Scene, message: string, color: string): void {
        this.feedbackText?.destroy();
        this.feedbackText = scene.add.text(40, 40, message, {
            fontFamily: "Arial",
            fontSize: "18px",
            color,
            backgroundColor: "rgba(0,0,0,0.55)",
            padding: { left: 8, right: 8, top: 6, bottom: 6 },
            wordWrap: { width: Math.max(280, scene.scale.width - 80) },
        }).setOrigin(0, 0).setDepth(1000);
        scene.time.delayedCall(2400, () => {
            this.feedbackText?.destroy();
            this.feedbackText = undefined;
        });
    }
}

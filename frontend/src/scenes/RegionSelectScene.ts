import BackgroundImage from "../components/BackgroundImage";
import ClickablePanelRegionColumn from "../components/clickable-panel/RegionColumn";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import { drawUxDualZones } from "../components/UxZonePanels";
import { getPageLayout } from "../layout/pageLayout";


export default class RegionSelectScene extends Phaser.Scene {

  constructor() {
    super({ key: "RegionSelectScene" });
  }

  create(): void {
    new BackgroundImage(this);
    new HudPanel(this);
    const layout = getPageLayout(this);
    drawUxDualZones(this, {
      leftTitle: "Choose Region",
      rightTitle: "Region Intel",
      leftColor: 0x0600ff,
      rightColor: 0x00f6ff,
    });
    new HomeButton(this, {
      x: layout.homeIcon.x,
      y: layout.homeIcon.y,
    });

    const leftColumnX = layout.content.x + 70;
    const rightColumnX = layout.content.x + 380;
    const columnY = layout.content.y + 168;
    new ClickablePanelRegionColumn(this, { x: leftColumnX, y: columnY }, 'mountain').setScale(.3);
    new ClickablePanelRegionColumn(this, { x: rightColumnX, y: columnY }, 'swamp').setScale(.3);
  }
}


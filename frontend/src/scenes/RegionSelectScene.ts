import BackgroundImage from "../components/BackgroundImage";
import ClickablePanelRegionColumn from "../components/clickable-panel/RegionColumn";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";


export default class RegionSelectScene extends Phaser.Scene {

  constructor() {
    super({ key: "RegionSelectScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_concrete');
    new HudPanel(this, 100, 100);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
    new ClickablePanelRegionColumn(this, {x: 179, y: 305 }, 'mountain').setScale(.3);
    new ClickablePanelRegionColumn(this, {x: 488, y: 299 }, 'swamp').setScale(.3);
  }
}
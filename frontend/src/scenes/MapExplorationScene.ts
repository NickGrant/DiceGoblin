import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";


export default class MapExplorationScene extends Phaser.Scene {

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_desk');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
  }
}
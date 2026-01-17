import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";


export default class DiceInventoryScene extends Phaser.Scene {

  constructor() {
    super({ key: "DiceInventoryScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_workbench');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
  }
}
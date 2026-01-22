import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import { apiClient } from "../services/apiClient";
import type { RunResponse } from "../types/ApiResponse";

export default class MapExplorationScene extends Phaser.Scene {
  _run: RunResponse | null = null;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  async preload () {
    await apiClient.getCurrentRun().then((run) => {
      this._run = run;
      console.log('ran', run);
    })
  }

  create(): void {
    new BackgroundImage(this, 'background_desk');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
    console.log('running', this._run);
  }
}
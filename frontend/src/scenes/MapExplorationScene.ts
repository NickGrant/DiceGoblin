import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import NodeList from "../components/encounter-map/NodeList";
import { apiClient } from "../services/apiClient";
import type { RunResponse } from "../types/ApiResponse";

export default class MapExplorationScene extends Phaser.Scene {
  _run: RunResponse | null = null;

  constructor() {
    super({ key: "MapExplorationScene" });
  }

  create(): void {
    new BackgroundImage(this, 'background_desk');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);
    apiClient.getCurrentRun().then((run) => {
      this._run = run;
      new NodeList(this, 0, 0, this._run?.data.run, this._run?.data.map.nodes);
    })
  }
}
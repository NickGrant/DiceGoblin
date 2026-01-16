import BackgroundImage from "../components/BackgroundImage";
import HudPanel from "../components/HudPanel";
import type { SessionResponse } from "../services/apiClient";

type RegionSelectData = {
  session?: SessionResponse;
};

export default class RegionSelectScene extends Phaser.Scene {
  private session: SessionResponse | null = null;

  constructor() {
    super({ key: "RegionSelectScene" });
  }

  init(data: RegionSelectData): void {
    this.session = data.session ?? null;
  }

  create(): void {
    const displayName = this.session?.data.user?.display_name ?? "Goblin";
    new BackgroundImage(this, 'background_concrete');
    new HudPanel(this, displayName, 100, 100);
  }
}
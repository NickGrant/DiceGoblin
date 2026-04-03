import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import RegionSelectionPanel from "../components/navigation/RegionSelectionPanel";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { resolveContentFrameBodyRect } from "../components/layout/contentAreaMath";
import { apiClient } from "../services/apiClient";

type RegionId = "farm" | "mountain" | "swamp";

type RegionDefinition = {
  id: RegionId;
  label: string;
  textureKey: string;
  regionDbId: number;
  energyCost: number;
  routeLabel: string;
  intelTitle: string;
  intelDescription: string;
};

const REGION_DEFINITIONS: RegionDefinition[] = [
  {
    id: "farm",
    label: "The Farm",
    textureKey: "region_farm_badge",
    regionDbId: 3,
    energyCost: 3,
    routeLabel: "Tutorial Route | 5 Nodes",
    intelTitle: "THE FARM",
    intelDescription: "Short guided route with pig-only encounters. Best place to learn formation, rewards, and rest flow.",
  },
  {
    id: "mountain",
    label: "Mountains",
    textureKey: "region_mountain_badge",
    regionDbId: 1,
    energyCost: 5,
    routeLabel: "Early Route | Standard Length",
    intelTitle: "MOUNTAINS",
    intelDescription: "Steady enemy pacing with dependable rewards. Recommended baseline route for early runs.",
  },
  {
    id: "swamp",
    label: "Swamp",
    textureKey: "region_swamp_badge",
    regionDbId: 2,
    energyCost: 5,
    routeLabel: "Advanced Route | High Attrition",
    intelTitle: "SWAMP",
    intelDescription: "Higher-risk encounters and unstable attrition. Best attempted after core warband upgrades.",
  },
];

export default class RegionSelectScene extends Phaser.Scene {
  private static readonly FRAME_MARGIN = 10;
  private static readonly REGION_TILE_SIZE = 288;
  private static readonly REGION_TILE_GAP = 10;
  private static readonly FRAME_TITLE_HEIGHT = 56;
  private static readonly INNER_PADDING = 10;
  private static readonly COLUMN_BOTTOM_EXTENSION = 10;
  private selectedRegionId: RegionId = "farm";
  private unlockedRegions = new Set<RegionId>(["farm"]);
  private regionPanels = new Map<RegionId, RegionSelectionPanel>();
  private intelTitleText?: Phaser.GameObjects.Text;
  private intelBodyText?: Phaser.GameObjects.Text;
  private intelEnergyIcon?: Phaser.GameObjects.Image;
  private intelEnergyText?: Phaser.GameObjects.Text;
  private startRunButton?: SharedActionButton;
  private currentEnergy: { current: number; max: number } | null = null;

  constructor() {
    super({ key: "RegionSelectScene" });
  }

  create(): void {
    new BackgroundImage(this);
    mountBottomCommandStrip(this);
    const layout = getPageLayout(this);
    const contentFrame = new ContentAreaFrame({
      scene: this,
      x: layout.content.x,
      y: layout.content.y,
      width: layout.content.width,
      height: layout.content.height + RegionSelectScene.COLUMN_BOTTOM_EXTENSION,
      title: "Choose Region",
      marginPx: RegionSelectScene.FRAME_MARGIN,
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);

    const intelFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height + RegionSelectScene.COLUMN_BOTTOM_EXTENSION,
      title: "Region Intel",
      marginPx: RegionSelectScene.FRAME_MARGIN,
      bodyColor: 0x4f5a65,
    });
    intelFrame.setDepth(-800);

    this.buildIntelPanel();

    const contentBody = resolveContentFrameBodyRect({
      width: layout.content.width,
      height: layout.content.height + RegionSelectScene.COLUMN_BOTTOM_EXTENSION,
      titleHeight: RegionSelectScene.FRAME_TITLE_HEIGHT,
      marginPx: RegionSelectScene.FRAME_MARGIN,
    });
    const contentBodyX = layout.content.x + contentBody.x;
    const contentBodyY = layout.content.y + contentBody.y;
    const contentBodyHeight = contentBody.height;
    const panelX = contentBodyX + RegionSelectScene.INNER_PADDING;
    const panelY = contentBodyY + RegionSelectScene.INNER_PADDING;

    REGION_DEFINITIONS.forEach((region, index) => {
      const panel = new RegionSelectionPanel({
        scene: this,
        x: panelX + (RegionSelectScene.REGION_TILE_SIZE + RegionSelectScene.REGION_TILE_GAP) * index,
        y: panelY,
        width: RegionSelectScene.REGION_TILE_SIZE,
        height: RegionSelectScene.REGION_TILE_SIZE,
        regionId: region.id,
        label: region.label,
        textureKey: region.textureKey,
        locked: region.id !== "farm",
        onSelect: (regionId) => this.selectRegion(regionId as RegionId),
        onActivate: async (regionId) => this.startRun(regionId as RegionId),
        onLockedSelect: () => this.showFeedback("Region locked."),
        onUnavailableSelect: (regionId) => this.showFeedback(this.buildInsufficientEnergyMessage(regionId as RegionId)),
      });
      this.regionPanels.set(region.id, panel);
    });

    this.startRunButton = new SharedActionButton({
      scene: this,
      x: contentBodyX + RegionSelectScene.INNER_PADDING,
      y: contentBodyY + contentBodyHeight - 64 - RegionSelectScene.INNER_PADDING,
      label: "Start Run",
      onClick: () => void this.startRun(this.selectedRegionId),
    });

    this.selectRegion("farm");
    void this.loadRegionUnlocks();

    markDebugSceneReady(this);
  }

  private buildIntelPanel(): void {
    const layout = getPageLayout(this);
    const headerX = layout.buttons.x + 24;
    const headerCenterY = layout.buttons.y + 118;

    this.intelTitleText = this.add.text(headerX, headerCenterY, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "34px",
      color: "#f2f2f2",
      stroke: "#1a1a1a",
      strokeThickness: 3,
      wordWrap: { width: layout.buttons.width - 120 },
    }).setOrigin(0, 0.5);

    if (this.hasTexture("icon_energy_large")) {
      this.intelEnergyIcon = this.add
        .image(layout.buttons.x + layout.buttons.width - 54, headerCenterY, "icon_energy_large")
        .setOrigin(0.5, 0.5)
        .setDisplaySize(24, 24);
    }

    this.intelEnergyText = this.add.text(layout.buttons.x + layout.buttons.width - 38, headerCenterY, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "24px",
      color: "#f0d38a",
      stroke: "#1a1a1a",
      strokeThickness: 2,
    }).setOrigin(0, 0.5);

    this.intelBodyText = this.add.text(layout.buttons.x + 24, layout.buttons.y + 162, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "21px",
      color: "#e8edf1",
      lineSpacing: 10,
      wordWrap: { width: layout.buttons.width - 48 },
    });
  }

  private async loadRegionUnlocks(): Promise<void> {
    try {
      const profile = await apiClient.getProfile({ force: true, allowStaleOnError: true });
      if (!profile.ok) {
        this.currentEnergy = null;
        this.applyUnlockedRegions(new Set<RegionId>(["farm"]));
        return;
      }
      this.currentEnergy = {
        current: profile.data.energy.current,
        max: profile.data.energy.max,
      };
      this.applyUnlockedRegions(this.inferUnlockedRegions(profile.data.region_unlocks ?? []));
    } catch {
      this.currentEnergy = null;
      this.applyUnlockedRegions(new Set<RegionId>(["farm"]));
    }
  }

  private hasTexture(key: string): boolean {
    const textures = (this as Phaser.Scene & { textures?: { exists?: (textureKey: string) => boolean } }).textures;
    return typeof textures?.exists === "function" && textures.exists(key);
  }

  private applyUnlockedRegions(unlocked: Set<RegionId>): void {
    this.unlockedRegions = unlocked;
    REGION_DEFINITIONS.forEach((region) => {
      this.regionPanels.get(region.id)?.setLocked(!this.unlockedRegions.has(region.id));
    });
    this.refreshRegionUi();
  }

  private inferUnlockedRegions(entries: unknown[]): Set<RegionId> {
    const unlocked = new Set<RegionId>(["farm"]);
    for (const entry of entries) {
      if (!entry || typeof entry !== "object") continue;
      const record = entry as Record<string, unknown>;
      const numericId = Number(record.region_id ?? record.id ?? Number.NaN);
      const slug = String(record.region_slug ?? record.slug ?? "").toLowerCase();
      const unlockedFlag =
        record.unlocked === true ||
        record.is_unlocked === true ||
        String(record.status ?? "").toLowerCase() === "unlocked" ||
        typeof record.unlocked_at === "string" ||
        Number.isFinite(numericId) ||
        slug.length > 0;
      if (!unlockedFlag) continue;
      if (numericId === 3 || slug === "the_farm" || slug === "farm") unlocked.add("farm");
      if (numericId === 2 || slug === "swamp" || slug === "swamps") unlocked.add("swamp");
      if (numericId === 1 || slug === "mountain" || slug === "mountains") unlocked.add("mountain");
    }
    return unlocked;
  }

  private selectRegion(regionId: RegionId): void {
    this.selectedRegionId = regionId;
    this.refreshRegionUi();
  }

  private refreshRegionUi(): void {
    REGION_DEFINITIONS.forEach((region) => {
      const panel = this.regionPanels.get(region.id);
      if (!panel) return;
      const panelUnlocked = this.unlockedRegions.has(region.id);
      const canAfford = this.canAffordRegion(region);
      panel.setSelected(region.id === this.selectedRegionId);
      panel.setStartable(panelUnlocked && canAfford !== false, `Need ${region.energyCost} Energy`);
    });

    const selected = REGION_DEFINITIONS.find((region) => region.id === this.selectedRegionId) ?? REGION_DEFINITIONS[0]!;
    const isUnlocked = this.unlockedRegions.has(selected.id);
    const canAffordSelected = this.canAffordRegion(selected);
    const startButtonLabel = !isUnlocked
      ? "Locked"
      : canAffordSelected === false
        ? `Need ${selected.energyCost} Energy`
        : "Start Run";

    this.intelTitleText?.setText(selected.intelTitle);
    this.intelEnergyText?.setText(String(selected.energyCost));
    this.intelBodyText?.setText(selected.intelDescription);
    this.layoutIntelHeader();

    this.startRunButton?.setText(startButtonLabel).setEnabled(isUnlocked && canAffordSelected !== false);
  }

  private layoutIntelHeader(): void {
    if (!this.intelTitleText || !this.intelEnergyText) {
      return;
    }

    const layout = getPageLayout(this);
    const titleRight = this.intelTitleText.x + this.intelTitleText.width;
    const maxIconX = layout.buttons.x + layout.buttons.width - 54;
    const desiredIconX = titleRight + 20;
    const iconX = Math.min(desiredIconX, maxIconX);

    this.setObjectPosition(this.intelEnergyIcon, iconX, this.intelTitleText.y);
    this.setObjectPosition(this.intelEnergyText, iconX + 14, this.intelTitleText.y);
  }

  private setObjectPosition(
    object: { setPosition?: (x: number, y: number) => unknown; x?: number; y?: number } | undefined,
    x: number,
    y: number,
  ): void {
    if (!object) {
      return;
    }
    if (typeof object.setPosition === "function") {
      object.setPosition(x, y);
      return;
    }
    object.x = x;
    object.y = y;
  }

  private async startRun(regionId: RegionId): Promise<void> {
    if (!this.unlockedRegions.has(regionId)) {
      this.showFeedback("Region locked.");
      return;
    }

    if (this.canAffordRegionById(regionId) === false) {
      this.showFeedback(this.buildInsufficientEnergyMessage(regionId));
      return;
    }

    try {
      const region = REGION_DEFINITIONS.find((entry) => entry.id === regionId);
      const res = await apiClient.createRun(region?.regionDbId ?? 1);
      if (!res.ok) {
        this.showFeedback(this.resolveRunStartErrorMessage(regionId, res.error.message));
        return;
      }
      this.scene.start("MapExplorationScene");
    } catch (error) {
      const fallback = "Cannot start run right now. Please retry.";
      const message = error instanceof Error ? error.message : fallback;
      this.showFeedback(this.resolveRunStartErrorMessage(regionId, message));
    }
  }

  private showFeedback(message: string): void {
    void message;
  }

  private canAffordRegionById(regionId: RegionId): boolean | null {
    const region = REGION_DEFINITIONS.find((entry) => entry.id === regionId);
    return region ? this.canAffordRegion(region) : null;
  }

  private canAffordRegion(region: RegionDefinition): boolean | null {
    if (this.currentEnergy === null) {
      return null;
    }
    return this.currentEnergy.current >= region.energyCost;
  }

  private buildInsufficientEnergyMessage(regionId: RegionId): string {
    const region = REGION_DEFINITIONS.find((entry) => entry.id === regionId) ?? REGION_DEFINITIONS[0]!;
    if (this.currentEnergy !== null) {
      return `${region.label} costs ${region.energyCost} energy to start. You currently have ${this.currentEnergy.current}/${this.currentEnergy.max}.`;
    }
    return `${region.label} costs ${region.energyCost} energy to start.`;
  }

  private resolveRunStartErrorMessage(regionId: RegionId, message: string): string {
    if (message.includes("insufficient_energy")) {
      return this.buildInsufficientEnergyMessage(regionId);
    }
    if (message.includes("run_already_active")) {
      return "A run is already active. Return to the map or abandon the current run before starting another.";
    }
    return `Cannot start run: ${message}`;
  }
}

import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import RegionSelectionPanel from "../components/navigation/RegionSelectionPanel";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { apiClient } from "../services/apiClient";

type RegionId = "farm" | "mountain" | "swamp";

type RegionDefinition = {
  id: RegionId;
  label: string;
  textureKey: string;
  regionDbId: number;
  energyCost: number;
  intelTitle: string;
  intelDescription: string;
};

const REGION_DEFINITIONS: RegionDefinition[] = [
  {
    id: "farm",
    label: "The Farm",
    textureKey: "column_mountain",
    regionDbId: 3,
    energyCost: 3,
    intelTitle: "THE FARM",
    intelDescription: "Short guided route with pig-only encounters. Best place to learn formation, rewards, and rest flow.",
  },
  {
    id: "mountain",
    label: "Mountains",
    textureKey: "column_mountain",
    regionDbId: 1,
    energyCost: 5,
    intelTitle: "MOUNTAINS",
    intelDescription: "Steady enemy pacing with dependable rewards. Recommended baseline route for early runs.",
  },
  {
    id: "swamp",
    label: "Swamp",
    textureKey: "column_swamp",
    regionDbId: 2,
    energyCost: 5,
    intelTitle: "SWAMP",
    intelDescription: "Higher-risk encounters and unstable attrition. Best attempted after core warband upgrades.",
  },
];

export default class RegionSelectScene extends Phaser.Scene {
  private selectedRegionId: RegionId = "farm";
  private unlockedRegions = new Set<RegionId>(["farm"]);
  private regionPanels = new Map<RegionId, RegionSelectionPanel>();
  private intelTitleText?: Phaser.GameObjects.Text;
  private intelBodyText?: Phaser.GameObjects.Text;
  private intelHintText?: Phaser.GameObjects.Text;
  private statusTitleText?: Phaser.GameObjects.Text;
  private statusBodyText?: Phaser.GameObjects.Text;
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
      height: layout.content.height,
      title: "Choose Region",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);

    const intelFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Region Intel",
      bodyColor: 0x4f5a65,
    });
    intelFrame.setDepth(-800);

    this.buildIntelPanel();

    const gap = 18;
    const panelWidth = Math.floor((layout.content.width - gap * 4) / 3);
    const panelHeight = Math.max(240, Math.min(380, layout.content.height - 160));
    const panelY = layout.content.y + 90;

    REGION_DEFINITIONS.forEach((region, index) => {
      const panel = new RegionSelectionPanel({
        scene: this,
        x: layout.content.x + gap + (panelWidth + gap) * index,
        y: panelY,
        width: panelWidth,
        height: panelHeight,
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

    this.selectRegion("farm");
    void this.loadRegionUnlocks();

    markDebugSceneReady(this);
  }

  private buildIntelPanel(): void {
    const layout = getPageLayout(this);
    this.intelTitleText = this.add.text(layout.buttons.x + 24, layout.buttons.y + 90, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "36px",
      color: "#f2f2f2",
      stroke: "#1a1a1a",
      strokeThickness: 3,
      wordWrap: { width: layout.buttons.width - 48 },
    });

    this.intelBodyText = this.add.text(layout.buttons.x + 24, layout.buttons.y + 146, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "22px",
      color: "#e8edf1",
      lineSpacing: 8,
      wordWrap: { width: layout.buttons.width - 48 },
    });

    this.intelHintText = this.add.text(layout.buttons.x + 24, layout.buttons.y + layout.buttons.height - 150, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: "#b9d0d6",
      wordWrap: { width: layout.buttons.width - 48 },
    });

    const statusBoxY = layout.buttons.y + layout.buttons.height - 244;
    this.add.rectangle(layout.buttons.x + 20, statusBoxY, layout.buttons.width - 40, 92, 0x1e252b, 0.92)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xb9d0d6, 0.2);

    this.statusTitleText = this.add.text(layout.buttons.x + 36, statusBoxY + 14, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "22px",
      color: "#f2f2f2",
      stroke: "#1a1a1a",
      strokeThickness: 3,
      wordWrap: { width: layout.buttons.width - 72 },
    });

    this.statusBodyText = this.add.text(layout.buttons.x + 36, statusBoxY + 42, "", {
      fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
      fontSize: "18px",
      color: "#dbe4e8",
      lineSpacing: 4,
      wordWrap: { width: layout.buttons.width - 72 },
    });

    this.startRunButton = new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(0, Math.floor((layout.buttons.width - 280) / 2)),
      y: layout.buttons.y + layout.buttons.height - 92,
      label: "Start Run",
      onClick: () => void this.startRun(this.selectedRegionId),
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
    this.intelBodyText?.setText(selected.intelDescription);
    this.intelHintText?.setText(
      !isUnlocked
        ? "This region is locked. Progress through available regions to unlock it."
        : canAffordSelected === false
          ? `You need ${selected.energyCost} energy to start this run. Wait for regen or return after recovering energy.`
          : "Single-click a region to inspect. Double-click the region card or press Start Run to begin."
    );

    this.statusTitleText?.setText(
      !isUnlocked
        ? "Region Locked"
        : canAffordSelected === false
          ? "Insufficient Energy"
          : "Ready"
    );
    this.statusBodyText?.setText(
      !isUnlocked
        ? "This region cannot be launched yet."
        : canAffordSelected === false
          ? this.buildInsufficientEnergyMessage(selected.id)
          : this.currentEnergy !== null
            ? `Current energy: ${this.currentEnergy.current}/${this.currentEnergy.max}. Run cost: ${selected.energyCost}.`
            : `Run cost: ${selected.energyCost} energy.`
    );

    this.startRunButton?.setText(startButtonLabel).setEnabled(isUnlocked && canAffordSelected !== false);
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
    this.statusTitleText?.setText("Run Start Blocked");
    this.statusBodyText?.setText(message);
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

import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import ToastMessage from "../components/feedback/ToastMessage";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import { markDebugSceneReady } from "../debug/debugHooks";
import { getPageLayout } from "../layout/pageLayout";
import RegionSelectionPanel from "../components/navigation/RegionSelectionPanel";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import { apiClient } from "../services/apiClient";

type RegionId = "mountain" | "swamp";

type RegionDefinition = {
  id: RegionId;
  label: string;
  textureKey: string;
  regionDbId: number;
  intelTitle: string;
  intelDescription: string;
};

const REGION_DEFINITIONS: RegionDefinition[] = [
  {
    id: "mountain",
    label: "Mountains",
    textureKey: "column_mountain",
    regionDbId: 1,
    intelTitle: "MOUNTAINS",
    intelDescription: "Steady enemy pacing with dependable rewards. Recommended baseline route for early runs.",
  },
  {
    id: "swamp",
    label: "Swamp",
    textureKey: "column_swamp",
    regionDbId: 2,
    intelTitle: "SWAMP",
    intelDescription: "Higher-risk encounters and unstable attrition. Best attempted after core warband upgrades.",
  },
];

export default class RegionSelectScene extends Phaser.Scene {
  private toast?: ToastMessage;
  private selectedRegionId: RegionId = "mountain";
  private unlockedRegions = new Set<RegionId>(["mountain"]);
  private regionPanels = new Map<RegionId, RegionSelectionPanel>();
  private intelTitleText?: Phaser.GameObjects.Text;
  private intelBodyText?: Phaser.GameObjects.Text;
  private intelHintText?: Phaser.GameObjects.Text;
  private startRunButton?: SharedActionButton;

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

    const gap = 24;
    const panelWidth = Math.floor((layout.content.width - gap * 3) / 2);
    const panelHeight = Math.max(200, layout.content.height - 200);
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
        locked: region.id !== "mountain",
        onSelect: (regionId) => this.selectRegion(regionId as RegionId),
        onActivate: async (regionId) => this.startRun(regionId as RegionId),
        onLockedSelect: () => this.showFeedback("Region locked."),
      });
      this.regionPanels.set(region.id, panel);
    });

    this.selectRegion("mountain");
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
        this.applyUnlockedRegions(new Set<RegionId>(["mountain"]));
        return;
      }
      this.applyUnlockedRegions(this.inferUnlockedRegions(profile.data.region_unlocks ?? []));
    } catch {
      this.applyUnlockedRegions(new Set<RegionId>(["mountain"]));
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
    const unlocked = new Set<RegionId>(["mountain"]);
    for (const entry of entries) {
      if (!entry || typeof entry !== "object") continue;
      const record = entry as Record<string, unknown>;
      const unlockedFlag =
        record.unlocked === true ||
        record.is_unlocked === true ||
        String(record.status ?? "").toLowerCase() === "unlocked";
      if (!unlockedFlag) continue;

      const numericId = Number(record.region_id ?? record.id ?? NaN);
      const slug = String(record.region_slug ?? record.slug ?? "").toLowerCase();
      if (numericId === 2 || slug === "swamp") {
        unlocked.add("swamp");
      }
      if (numericId === 1 || slug === "mountain") {
        unlocked.add("mountain");
      }
    }
    return unlocked;
  }

  private selectRegion(regionId: RegionId): void {
    this.selectedRegionId = regionId;
    this.refreshRegionUi();
  }

  private refreshRegionUi(): void {
    REGION_DEFINITIONS.forEach((region) => {
      this.regionPanels.get(region.id)?.setSelected(region.id === this.selectedRegionId);
    });

    const selected = REGION_DEFINITIONS.find((region) => region.id === this.selectedRegionId) ?? REGION_DEFINITIONS[0]!;
    const isUnlocked = this.unlockedRegions.has(selected.id);
    this.intelTitleText?.setText(selected.intelTitle);
    this.intelBodyText?.setText(selected.intelDescription);
    this.intelHintText?.setText(
      isUnlocked
        ? "Single-click a region to inspect. Double-click the region card or press Start Run to begin."
        : "This region is locked. Progress through available regions to unlock it."
    );
    this.startRunButton?.setText(isUnlocked ? "Start Run" : "Locked").setEnabled(isUnlocked);
  }

  private async startRun(regionId: RegionId): Promise<void> {
    if (!this.unlockedRegions.has(regionId)) {
      this.showFeedback("Region locked.");
      return;
    }

    try {
      const res = await apiClient.createRun(regionId);
      if (!res.ok) {
        this.showFeedback(`Cannot start run: ${res.error.message}`);
        return;
      }
      this.scene.start("MapExplorationScene");
    } catch (error) {
      const fallback = "Cannot start run right now. Please retry.";
      this.showFeedback(error instanceof Error ? error.message : fallback);
    }
  }

  private showFeedback(message: string): void {
    this.toast?.destroy();
    this.toast = new ToastMessage({
      scene: this,
      x: 24,
      y: this.scale.height - 84,
      message,
      severity: "warning",
      durationMs: 2600,
    });
  }
}








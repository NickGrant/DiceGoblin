import Phaser from "phaser";
import type { TeamRecord } from "../types/ApiResponse";
import SharedActionButton from "./clickable-panel/SharedActionButton";
import UnifiedButtonList from "./clickable-panel/UnifiedButtonList";
import ListContainer from "./list/ListContainer";

type SquadListPanelConfig = {
  scene: Phaser.Scene;
  x: number;
  y: number;
  width: number;
  height: number;
  title?: string;
  squads: TeamRecord[];
  onSquadClick: (squad: TeamRecord) => void;
  maxVisibleSquads?: number;
  buttonGapY?: number;
};

export default class SquadListPanel extends Phaser.GameObjects.Container {
  private static readonly METAL_BUTTON_METRICS = SharedActionButton.getVariantMetrics("metal");
  private readonly sceneRef: Phaser.Scene;
  private readonly panelWidth: number;
  private readonly panelHeight: number;
  private readonly title: string;
  private readonly onSquadClick: (squad: TeamRecord) => void;
  private squads: TeamRecord[];
  private readonly maxVisibleSquads?: number;
  private readonly buttonGapY: number;
  private pageIndex = 0;
  private listContainer: ListContainer<TeamRecord>;

  constructor(cfg: SquadListPanelConfig) {
    super(cfg.scene, cfg.x, cfg.y);

    this.sceneRef = cfg.scene;
    this.panelWidth = cfg.width;
    this.panelHeight = cfg.height;
    this.title = cfg.title ?? "";
    this.onSquadClick = cfg.onSquadClick;
    this.squads = cfg.squads;
    this.maxVisibleSquads = cfg.maxVisibleSquads;
    this.buttonGapY = Math.max(0, cfg.buttonGapY ?? 8);

    this.listContainer = new ListContainer<TeamRecord>({
      scene: this.sceneRef,
      x: 0,
      y: 0,
      width: this.panelWidth,
      height: this.panelHeight,
      title: this.title,
      items: this.squads,
      loadState: "ready",
      pageIndex: this.pageIndex,
      renderItems: ({ scene, parent, items, contentX, contentY, contentWidth }) => {
        const buttonListX = contentX + Math.max(
          0,
          Math.floor((contentWidth - SquadListPanel.METAL_BUTTON_METRICS.listWidth) / 2),
        );

        const buttonList = new UnifiedButtonList({
          scene,
          x: buttonListX,
          y: contentY,
          gapY: this.buttonGapY,
          defaultVariant: "metal",
          buttons: items.map((squad) => ({
            label: squad.is_active ? `${squad.name} [ACTIVE]` : squad.name,
            onClick: () => this.onSquadClick(squad),
          })),
        });

        parent.add(buttonList);
        return [buttonList];
      },
      getPageSize: ({ contentHeight }) => {
        if (this.maxVisibleSquads && this.maxVisibleSquads > 0) {
          return this.maxVisibleSquads;
        }

        const rowHeight = SquadListPanel.METAL_BUTTON_METRICS.listRowHeight;
        return Math.max(1, Math.floor((contentHeight + this.buttonGapY) / (rowHeight + this.buttonGapY)));
      },
      emptyMessage: "No squads found.",
    });

    this.add(this.listContainer);

    cfg.scene.add.existing(this);
  }

  public setSquads(squads: TeamRecord[]): this {
    this.squads = squads;
    this.pageIndex = 0;
    this.syncList();
    return this;
  }

  private syncList(): void {
    this.listContainer.updateState({
      items: this.squads,
      loadState: "ready",
      pageIndex: this.pageIndex,
    });
  }
}


import BackgroundImage from "../components/BackgroundImage";
import HomeButton from "../components/HomeButton";
import HudPanel from "../components/HudPanel";
import UiButton from "../components/Button";
import UnitListPanel, { type UnitListRowState } from "../components/UnitListPanel";
import { adaptDiceDetails, adaptUnitRecords } from "../adapters/profileViewModels";
import { apiClient } from "../services/apiClient";
import type { DiceDetailsViewModel } from "../adapters/profileViewModels";
import type { UnitRecord } from "../types/ApiResponse";


export default class DiceInventoryScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private returnScene = "HomeScene";
  private mutationAllowed = true;

  private units: UnitRecord[] = [];
  private dice: DiceDetailsViewModel[] = [];
  private selectedUnitId: string | null = null;
  private selectedDiceId: string | null = null;
  private unitPanel?: UnitListPanel;
  private diceText?: Phaser.GameObjects.Text;
  private statusText?: Phaser.GameObjects.Text;
  private toastText?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "DiceInventoryScene" });
  }

  init(data: { runId?: string; nodeId?: string; returnScene?: string }): void {
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    this.returnScene = String(data?.returnScene ?? "HomeScene");
  }

  create(): void {
    new BackgroundImage(this, 'background_workbench');
    new HudPanel(this);
    new HomeButton(this, {x: 64, y: 52}).setScale(.5);

    const inRestContext = this.runId !== "" && this.nodeId !== "";
    this.add.text(480, 92, "DICE INVENTORY", {
      fontFamily: "Arial",
      fontSize: "20px",
      color: "#ffffff",
    }).setOrigin(0.5);

    this.add.text(480, 136, inRestContext
      ? `Rest context active (run ${this.runId}, node ${this.nodeId}).`
      : "Out-of-run context.", {
      fontFamily: "Arial",
      fontSize: "12px",
      color: "#dddddd",
    }).setOrigin(0.5);

    this.add.text(480, 168, inRestContext
      ? "Dice changes from this screen should be validated against rest-context backend rules."
      : "Dice changes are available here between runs.", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#bbbbbb",
      align: "center",
      wordWrap: { width: 720 },
    }).setOrigin(0.5);

    if (inRestContext) {
      new UiButton({
        scene: this,
        x: 860,
        y: 92,
        label: "Back to Rest",
        size: "tiny",
        onClick: () => this.scene.start(this.returnScene, {
          runId: this.runId,
          nodeId: this.nodeId,
        }),
      });
    }

    this.statusText = this.add.text(480, 194, "Loading inventory...", {
      fontFamily: "Arial",
      fontSize: "11px",
      color: "#dddddd",
      align: "center",
      wordWrap: { width: 760 },
    }).setOrigin(0.5);

    new UiButton({
      scene: this,
      x: 560,
      y: 510,
      label: "Prev Die",
      size: "tiny",
      onClick: () => this.selectAdjacentDie(-1),
    });
    new UiButton({
      scene: this,
      x: 620,
      y: 510,
      label: "Next Die",
      size: "tiny",
      onClick: () => this.selectAdjacentDie(1),
    });
    new UiButton({
      scene: this,
      x: 710,
      y: 510,
      label: "Equip Selected",
      size: "tiny",
      onClick: () => void this.equipSelected(),
    });
    new UiButton({
      scene: this,
      x: 850,
      y: 510,
      label: "Unequip Selected",
      size: "tiny",
      onClick: () => void this.unequipSelected(),
    });

    void this.loadData();
  }

  private async loadData(): Promise<void> {
    const profile = await apiClient.getProfile({ force: true });
    if (!profile.ok) {
      this.setStatus(`Profile unavailable: ${profile.error.message}`);
      return;
    }
    this.units = adaptUnitRecords(profile.data.units ?? []);
    this.dice = adaptDiceDetails(profile.data.dice ?? [], profile.data.units ?? []);
    this.mutationAllowed = this.runId !== "" && this.nodeId !== "" ? true : profile.data.active_run === null;
    if (this.selectedUnitId === null && this.units.length > 0) {
      this.selectedUnitId = this.units[0]?.id ?? null;
    }
    this.renderUnitPanel();
    this.renderDiceList();
    const modeLabel = this.mutationAllowed
      ? "Dice actions are enabled."
      : "Active run detected outside rest context: dice actions disabled.";
    this.setStatus(modeLabel);
  }

  private renderUnitPanel(): void {
    this.unitPanel?.destroy();
    this.unitPanel = new UnitListPanel({
      scene: this,
      x: 20,
      y: 220,
      width: 380,
      height: 280,
      title: "UNITS",
      units: this.units,
      onUnitClick: (u) => {
        this.selectedUnitId = u.id;
        this.unitPanel?.refreshRowStates();
        this.renderDiceList();
      },
      getRowState: (u) => this.getUnitRowState(u),
      maxVisibleRows: 9,
    });
  }

  private getUnitRowState(unit: UnitRecord): UnitListRowState {
    return {
      highlighted: unit.id === this.selectedUnitId,
      disabled: !this.mutationAllowed,
      badgeText: unit.id === this.selectedUnitId ? "SELECTED" : null,
    };
  }

  private renderDiceList(): void {
    this.diceText?.destroy();
    const lines = [
      "DICE",
      "Use Prev/Next Die to choose a die.",
      "",
    ];
    if (this.selectedDiceId === null && this.dice.length > 0) {
      this.selectedDiceId = this.dice[0]?.id ?? null;
    }
    const selectedIndex = this.dice.findIndex((d) => d.id === this.selectedDiceId);
    const safeSelected = selectedIndex >= 0 ? selectedIndex : 0;
    if (this.dice.length > 0) {
      const selectedDie = this.dice[safeSelected];
      this.selectedDiceId = selectedDie ? selectedDie.id : null;
    }
    for (let i = 0; i < Math.min(this.dice.length, 12); i += 1) {
      const die = this.dice[i];
      if (!die) continue;
      const marker = die.id === this.selectedDiceId ? ">" : " ";
      const equipLabel = die.equipped ? ` [Equipped: ${die.equipped.unitName}]` : "";
      lines.push(`${marker} ${i + 1}. ${die.displayName}${equipLabel}`);
    }
    if (this.dice.length === 0) {
      lines.push("No dice available.");
    }
    this.diceText = this.add.text(430, 220, lines.join("\n"), {
      fontFamily: "monospace",
      fontSize: "12px",
      color: "#f5f5f5",
      wordWrap: { width: 500 },
    });
  }

  private selectAdjacentDie(delta: number): void {
    if (this.dice.length === 0) return;
    const currentIndex = Math.max(0, this.dice.findIndex((d) => d.id === this.selectedDiceId));
    const nextIndex = (currentIndex + delta + this.dice.length) % this.dice.length;
    const die = this.dice[nextIndex];
    this.selectedDiceId = die ? die.id : this.selectedDiceId;
    this.renderDiceList();
  }

  private async equipSelected(): Promise<void> {
    if (!this.mutationAllowed) {
      this.showToast("Equip blocked outside rest while run is active.");
      return;
    }
    if (!this.selectedUnitId || !this.selectedDiceId) {
      this.showToast("Select a unit and die.");
      return;
    }
    const res = await apiClient.equipDice(
      this.selectedUnitId,
      this.selectedDiceId,
      this.runId && this.nodeId ? { runId: this.runId, nodeId: this.nodeId } : undefined
    );
    if (!res.ok) {
      this.showToast(`Equip failed: ${res.error.message}`);
      return;
    }
    this.showToast("Die equipped.", "#ccffcc");
    await this.loadData();
  }

  private async unequipSelected(): Promise<void> {
    if (!this.mutationAllowed) {
      this.showToast("Unequip blocked outside rest while run is active.");
      return;
    }
    if (!this.selectedUnitId || !this.selectedDiceId) {
      this.showToast("Select a unit and die.");
      return;
    }
    const res = await apiClient.unequipDice(
      this.selectedUnitId,
      this.selectedDiceId,
      this.runId && this.nodeId ? { runId: this.runId, nodeId: this.nodeId } : undefined
    );
    if (!res.ok) {
      this.showToast(`Unequip failed: ${res.error.message}`);
      return;
    }
    this.showToast("Die unequipped.", "#ccffcc");
    await this.loadData();
  }

  private setStatus(message: string): void {
    this.statusText?.setText(message);
  }

  private showToast(message: string, color = "#ffcccc"): void {
    this.toastText?.destroy();
    this.toastText = this.add.text(480, 530, message, {
      fontFamily: "Arial",
      fontSize: "12px",
      color,
    }).setOrigin(0.5);
    this.time.delayedCall(2000, () => {
      this.toastText?.destroy();
      this.toastText = undefined;
    });
  }
}

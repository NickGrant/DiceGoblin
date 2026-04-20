import Phaser from "phaser";
import BackgroundImage from "../components/BackgroundImage";
import { mountBottomCommandStrip } from "../components/BottomCommandStrip";
import SharedActionButton from "../components/clickable-panel/SharedActionButton";
import { getDebugSceneConfig } from "../debug/debugScene";
import { isDevPanelEnabled } from "../debug/devFlags";
import { getDebugResolvedNodeFixture } from "../debug/debugFixtures";
import { markDebugSceneReady } from "../debug/debugHooks";
import { apiClient } from "../services/apiClient";
import { getPageLayout } from "../layout/pageLayout";
import ContentAreaFrame from "../components/layout/ContentAreaFrame";
import {
  deriveSummaryStatus,
  formatUnlockedNodes,
  isNodeResolutionType,
  type NodeResolutionType,
} from "./nodeResolutionFlow";
import {
  buildClaimSummary,
  buildLootReceiptLines,
  buildTickSummaryLines,
  deriveDefaultTick,
  prettifyEnemySlug,
  shortenEnemyLabel,
  type BattleLog,
  type ClaimSummary,
  type ResolutionSummary,
} from "./nodeResolutionPresentation";
import FormationGrid3x3, {
  type FormationMap,
  type FormationStatusIndicator,
} from "../components/FormationGrid3x3";
import {
  anchorCellForCells,
  isAnchorCell,
  occupiedCellsFromAnchor,
  type FormationCellId,
  type FormationFootprint,
} from "../utils/formationGeometry";

const ACTION_BODY_TOP_OFFSET = 72;
const CONTENT_BODY_TOP_OFFSET = 74;
const CONTENT_BODY_BOTTOM_PADDING = 22;
const STATUS_CARD_HEIGHT = 88;
const RESOLUTION_BODY_TOP_GAP = 24;
const RESOLVE_TIMEOUT_MS = 12_000;
const TICK_AUTOPLAY_STEP_MS = 850;
const TICK_PLAYBACK_SPEEDS = [1, 2, 4] as const;
const ACTION_BUTTON_WIDTH = 280;

type NodeResolutionSceneData = {
  runId?: string;
  nodeId?: string;
  nodeType?: string;
};

type ParticipantView = {
  id: string;
  display: string;
  maxHp: number;
  pos: { x: number; y: number } | null;
  formation: FormationFootprint;
};

type UnitStatusSnapshot = Record<string, FormationStatusIndicator[]>;

export default class NodeResolutionScene extends Phaser.Scene {
  private runId = "";
  private nodeId = "";
  private nodeType: NodeResolutionType | null = null;
  private hasResolved = false;
  private actionButton?: SharedActionButton;
  private debugBattleLogButton?: SharedActionButton;
  private actionHandler: (() => void) | null = null;

  private statusText?: Phaser.GameObjects.Text;
  private detailText?: Phaser.GameObjects.Text;
  private errorText?: Phaser.GameObjects.Text;
  private statusCard?: Phaser.GameObjects.Rectangle;
  private errorCard?: Phaser.GameObjects.Rectangle;
  private resolutionUiObjects: Phaser.GameObjects.GameObject[] = [];
  private resolveTimeoutMs = RESOLVE_TIMEOUT_MS;
  private logMaskGraphics?: Phaser.GameObjects.Graphics;
  private logViewport: { x: number; y: number; width: number; height: number } | null = null;
  private logScrollOffset = 0;
  private selectedTick = 0;
  private latestLog: BattleLog = null;
  private latestSummary: ResolutionSummary | null = null;
  private wheelHandlerRegistered = false;
  private tickPlaybackActive = false;
  private tickPlaybackTimer?: Phaser.Time.TimerEvent;
  private playbackSpeedMultiplier: (typeof TICK_PLAYBACK_SPEEDS)[number] = 1;
  private hoverHpTooltip?: Phaser.GameObjects.Text;

  constructor() {
    super({ key: "NodeResolutionScene" });
  }

  init(data: NodeResolutionSceneData): void {
    this.hasResolved = false;
    this.stopTickPlayback();
    this.selectedTick = 0;
    this.latestLog = null;
    this.latestSummary = null;
    this.playbackSpeedMultiplier = 1;
    this.runId = String(data?.runId ?? "");
    this.nodeId = String(data?.nodeId ?? "");
    const typeValue = String(data?.nodeType ?? "");
    this.nodeType = isNodeResolutionType(typeValue) ? typeValue : null;
    const debugConfig = getDebugSceneConfig();
    if (debugConfig.enabled) {
      if (!this.runId) this.runId = "91";
      if (!this.nodeId) this.nodeId = "502";
      if (!this.nodeType) this.nodeType = "combat";
    }
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
      title: "Resolve Node",
      bodyColor: 0x23272a,
    });
    contentFrame.setDepth(-800);
    const actionsFrame = new ContentAreaFrame({
      scene: this,
      x: layout.buttons.x,
      y: layout.buttons.y,
      width: layout.buttons.width,
      height: layout.buttons.height,
      title: "Node Actions",
      bodyColor: 0x006f7a,
    });
    actionsFrame.setDepth(-800);
    this.statusCard = this.add
      .rectangle(
        layout.content.x + 12,
        layout.content.y + CONTENT_BODY_TOP_OFFSET,
        layout.content.width - 24,
        STATUS_CARD_HEIGHT,
        0x173136,
        0.6
      )
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x8ea1af, 0.42);
    this.statusText = this.add
      .text(layout.content.x + 26, layout.content.y + CONTENT_BODY_TOP_OFFSET + 12, "Resolving node...", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "26px",
        color: "#ffffff",
      })
      .setOrigin(0, 0);

    this.detailText = this.add
      .text(layout.content.x + 26, layout.content.y + CONTENT_BODY_TOP_OFFSET + 46, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#e6eff0",
        lineSpacing: 5,
        wordWrap: { width: Math.max(300, layout.content.width - 52) },
      })
      .setOrigin(0, 0);

    this.errorCard = this.add
      .rectangle(
        layout.content.x + 12,
        layout.content.y + layout.content.height - CONTENT_BODY_BOTTOM_PADDING - 58,
        layout.content.width - 24,
        46,
        0x48262a,
        0.78
      )
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xd2858a, 0.5);
    if (this.errorCard && "setVisible" in this.errorCard && typeof this.errorCard.setVisible === "function") {
      this.errorCard.setVisible(false);
    }
    this.errorText = this.add
      .text(layout.content.x + 26, layout.content.y + layout.content.height - CONTENT_BODY_BOTTOM_PADDING - 35, "", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "14px",
        color: "#ffb3b3",
        wordWrap: { width: Math.max(300, layout.content.width - 52) },
      })
      .setOrigin(0, 0.5);

    this.setStatusBanner(
      "Resolving node...",
      "Stand by while the encounter resolves. Battle details, rewards, and unlocked route updates will appear here."
    );
    this.renderStatePlaceholder(
      "Preparing encounter report",
      [
        `Run ${this.runId || "?"} | Node ${this.nodeId || "?"} | ${String(this.nodeType ?? "unknown").toUpperCase()}`,
        "If the encounter takes too long, you can retry the resolution from the action panel.",
      ],
      0x89dfe0,
      0x13262d
    );

    this.actionButton = new SharedActionButton({
      scene: this,
      x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2)),
      y: layout.buttons.y + ACTION_BODY_TOP_OFFSET,
      label: "Resolving...",
      enabled: false,
      onClick: () => this.actionHandler?.(),
    });
    if (isDevPanelEnabled()) {
      this.debugBattleLogButton = new SharedActionButton({
        scene: this,
        x: layout.buttons.x + Math.max(10, Math.floor((layout.buttons.width - ACTION_BUTTON_WIDTH) / 2)),
        y: layout.buttons.y + ACTION_BODY_TOP_OFFSET + 76,
        label: "Copy Battle Log",
        enabled: false,
        onClick: () => void this.copyBattleLogToClipboard(),
      });
    }

    if (!this.wheelHandlerRegistered && this.input && typeof this.input.on === "function") {
      this.input.on("wheel", this.handleLogWheel, this);
      this.wheelHandlerRegistered = true;
      if (this.events && typeof this.events.once === "function") {
        this.events.once(Phaser.Scenes.Events.SHUTDOWN, () => {
          if (this.input && typeof this.input.off === "function") {
            this.input.off("wheel", this.handleLogWheel, this);
          }
          this.stopTickPlayback();
          this.wheelHandlerRegistered = false;
        });
      }
    }

    void this.resolveNode();
  }

  private async resolveNode(): Promise<void> {
    if (!this.nodeType || !this.runId || !this.nodeId) {
      this.setStatusBanner("Missing run context", "This node cannot resolve without an active run and node reference.");
      this.renderStatePlaceholder(
        "Resolution unavailable",
        ["Return to the map or reopen the run from Home to refresh the encounter context."],
        0xffb2b2,
        0x341d22
      );
      this.showError("Node resolution unavailable: missing run context.");
      this.configureButton("Back to Map", true, () => this.returnToMap());
      markDebugSceneReady(this, { state: "error" });
      return;
    }

    if (this.hasResolved) {
      return;
    }
    this.hasResolved = true;
    this.clearError();
    this.setStatusBanner(
      `Resolving ${String(this.nodeType).toUpperCase()} node`,
      `Run ${this.runId} | Node ${this.nodeId}. Building battle results and reward summary now.`
    );
    this.configureButton("Resolving...", false, () => {
      // Intentionally disabled during active resolve.
    });

    try {
      if (this.nodeType === "exit") {
        const debugConfig = getDebugSceneConfig();
        const exitRes = await apiClient.exitRun(this.runId).catch(() => {
          if (!debugConfig.enabled) {
            throw new Error("Failed to exit run");
          }
          return {
            ok: true,
            data: {
              run_id: this.runId,
              status: "completed",
            },
          };
        });
        if (!exitRes.ok) {
          if (debugConfig.enabled) {
            const status = deriveSummaryStatus({
              nodeType: this.nodeType,
              exitStatus: "completed",
            });
            this.setStatusBanner("Exit resolved", "Run status: completed. Debug fallback completed this exit endpoint.");
            this.configureButton("Continue", true, () => {
              this.scene.start("RunEndSummaryScene", {
                status,
                rewards: ["- No rewards from exit node"],
                progression: [],
                survivors: [],
                defeated: [],
              });
            });
            markDebugSceneReady(this, { state: "exit-resolved", status, fallback: "debug" });
            return;
          }
          const exitErrorMessage = (exitRes as { error?: { message?: string } }).error?.message ?? "Unknown error";
          this.showError(`Exit failed: ${exitErrorMessage}`);
          this.configureButton("Retry", true, () => {
            this.hasResolved = false;
            void this.resolveNode();
          });
          return;
        }

        const status = deriveSummaryStatus({
          nodeType: this.nodeType,
          exitStatus: exitRes.data.status,
        });

        this.setStatusBanner("Exit resolved", `Run status: ${exitRes.data.status}. This run endpoint has been finalized.`);
        this.configureButton("Continue", true, () => {
          this.scene.start("RunEndSummaryScene", {
            status,
            rewards: ["- No rewards from exit node"],
            progression: [],
            survivors: [],
            defeated: [],
          });
        });
        markDebugSceneReady(this, { state: "exit-resolved", status });
        return;
      }

      const resolveRes = await this.withTimeout(
        apiClient.resolveRunNode(this.runId, this.nodeId),
        this.resolveTimeoutMs,
        "resolve-node"
      ).catch((error: unknown) => {
        const message = (error as Error)?.message ?? "";
        if (/timeout/i.test(message)) {
          throw error;
        }
        const debugConfig = getDebugSceneConfig();
        if (!debugConfig.enabled) {
          throw new Error("Failed to resolve");
        }
        return getDebugResolvedNodeFixture();
      });
      if (!resolveRes.ok) {
        const reason = String(resolveRes.error.message ?? "Unknown error");
        if (/no[\s_-]*enemies/i.test(reason)) {
          await this.handleNoEnemiesResolution(reason);
          return;
        }
        this.setStatusBanner("Resolution failed", "The encounter could not be resolved cleanly. You can retry or return to the map.");
        this.renderStatePlaceholder(
          "Encounter report unavailable",
          ["No battle report was produced for this node.", "Use Retry Resolve to attempt the server call again."],
          0xffb2b2,
          0x341d22
        );
        this.showError(`Resolve failed: ${reason}`);
        this.configureButton("Back to Map", true, () => this.returnToMap());
        markDebugSceneReady(this, { state: "error", reason });
        return;
      }

      const outcome = resolveRes.data.battle.outcome;
      const battleId = String(resolveRes.data.battle.battle_id);
      const debugConfig = getDebugSceneConfig();
      const claimRes = await this.withTimeout(
        apiClient.claimBattleRewards(battleId),
        this.resolveTimeoutMs,
        "claim-battle"
      ).catch((error: unknown) => {
        if (!debugConfig.enabled) {
          throw error;
        }
        return {
          ok: true,
          data: {
            battle_id: battleId,
            status: "claimed",
            rewards: {
              currency_soft: 25,
              xp_total: 20,
            },
            updated_units: [],
          },
        };
      });
      if (!claimRes.ok) {
        if (debugConfig.enabled) {
          const fallbackClaimSummary = {
            rewards: ["Teeth +25", "Unit XP Award +20 each"],
            progression: [],
          };
          const unlockedMsg = formatUnlockedNodes(resolveRes.data.next.unlocked_node_ids);
          this.setStatusBanner(
            String(outcome).toUpperCase(),
            `Battle ${battleId} resolved in ${Number(resolveRes.data.battle.rounds)} rounds and ${Number(resolveRes.data.battle.ticks)} ticks.`
          );
          const summary: ResolutionSummary = {
            battleId,
            outcome,
            rounds: Number(resolveRes.data.battle.rounds),
            ticks: Number(resolveRes.data.battle.ticks),
            unlockedMsg,
            encounterDescription: this.readEncounterDescription(resolveRes.data.battle.log),
          };
          if (this.nodeType === "loot") {
            this.renderLootResolutionPanels(resolveRes.data.battle.log, summary, fallbackClaimSummary);
          } else {
            this.renderResolutionPanels(resolveRes.data.battle.log, summary);
          }
          this.configureButton("Back to Map", true, () => {
            this.scene.start("MapExplorationScene", {
              resolutionMessage: `Node ${this.nodeId} resolved (${outcome}).`,
              resolutionColor: outcome === "victory" ? "#ccffcc" : "#ffd89e",
            });
          });
          markDebugSceneReady(this, { state: "resolved", outcome, fallback: "debug-claim" });
          return;
        }
        this.setStatusBanner("Rewards unavailable", "The battle finished, but the reward claim step failed. Retry the encounter resolution to resync the reward state.");
        this.renderStatePlaceholder(
          "Reward claim failed",
          ["No reward receipt could be generated for this encounter.", "Retry Resolve will attempt the claim step again."],
          0xffd89e,
          0x352d1a
        );
        const claimErrorMessage = (claimRes as { error?: { message?: string } }).error?.message ?? "Unknown error";
        this.showError(`Reward claim failed: ${claimErrorMessage}`);
        this.configureButton("Retry Resolve", true, () => {
          this.hasResolved = false;
          void this.resolveNode();
        });
        return;
      }

      const claimSummary = buildClaimSummary(claimRes.data as Record<string, unknown>);
      const unlockedMsg = formatUnlockedNodes(resolveRes.data.next.unlocked_node_ids);
      this.setStatusBanner(
        String(outcome).toUpperCase(),
        `Battle ${battleId} resolved in ${Number(resolveRes.data.battle.rounds)} rounds and ${Number(resolveRes.data.battle.ticks)} ticks. ${unlockedMsg}`
      );
      try {
        const summary: ResolutionSummary = {
          battleId,
          outcome,
          rounds: Number(resolveRes.data.battle.rounds),
          ticks: Number(resolveRes.data.battle.ticks),
          unlockedMsg,
          encounterDescription: this.readEncounterDescription(resolveRes.data.battle.log),
        };
        if (this.nodeType === "loot") {
          this.renderLootResolutionPanels(resolveRes.data.battle.log, summary, claimSummary);
        } else {
          this.renderResolutionPanels(resolveRes.data.battle.log, summary);
        }
      } catch {
        this.detailText?.setText("Battle details unavailable.");
      }

      const refreshed = await this.withTimeout(
        apiClient.getCurrentRun(),
        this.resolveTimeoutMs,
        "refresh-current-run"
      ).catch((error: unknown) => {
        if (!debugConfig.enabled) {
          throw error;
        }
        return {
          ok: true,
          data: {
            run: {
              run_id: this.runId,
            },
          },
        };
      });
      if (refreshed.ok && refreshed.data.run === null) {
        const status = deriveSummaryStatus({
          nodeType: this.nodeType,
          outcome,
        });
        this.configureButton("Continue", true, () => {
          this.scene.start("RunEndSummaryScene", {
            status,
            rewards: claimSummary.rewards,
            progression: claimSummary.progression,
            survivors: [],
            defeated: [],
          });
        });
        markDebugSceneReady(this, { state: "terminal", outcome });
        return;
      }

      this.configureButton("Back to Map", true, () => {
        const rewardTeaser = claimSummary.rewards.find((line) => !line.startsWith("-")) ?? "";
        this.scene.start("MapExplorationScene", {
          resolutionMessage: rewardTeaser
            ? `Node ${this.nodeId} resolved (${outcome}). ${rewardTeaser}`
            : `Node ${this.nodeId} resolved (${outcome}).`,
          resolutionColor: outcome === "victory" ? "#ccffcc" : "#ffd89e",
        });
      });
      markDebugSceneReady(this, { state: "resolved", outcome });
    } catch (error) {
      const message = (error as Error)?.message ?? "";
      const timedOut = /timeout/i.test(message);
      this.hasResolved = false;
      if (timedOut) {
        this.setStatusBanner("Resolution timed out", "The encounter took too long to respond. Retry to rebuild the battle report or return to the map.");
        this.renderStatePlaceholder(
          "Encounter report timed out",
          ["No timeline was returned before the timeout window expired.", "Retry Resolve will request the report again."],
          0xffd89e,
          0x352d1a
        );
        this.showError("Resolution timed out. Retry or return to map.");
        this.configureButton("Retry Resolve", true, () => {
          this.hasResolved = false;
          void this.resolveNode();
        });
      } else {
        this.setStatusBanner("Node resolution unavailable", "The encounter report could not be loaded. Return to the map or retry the node resolution.");
        this.renderStatePlaceholder(
          "Encounter unavailable",
          ["The game could not load a readable battle report for this node."],
          0xffb2b2,
          0x341d22
        );
        this.showError("Node resolution unavailable. Please retry.");
        this.configureButton("Back to Map", true, () => this.returnToMap());
      }
      markDebugSceneReady(this, { state: "error" });
    }
  }

  private withTimeout<T>(promise: Promise<T>, timeoutMs: number, label: string): Promise<T> {
    return new Promise<T>((resolve, reject) => {
      const timer = setTimeout(() => {
        reject(new Error(`${label} timeout`));
      }, timeoutMs);
      promise
        .then((value) => {
          clearTimeout(timer);
          resolve(value);
        })
        .catch((error: unknown) => {
          clearTimeout(timer);
          reject(error);
        });
    });
  }

  private async handleNoEnemiesResolution(reason: string): Promise<void> {
    this.setStatusBanner("No enemies", "This encounter resolved without combat.");
    try {
      this.renderResolutionPanels(null, {
        battleId: "n/a",
        outcome: "no_enemies",
        rounds: 0,
        ticks: 0,
        unlockedMsg: "Encounter resolved without combat.",
      });
    } catch {
      this.detailText?.setText("Encounter resolved without combat.");
    }
    this.showError(`Reason: ${reason}`);

    try {
      const refreshed = await apiClient.getCurrentRun();
      if (refreshed.ok && refreshed.data.map?.nodes) {
        const node = refreshed.data.map.nodes.find((candidate) => String(candidate.id) === this.nodeId);
        if (node && String(node.status) === "cleared") {
          try {
            this.renderResolutionPanels(null, {
              battleId: "n/a",
              outcome: "no_enemies",
              rounds: 0,
              ticks: 0,
              unlockedMsg: "Encounter resolved without combat. Node status is now cleared.",
            });
          } catch {
            this.detailText?.setText("Encounter resolved without combat. Node status is now cleared.");
          }
        }
      }
    } catch {
      // Fallback to reason-only message when refresh is unavailable.
    }

    this.configureButton("Back to Map", true, () => {
      this.scene.start("MapExplorationScene", {
        resolutionMessage: `Node ${this.nodeId} resolved: ${reason}`,
        resolutionColor: "#ffd89e",
      });
    });
    markDebugSceneReady(this, { state: "resolved-no-enemies", reason });
  }

  private configureButton(label: string, enabled: boolean, onClick: () => void): void {
    this.actionHandler = onClick;
    this.actionButton?.setText(label).setEnabled(enabled);
  }

  private showError(message: string): void {
    if (this.errorCard && "setVisible" in this.errorCard && typeof this.errorCard.setVisible === "function") {
      this.errorCard.setVisible(true);
    }
    this.errorText?.setText(message);
  }

  private clearError(): void {
    if (this.errorCard && "setVisible" in this.errorCard && typeof this.errorCard.setVisible === "function") {
      this.errorCard.setVisible(false);
    }
    this.errorText?.setText("");
  }

  private setStatusBanner(title: string, detail: string): void {
    this.statusText?.setText(title);
    this.detailText?.setText(detail);
  }

  private returnToMap(): void {
    this.scene.start("MapExplorationScene");
  }

  private renderStatePlaceholder(title: string, bodyLines: string[], accentColor: number, panelColor: number): void {
    this.clearResolutionPanels();
    const layout = getPageLayout(this);
    const cardX = layout.content.x + 24;
    const cardY = layout.content.y + CONTENT_BODY_TOP_OFFSET + STATUS_CARD_HEIGHT + RESOLUTION_BODY_TOP_GAP;
    const cardWidth = Math.max(260, layout.content.width - 48);
    const cardHeight = Math.max(150, layout.content.height - CONTENT_BODY_TOP_OFFSET - STATUS_CARD_HEIGHT - CONTENT_BODY_BOTTOM_PADDING - 96);

    const card = this.add
      .rectangle(cardX, cardY, cardWidth, cardHeight, panelColor, 0.62)
      .setOrigin(0, 0)
      .setStrokeStyle(1, accentColor, 0.6);
    const accent = this.add
      .rectangle(cardX + 16, cardY + 18, 6, cardHeight - 36, accentColor, 0.9)
      .setOrigin(0, 0);
    const titleText = this.add
      .text(cardX + 34, cardY + 18, title, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "22px",
        color: "#f1f4f5",
      })
      .setOrigin(0, 0);
    const bodyText = this.add
      .text(cardX + 34, cardY + 54, bodyLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "18px",
        color: "#d9e3e5",
        lineSpacing: 8,
        wordWrap: { width: cardWidth - 58 },
      })
      .setOrigin(0, 0);

    this.resolutionUiObjects.push(card, accent, titleText, bodyText);
  }

  private renderResolutionPanels(
    log: BattleLog,
    summary: ResolutionSummary,
    selectedTickOverride?: number,
  ): void {
    this.latestLog = log;
    this.latestSummary = summary;
    this.clearResolutionPanels();
    const layout = getPageLayout(this);
    const contentX = layout.content.x + 16;
    const contentY = layout.content.y + CONTENT_BODY_TOP_OFFSET + STATUS_CARD_HEIGHT + RESOLUTION_BODY_TOP_GAP;
    const contentWidth = Math.max(300, layout.content.width - 32);
    const contentHeight = Math.max(180, layout.content.height - CONTENT_BODY_TOP_OFFSET - STATUS_CARD_HEIGHT - CONTENT_BODY_BOTTOM_PADDING - 72);
    const sliderHeight = 90;
    const bodyY = contentY + sliderHeight;
    const bodyHeight = Math.max(120, contentHeight - sliderHeight - 6);

    const maxTick = this.deriveObservedMaxTick(log, Math.max(0, summary.ticks));
    const defaultTick = deriveDefaultTick(log);
    const desiredTick = selectedTickOverride ?? (this.selectedTick > 0 ? this.selectedTick : defaultTick);
    this.selectedTick = Phaser.Math.Clamp(desiredTick, 0, maxTick);
    this.debugBattleLogButton?.setEnabled(Boolean(log));

    const sliderInset = 10;
    this.renderTickSlider(
      contentX + sliderInset,
      contentY,
      Math.max(140, contentWidth - sliderInset * 2),
      sliderHeight - 8,
      maxTick,
      this.selectedTick,
      log
    );

    const gap = 14;
    const sideWidth = Math.max(180, Math.floor((contentWidth - gap * 2) / 3));
    const leftWidth = sideWidth;
    const rightWidth = sideWidth;
    const centerWidth = Math.max(220, contentWidth - leftWidth - rightWidth - gap * 2);

    const leftX = contentX;
    const centerX = leftX + leftWidth + gap;
    const rightX = centerX + centerWidth + gap;

    const centerPanel = this.add
      .rectangle(centerX, bodyY, centerWidth, bodyHeight, 0x7c1018, 0.4)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xff7c88, 0.75);

    const allyTitle = this.add
      .text(leftX + 8, bodyY + 8, "ALLIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f4f4f4",
      })
      .setOrigin(0, 0);
    const enemyTitle = this.add
      .text(rightX + 8, bodyY + 8, "ENEMIES", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f4f4f4",
      })
      .setOrigin(0, 0);

    const allyUnits = this.extractParticipants(log, "player", 9);
    const enemyUnits = this.extractParticipants(log, "enemy", 9);
    const hpSnapshot = this.computeHpSnapshot(log, this.selectedTick);
    const selectedRound = this.resolveRoundNumber(log, this.selectedTick);
    const statusSnapshot = this.computeStatusSnapshot(log, this.selectedTick, selectedRound);
    const actedThisTick = this.computeActorsForTick(log, this.selectedTick);
    const damageThisTick = this.computeDamageForTick(log, this.selectedTick);

    this.createFormationGrid(
      leftX + 10,
      bodyY + 34,
      leftWidth - 20,
      bodyHeight - 44,
      allyUnits.map((unit, index) => ({
        id: unit.id,
        display: String(index + 1),
        pos: unit.pos,
        formation: unit.formation,
        currentHp: this.readCurrentHp(unit.id, unit.maxHp, hpSnapshot),
        maxHp: unit.maxHp,
        hpPercent: this.readHpPercent(unit.id, unit.maxHp, hpSnapshot),
        statuses: statusSnapshot[unit.id] ?? [],
        attacked: actedThisTick.has(unit.id),
        damageTaken: damageThisTick.get(unit.id) ?? 0,
      }))
    );

    this.createFormationGrid(
      rightX + 10,
      bodyY + 34,
      rightWidth - 20,
      bodyHeight - 44,
      enemyUnits.map((unit) => ({
        id: unit.id,
        display: shortenEnemyLabel(unit.display),
        pos: unit.pos,
        formation: unit.formation,
        currentHp: this.readCurrentHp(unit.id, unit.maxHp, hpSnapshot),
        maxHp: unit.maxHp,
        hpPercent: this.readHpPercent(unit.id, unit.maxHp, hpSnapshot),
        statuses: statusSnapshot[unit.id] ?? [],
        attacked: actedThisTick.has(unit.id),
        damageTaken: damageThisTick.get(unit.id) ?? 0,
      }))
    );

    this.detailText?.destroy();
    const viewportX = centerX + 10;
    const viewportY = bodyY + 10;
    const viewportWidth = Math.max(120, centerWidth - 20);
    const viewportHeight = Math.max(80, bodyHeight - 20);
    this.logViewport = { x: viewportX, y: viewportY, width: viewportWidth, height: viewportHeight };

    const tickEvents = this.extractEventsForTick(log, this.selectedTick);
    const summaryLines = this.buildTickSummaryLines(summary, tickEvents);

    this.detailText = this.add
      .text(viewportX, viewportY, summaryLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#ffe6ea",
        lineSpacing: 4,
        wordWrap: { width: Math.max(120, viewportWidth) },
      })
      .setOrigin(0, 0);

    this.logScrollOffset = 0;
    this.detailText.setY(viewportY);
    this.logMaskGraphics?.destroy();
    this.logMaskGraphics = this.add.graphics();
    this.logMaskGraphics.fillStyle(0xffffff, 1);
    this.logMaskGraphics.fillRect(viewportX, viewportY, viewportWidth, viewportHeight);
    this.logMaskGraphics.visible = false;
    this.detailText.setMask(this.logMaskGraphics.createGeometryMask());

    this.resolutionUiObjects.push(centerPanel, allyTitle, enemyTitle, this.detailText, this.logMaskGraphics);
  }

  private renderTickSlider(
    x: number,
    y: number,
    width: number,
    height: number,
    maxTick: number,
    selectedTick: number,
    log: BattleLog,
  ): void {
    const selectableTicks = this.getNonEmptyTicks(log, maxTick);
    const timelineTicks = selectableTicks.length > 0 ? selectableTicks : [0];
    const controlY = y + 26;
    const trackY = y + 58;
    const trackHeight = 8;
    const controlGap = 8;
    const makeControl = (
      buttonX: number,
      buttonWidth: number,
      label: string,
      active: boolean,
      onClick: () => void,
    ): number => {
      const bg = this.add
        .rectangle(buttonX, controlY, buttonWidth, 24, active ? 0x587286 : 0x3b414f, 0.95)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.28)
        .setInteractive({ useHandCursor: true });
      const text = this.add
        .text(buttonX + buttonWidth / 2, controlY + 12, label, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: "#ffffff",
        })
        .setOrigin(0.5, 0.5);
      bg.on("pointerdown", () => onClick());
      this.resolutionUiObjects.push(bg, text);
      return buttonX + buttonWidth + controlGap;
    };

    const sliderTitle = this.add
      .text(x, y, `Tick ${selectedTick}/${maxTick}`, {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#f1f1f1",
      })
      .setOrigin(0, 0);
    this.resolutionUiObjects.push(sliderTitle);

    let nextControlX = x;
    nextControlX = makeControl(nextControlX, 48, "Prev", false, () => {
      this.stepSelectableTick(timelineTicks, -1);
    });
    nextControlX = makeControl(nextControlX, 58, this.tickPlaybackActive ? "Pause" : "Play", this.tickPlaybackActive, () => {
      this.toggleTickPlayback(maxTick, log);
    });
    nextControlX = makeControl(nextControlX, 48, "Next", false, () => {
      this.stepSelectableTick(timelineTicks, 1);
    });
    nextControlX = makeControl(nextControlX, 58, "Skip", false, () => {
      const finalTick = timelineTicks[timelineTicks.length - 1] ?? 0;
      if (this.latestSummary) {
        this.stopTickPlayback();
        this.renderResolutionPanels(this.latestLog, this.latestSummary, finalTick);
      }
    });

    let speedX = x + width - 150;
    for (const speed of TICK_PLAYBACK_SPEEDS) {
      const isActive = this.playbackSpeedMultiplier === speed;
      const currentSpeed = speed;
      const bg = this.add
        .rectangle(speedX, y, 42, 22, isActive ? 0x386f45 : 0x3b414f, 0.95)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xffffff, 0.25)
        .setInteractive({ useHandCursor: true });
      const text = this.add
        .text(speedX + 21, y + 11, `${speed}x`, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: "#ffffff",
        })
        .setOrigin(0.5, 0.5);
      bg.on("pointerdown", () => this.setPlaybackSpeed(currentSpeed, maxTick, log));
      this.resolutionUiObjects.push(bg, text);
      speedX += 48;
    }

    const trackX = nextControlX + 8;
    const trackWidth = Math.max(120, width - (trackX - x));
    const track = this.add
      .rectangle(trackX, trackY, trackWidth, trackHeight, 0x2a2f36, 0.9)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xffffff, 0.25);
    this.resolutionUiObjects.push(track);

    const positionForTick = (tick: number): number => {
      if (timelineTicks.length <= 1) {
        return trackX;
      }
      const index = Math.max(0, timelineTicks.indexOf(tick));
      const ratio = index / Math.max(1, timelineTicks.length - 1);
      return trackX + Math.round(ratio * trackWidth);
    };

    const setTickFromPointerX = (pointerX: number): void => {
      if (!this.latestSummary) {
        return;
      }
      const ratio = Phaser.Math.Clamp((pointerX - trackX) / Math.max(1, trackWidth), 0, 1);
      const candidateIndex = Math.round(ratio * Math.max(0, timelineTicks.length - 1));
      const nextTick = timelineTicks[Math.max(0, Math.min(candidateIndex, timelineTicks.length - 1))] ?? 0;
      if (nextTick === this.selectedTick) {
        return;
      }
      this.stopTickPlayback();
      this.renderResolutionPanels(this.latestLog, this.latestSummary, nextTick);
    };

    (track as unknown as { setInteractive?: (config?: unknown) => unknown }).setInteractive?.({ useHandCursor: true });
    (track as unknown as { on?: (event: string, cb: (...args: unknown[]) => void) => unknown }).on?.("pointerdown", (...args: unknown[]) => {
      const pointer = args[0] as Phaser.Input.Pointer;
      setTickFromPointerX(pointer.x);
    });
    (track as unknown as { on?: (event: string, cb: (...args: unknown[]) => void) => unknown }).on?.("pointermove", (...args: unknown[]) => {
      const pointer = args[0] as Phaser.Input.Pointer;
      if (pointer.isDown) {
        setTickFromPointerX(pointer.x);
      }
    });

    if (timelineTicks.length > 0) {
      for (const tick of timelineTicks) {
        const tickX = positionForTick(tick);
        const stop = this.add.rectangle(tickX, trackY - 3, 2, 14, 0xaeb5c2, 0.45).setOrigin(0.5, 0);
        this.resolutionUiObjects.push(stop);
      }
    }

    const roundStartTicks = this.extractRoundStartTicks(log);
    for (const roundTick of roundStartTicks) {
      if (roundTick < 0 || roundTick > maxTick) {
        continue;
      }
      const compactTick = this.coerceTickToSelectable(roundTick, timelineTicks, maxTick);
      const markerX = positionForTick(compactTick);
      const marker = this.add.rectangle(markerX, trackY - 10, 3, 24, 0xffd35b, 0.85).setOrigin(0.5, 0);
      this.resolutionUiObjects.push(marker);
    }

    const handleX = positionForTick(selectedTick);
    const handle = this.add
      .rectangle(handleX, trackY - 6, 10, 20, 0xffffff, 0.95)
      .setOrigin(0.5, 0)
      .setStrokeStyle(1, 0x1a1a1a, 0.7);
    this.resolutionUiObjects.push(handle);

    const helpText = this.add
      .text(x, y + height - 12, "Compact timeline view. Gold markers denote round starts.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "12px",
        color: "#d9d9d9",
      })
      .setOrigin(0, 1);
    this.resolutionUiObjects.push(helpText);
  }

  private createFormationGrid(
    x: number,
    y: number,
    width: number,
    height: number,
    entries: Array<{
      id: string;
      display: string;
      pos: { x: number; y: number } | null;
      currentHp: number;
      maxHp: number;
      hpPercent: number;
      formation: FormationFootprint;
      statuses: FormationStatusIndicator[];
      attacked: boolean;
      damageTaken: number;
    }>
  ): void {
    const gap = 6;
    const cellByWidth = Math.floor((width - gap * 2) / 3);
    const cellByHeight = Math.floor((height - gap * 2) / 3);
    const cellSize = Math.max(28, Math.min(cellByWidth, cellByHeight));

    const formation = this.buildFormationMap(entries.map((entry) => ({ id: entry.id, pos: entry.pos, formation: entry.formation })));
    const labelsById = new Map(entries.map((entry) => [entry.id, entry.display] as const));
    const hpTextById = new Map(entries.map((entry) => [entry.id, `${entry.currentHp}/${entry.maxHp}`] as const));
    const hpById = new Map(entries.map((entry) => [entry.id, entry.hpPercent] as const));
    const statusesById = new Map(entries.map((entry) => [entry.id, entry.statuses] as const));
    const attackedById = new Map(entries.map((entry) => [entry.id, entry.attacked] as const));
    const damageById = new Map(entries.map((entry) => [entry.id, entry.damageTaken] as const));
    const anchorByUnitId = new Map<string, FormationCellId>();
    for (const entry of entries) {
      const placedCells = Object.entries(formation)
        .filter(([, unitId]) => unitId === entry.id)
        .map(([cell]) => cell as FormationCellId);
      const anchor = anchorCellForCells(placedCells);
      if (anchor) {
        anchorByUnitId.set(entry.id, anchor);
      }
    }
    const grid = new FormationGrid3x3({
      scene: this,
      x,
      y,
      cellSize,
      gap,
      formation,
      allowSelection: false,
      selectedCell: null,
      onCellHover: (_cell, unitId, pointer) => {
        if (!unitId) {
          this.hideHoverHpTooltip();
          return;
        }
        const hpText = hpTextById.get(String(unitId));
        if (!hpText) {
          this.hideHoverHpTooltip();
          return;
        }
        this.showHoverHpTooltip(pointer.x, pointer.y, `HP ${hpText}`);
      },
      onCellOut: () => {
        this.hideHoverHpTooltip();
      },
      getCellLabel: (_cell, unitId) => {
        if (!unitId) return "";
        if (anchorByUnitId.get(String(unitId)) !== _cell) return "";
        return labelsById.get(String(unitId)) ?? String(unitId);
      },
      getCellShowIcon: (_cell, unitId) => !!unitId && anchorByUnitId.get(String(unitId)) === _cell,
      getCellHpPercent: (_cell, unitId) => {
        if (!unitId) return null;
        if (anchorByUnitId.get(String(unitId)) !== _cell) return null;
        return hpById.get(String(unitId)) ?? null;
      },
      getCellStatusIndicators: (_cell, unitId) => {
        if (!unitId) return [];
        if (anchorByUnitId.get(String(unitId)) !== _cell) return [];
        return statusesById.get(String(unitId)) ?? [];
      },
      getCellOutlineColor: (_cell, unitId) => {
        if (!unitId) return null;
        return attackedById.get(String(unitId)) ? 0xffd35b : null;
      },
      getCellDamageText: (_cell, unitId) => {
        if (!unitId) return null;
        if (anchorByUnitId.get(String(unitId)) !== _cell) return null;
        const damage = damageById.get(String(unitId)) ?? 0;
        if (damage <= 0) return null;
        return `-${damage}`;
      },
      colors: {
        cellFill: 0x0f0f0f,
        cellFillAlpha: 0.88,
        stroke: 0xffffff,
        strokeAlpha: 0.55,
        selectedStroke: 0xffffff,
        selectedStrokeAlpha: 0.55,
        text: "#f2f2f2",
      },
    });
    this.resolutionUiObjects.push(grid);
  }

  private showHoverHpTooltip(x: number, y: number, text: string): void {
    if (!this.hoverHpTooltip) {
      this.hoverHpTooltip = this.add
        .text(0, 0, text, {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "12px",
          color: "#ffffff",
          backgroundColor: "#10151b",
          padding: { x: 6, y: 3 },
        })
        .setOrigin(0, 1)
        .setDepth(2500)
        .setVisible(false);
      this.resolutionUiObjects.push(this.hoverHpTooltip);
    }

    this.hoverHpTooltip
      .setText(text)
      .setPosition(x + 10, y - 8)
      .setVisible(true);
  }

  private hideHoverHpTooltip(): void {
    this.hoverHpTooltip?.setVisible(false);
  }

  private buildFormationMap(entries: Array<{ id: string; pos: { x: number; y: number } | null; formation: FormationFootprint }>): Partial<FormationMap> {
    const cells: Array<keyof FormationMap> = ["A1", "A2", "A3", "B1", "B2", "B3", "C1", "C2", "C3"];
    const formation: Partial<FormationMap> = {};
    const assignedIds = new Set<string>();

    for (const entry of entries) {
      const cell = this.posToCell(entry.pos);
      if (!cell || assignedIds.has(entry.id)) {
        continue;
      }
      const occupiedCells = occupiedCellsFromAnchor(cell as FormationCellId, entry.formation);
      if (!occupiedCells) {
        continue;
      }
      const canOccupy = occupiedCells.every((occupiedCell) => !formation[occupiedCell]);
      if (canOccupy) {
        for (const occupiedCell of occupiedCells) {
          formation[occupiedCell] = entry.id;
        }
        assignedIds.add(entry.id);
      }
    }

    for (const entry of entries) {
      if (assignedIds.has(entry.id)) {
        continue;
      }
      const openCell = cells.find((cell) => {
        const occupiedCells = occupiedCellsFromAnchor(cell as FormationCellId, entry.formation);
        return occupiedCells !== null && occupiedCells.every((occupiedCell) => !formation[occupiedCell]);
      });
      if (!openCell) {
        break;
      }
      const occupiedCells = occupiedCellsFromAnchor(openCell as FormationCellId, entry.formation);
      if (!occupiedCells) {
        continue;
      }
      for (const occupiedCell of occupiedCells) {
        formation[occupiedCell] = entry.id;
      }
      assignedIds.add(entry.id);
    }

    return formation;
  }

  private posToCell(pos: { x: number; y: number } | null): keyof FormationMap | null {
    if (!pos || !Number.isFinite(pos.x) || !Number.isFinite(pos.y)) {
      return null;
    }

    const x = Math.max(0, Math.min(2, Math.floor(pos.x)));
    const y = Math.max(0, Math.min(2, Math.floor(pos.y)));
    const row = ["A", "B", "C"][y] ?? null;
    if (!row) {
      return null;
    }
    return `${row}${3 - x}` as keyof FormationMap;
  }

  private extractParticipants(
    log: BattleLog,
    side: "player" | "enemy",
    max: number
  ): ParticipantView[] {
    const participants = log
      && typeof log.meta === "object"
      && log.meta !== null
      && typeof (log.meta as Record<string, unknown>).participants === "object"
      && (log.meta as Record<string, unknown>).participants !== null
      ? ((log.meta as Record<string, unknown>).participants as Record<string, unknown>)
      : null;
    const list = participants && Array.isArray(participants[side]) ? participants[side] : [];
    const units: ParticipantView[] = [];
    for (const entry of list.slice(0, max)) {
      if (!entry || typeof entry !== "object") continue;
      const record = entry as Record<string, unknown>;
      const id = String(side === "player"
        ? String(record.unit_instance_id ?? "unit")
        : String(record.slug ?? "enemy"));
      const maxHpRaw = record.max_hp;
      const maxHp = typeof maxHpRaw === "number" ? maxHpRaw : Number(maxHpRaw ?? 1);
      units.push({
        id,
        display: side === "player" ? id : prettifyEnemySlug(id),
        maxHp: Number.isFinite(maxHp) && maxHp > 0 ? maxHp : 1,
        pos: this.readParticipantPos(record),
        formation: this.readParticipantFormation(record),
      });
    }
    return units;
  }

  private readParticipantFormation(record: Record<string, unknown>): FormationFootprint {
    const raw = record.formation;
    if (!raw || typeof raw !== "object") {
      return { w: 1, h: 1 };
    }
    const formation = raw as Record<string, unknown>;
    const width = typeof formation.w === "number" ? formation.w : Number(formation.w ?? NaN);
    const height = typeof formation.h === "number" ? formation.h : Number(formation.h ?? NaN);
    return {
      w: Number.isFinite(width) ? Math.max(1, Math.min(3, Math.floor(width))) : 1,
      h: Number.isFinite(height) ? Math.max(1, Math.min(3, Math.floor(height))) : 1,
    };
  }

  private readParticipantPos(record: Record<string, unknown>): { x: number; y: number } | null {
    const raw = record.pos;
    if (!raw || typeof raw !== "object") {
      return null;
    }
    const pos = raw as Record<string, unknown>;
    const x = typeof pos.x === "number" ? pos.x : Number(pos.x ?? NaN);
    const y = typeof pos.y === "number" ? pos.y : Number(pos.y ?? NaN);
    if (!Number.isFinite(x) || !Number.isFinite(y)) {
      return null;
    }
    return {
      x: Math.max(0, Math.min(2, Math.floor(x))),
      y: Math.max(0, Math.min(2, Math.floor(y))),
    };
  }

  private extractEventsForTick(log: BattleLog, tick: number): Array<Record<string, unknown>> {
    if (!log || !Array.isArray(log.events)) {
      return [];
    }

    return log.events.filter((event): event is Record<string, unknown> => {
      if (!event || typeof event !== "object") return false;
      const eventTick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? -1);
      return Number.isFinite(eventTick) && eventTick === tick;
    });
  }

  private extractRoundStartTicks(log: BattleLog): number[] {
    if (!log || !Array.isArray(log.events)) {
      return [];
    }

    const ticks: number[] = [];
    for (const event of log.events) {
      if (!event || typeof event !== "object") continue;
      const rec = event as Record<string, unknown>;
      if (rec.type !== "phase_start" || rec.phase !== "round_start") continue;
      const tick = typeof rec.tick === "number" ? rec.tick : Number(rec.tick ?? -1);
      if (Number.isFinite(tick) && tick >= 0) {
        ticks.push(tick);
      }
    }
    return ticks;
  }

  private resolveRoundNumber(log: BattleLog, selectedTick: number): number {
    const starts = this.extractRoundStartTicks(log).sort((a, b) => a - b);
    if (starts.length === 0) {
      return 0;
    }

    let round = 1;
    for (let i = 0; i < starts.length; i += 1) {
      const startTick = starts[i];
      if (typeof startTick !== "number") {
        continue;
      }
      if (selectedTick >= startTick) {
        round = i + 1;
      }
    }

    return round;
  }

  private computeStatusSnapshot(log: BattleLog, upToTick: number, selectedRound: number): UnitStatusSnapshot {
    const snapshot: Record<string, Record<string, number>> = {};
    if (!log || !Array.isArray(log.events) || selectedRound <= 0) {
      return {};
    }

    const roundStarts = this.extractRoundStartTicks(log).sort((a, b) => a - b);

    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      if (String(event.type ?? "") !== "action") continue;

      const tick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? NaN);
      if (!Number.isFinite(tick) || tick > upToTick) continue;

      const status = typeof event.status_applied === "string" ? event.status_applied : null;
      const durationRaw = event.status_duration_rounds;
      const duration = typeof durationRaw === "number" ? durationRaw : Number(durationRaw ?? NaN);
      if (!status || !Number.isFinite(duration) || duration <= 0) continue;

      const enemyTarget = typeof event.target_enemy_slug === "string" ? event.target_enemy_slug : null;
      const allyTarget = typeof event.target_unit_instance_id === "string" ? event.target_unit_instance_id : null;
      const targetId = enemyTarget ?? allyTarget;
      if (!targetId) continue;

      const eventRoundRaw = event.round;
      const eventRound = typeof eventRoundRaw === "number"
        ? eventRoundRaw
        : this.resolveRoundByTick(Number(tick), roundStarts);
      if (!Number.isFinite(eventRound) || eventRound <= 0) continue;

      const elapsedRounds = Math.max(0, selectedRound - eventRound);
      const remaining = Math.floor(duration - elapsedRounds);
      if (remaining <= 0) continue;

      if (!snapshot[targetId]) {
        snapshot[targetId] = {};
      }
      snapshot[targetId][status.toLowerCase()] = remaining;
    }

    const indicators: UnitStatusSnapshot = {};
    for (const [unitId, statuses] of Object.entries(snapshot)) {
      indicators[unitId] = Object.entries(statuses)
        .slice(0, 3)
        .map(([status, remaining]) => ({
          label: String(remaining),
          color: this.getStatusColor(status),
        }));
    }
    return indicators;
  }

  private resolveRoundByTick(tick: number, roundStarts: number[]): number {
    if (!Number.isFinite(tick) || roundStarts.length === 0) {
      return 0;
    }

    let round = 1;
    for (let i = 0; i < roundStarts.length; i += 1) {
      const startTick = roundStarts[i];
      if (typeof startTick !== "number") {
        continue;
      }
      if (tick >= startTick) {
        round = i + 1;
      }
    }
    return round;
  }

  private getStatusColor(status: string): number {
    const normalized = status.toLowerCase();
    if (normalized.includes("poison")) return 0x55b35f;
    if (normalized.includes("burn")) return 0xc87938;
    if (normalized.includes("bleed")) return 0xbf4a4a;
    if (normalized.includes("stun")) return 0xb58ad6;
    if (normalized.includes("slow")) return 0x4f85bf;
    return 0x6a798f;
  }

  private getNonEmptyTicks(log: BattleLog, maxTick: number): number[] {
    if (!log || !Array.isArray(log.events) || maxTick <= 0) {
      return [];
    }

    const uniqueTicks = new Set<number>();
    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      const tick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? NaN);
      if (!Number.isFinite(tick) || tick < 0 || tick > maxTick) continue;
      uniqueTicks.add(Math.floor(tick));
    }

    return Array.from(uniqueTicks).sort((a, b) => a - b);
  }

  private deriveObservedMaxTick(log: BattleLog, fallbackMaxTick: number): number {
    if (!log || !Array.isArray(log.events)) {
      return fallbackMaxTick;
    }

    let maxObservedTick = 0;
    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      const tick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? NaN);
      if (!Number.isFinite(tick) || tick < 0) continue;
      maxObservedTick = Math.max(maxObservedTick, Math.floor(tick));
    }

    return maxObservedTick > 0 ? maxObservedTick : fallbackMaxTick;
  }

  private coerceTickToSelectable(targetTick: number, selectableTicks: number[], maxTick: number): number {
    if (selectableTicks.length === 0) {
      return Phaser.Math.Clamp(targetTick, 0, maxTick);
    }

    let bestTick = selectableTicks[0] ?? 0;
    let bestDistance = Math.abs(bestTick - targetTick);
    for (const tick of selectableTicks) {
      const distance = Math.abs(tick - targetTick);
      if (distance < bestDistance) {
        bestTick = tick;
        bestDistance = distance;
      }
    }

    return bestTick;
  }

  private findNextNonEmptyTick(nonEmptyTicks: number[], currentTick: number): number | null {
    if (nonEmptyTicks.length === 0) {
      return null;
    }

    const firstTick = nonEmptyTicks[0];
    if (typeof firstTick !== "number") {
      return null;
    }

    const exactIndex = nonEmptyTicks.indexOf(currentTick);
    if (exactIndex >= 0) {
      if (exactIndex >= nonEmptyTicks.length - 1) {
        return null;
      }
      const next = nonEmptyTicks[exactIndex + 1];
      return typeof next === "number" ? next : null;
    }

    for (const tick of nonEmptyTicks) {
      if (tick > currentTick) {
        return tick;
      }
    }

    return null;
  }

  private toggleTickPlayback(maxTick: number, log: BattleLog): void {
    if (this.tickPlaybackActive) {
      this.stopTickPlayback();
      if (this.latestSummary) {
        this.renderResolutionPanels(this.latestLog, this.latestSummary, this.selectedTick);
      }
      return;
    }

    const ticks = this.getNonEmptyTicks(log, maxTick);
    if (ticks.length === 0 || !this.latestSummary) {
      return;
    }

    this.tickPlaybackActive = true;
    this.tickPlaybackTimer?.destroy();
    this.tickPlaybackTimer = this.time.addEvent({
      delay: this.getAutoplayDelayMs(),
      callback: () => {
        if (!this.tickPlaybackActive || !this.latestSummary) {
          return;
        }
        const nextTick = this.findNextNonEmptyTick(ticks, this.selectedTick);
        if (nextTick === null) {
          this.stopTickPlayback();
          this.renderResolutionPanels(this.latestLog, this.latestSummary, this.selectedTick);
          return;
        }
        this.renderResolutionPanels(this.latestLog, this.latestSummary, nextTick);
      },
      loop: true,
    });

    this.renderResolutionPanels(this.latestLog, this.latestSummary, this.selectedTick);
  }

  private stepSelectableTick(nonEmptyTicks: number[], delta: -1 | 1): void {
    if (!this.latestSummary || nonEmptyTicks.length === 0) {
      return;
    }

    const exactIndex = nonEmptyTicks.indexOf(this.selectedTick);
    const baseIndex = exactIndex >= 0 ? exactIndex : 0;
    const nextIndex = Phaser.Math.Clamp(baseIndex + delta, 0, nonEmptyTicks.length - 1);
    const nextTick = nonEmptyTicks[nextIndex] ?? this.selectedTick;
    this.stopTickPlayback();
    this.renderResolutionPanels(this.latestLog, this.latestSummary, nextTick);
  }

  private setPlaybackSpeed(multiplier: (typeof TICK_PLAYBACK_SPEEDS)[number], maxTick: number, log: BattleLog): void {
    this.playbackSpeedMultiplier = multiplier;
    if (this.tickPlaybackActive) {
      this.stopTickPlayback();
      this.toggleTickPlayback(maxTick, log);
      return;
    }
    if (this.latestSummary) {
      this.renderResolutionPanels(this.latestLog, this.latestSummary, this.selectedTick);
    }
  }

  private renderLootResolutionPanels(log: BattleLog, summary: ResolutionSummary, claimSummary: ClaimSummary): void {
    this.latestLog = log;
    this.latestSummary = summary;
    this.clearResolutionPanels();

    const layout = getPageLayout(this);
    const contentX = layout.content.x + 16;
    const contentY = layout.content.y + CONTENT_BODY_TOP_OFFSET + STATUS_CARD_HEIGHT + RESOLUTION_BODY_TOP_GAP;
    const contentWidth = Math.max(300, layout.content.width - 32);
    const contentHeight = Math.max(180, layout.content.height - CONTENT_BODY_TOP_OFFSET - STATUS_CARD_HEIGHT - CONTENT_BODY_BOTTOM_PADDING - 72);

    const gap = 14;
    const leftWidth = Math.max(220, Math.floor(contentWidth * 0.62));
    const rightWidth = Math.max(180, contentWidth - leftWidth - gap);
    const leftX = contentX;
    const rightX = leftX + leftWidth + gap;
    const bodyY = contentY;
    const bodyHeight = contentHeight;

    const receiptPanel = this.add
      .rectangle(leftX, bodyY, leftWidth, bodyHeight, 0x2b2020, 0.48)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0xf4d99c, 0.72);
    const lootPanel = this.add
      .rectangle(rightX, bodyY, rightWidth, bodyHeight, 0x15262d, 0.5)
      .setOrigin(0, 0)
      .setStrokeStyle(1, 0x9fd9ff, 0.72);

    const receiptTitle = this.add
      .text(leftX + 10, bodyY + 10, "Loot Receipt", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#ffe8b2",
      })
      .setOrigin(0, 0);

    const receiptLines = buildLootReceiptLines(summary, claimSummary);
    this.detailText?.destroy();
    this.detailText = this.add
      .text(leftX + 10, bodyY + 38, receiptLines.join("\n"), {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "13px",
        color: "#f3ede0",
        lineSpacing: 4,
        wordWrap: { width: Math.max(140, leftWidth - 20) },
      })
      .setOrigin(0, 0);

    const lootTitle = this.add
      .text(rightX + 10, bodyY + 10, "Recovered Cache", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "16px",
        color: "#d2eeff",
      })
      .setOrigin(0, 0);

    const visualCenterX = rightX + rightWidth / 2;
    const visualCenterY = bodyY + Math.max(74, Math.floor(bodyHeight * 0.5));
    if (this.textures.exists("icon_encounter_loot")) {
      const lootVisual = this.add
        .image(visualCenterX, visualCenterY, "icon_encounter_loot")
        .setDisplaySize(128, 128)
        .setAlpha(0.96);
      this.resolutionUiObjects.push(lootVisual);
    } else {
      const fallbackVisual = this.add
        .rectangle(visualCenterX - 50, visualCenterY - 50, 100, 100, 0x55707d, 0.65)
        .setOrigin(0, 0)
        .setStrokeStyle(1, 0xe8f4ff, 0.7);
      const fallbackText = this.add
        .text(visualCenterX, visualCenterY, "LOOT", {
          fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
          fontSize: "20px",
          color: "#ffffff",
        })
        .setOrigin(0.5, 0.5);
      this.resolutionUiObjects.push(fallbackVisual, fallbackText);
    }

    const caption = this.add
      .text(visualCenterX, bodyY + bodyHeight - 20, "Rewards secured from this node.", {
        fontFamily: '"IBM Plex Sans Condensed", "Roboto Condensed", Arial',
        fontSize: "12px",
        color: "#d8ecff",
      })
      .setOrigin(0.5, 1);

    this.resolutionUiObjects.push(receiptPanel, lootPanel, receiptTitle, this.detailText, lootTitle, caption);
  }

  private stopTickPlayback(): void {
    this.tickPlaybackActive = false;
    this.tickPlaybackTimer?.destroy();
    this.tickPlaybackTimer = undefined;
  }

  private buildTickSummaryLines(summary: ResolutionSummary, tickEvents: Array<Record<string, unknown>>): string[] {
    return buildTickSummaryLines(summary, this.selectedTick, tickEvents);
  }

  private getAutoplayDelayMs(): number {
    return Math.max(160, Math.round(TICK_AUTOPLAY_STEP_MS / this.playbackSpeedMultiplier));
  }

  private async copyBattleLogToClipboard(): Promise<void> {
    if (!this.latestLog) {
      this.showError("No battle log available to copy.");
      return;
    }

    try {
      const clipboard = typeof navigator !== "undefined" ? navigator.clipboard : undefined;
      if (!clipboard || typeof clipboard.writeText !== "function") {
        throw new Error("Clipboard access is unavailable in this browser.");
      }
      await clipboard.writeText(JSON.stringify(this.latestLog, null, 2));
      this.showError("Battle log copied to clipboard.");
    } catch (error) {
      this.showError(error instanceof Error ? error.message : "Failed to copy battle log.");
    }
  }

  private computeActorsForTick(log: BattleLog, tick: number): Set<string> {
    const actors = new Set<string>();
    if (!log || !Array.isArray(log.events)) {
      return actors;
    }

    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      if (String(event.type ?? "") !== "action") continue;
      const eventTick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? NaN);
      if (!Number.isFinite(eventTick) || eventTick !== tick) continue;

      const actor = typeof event.actor_unit_instance_id === "string"
        ? event.actor_unit_instance_id
        : typeof event.actor_enemy_slug === "string"
          ? event.actor_enemy_slug
          : null;
      if (actor) {
        actors.add(actor);
      }
    }

    return actors;
  }

  private computeDamageForTick(log: BattleLog, tick: number): Map<string, number> {
    const damageById = new Map<string, number>();
    if (!log || !Array.isArray(log.events)) {
      return damageById;
    }

    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      if (String(event.type ?? "") !== "action") continue;
      const eventTick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? NaN);
      if (!Number.isFinite(eventTick) || eventTick !== tick) continue;

      const damageRaw = event.damage;
      const damage = typeof damageRaw === "number" ? damageRaw : Number(damageRaw ?? NaN);
      if (!Number.isFinite(damage) || damage <= 0) continue;

      const target = typeof event.target_unit_instance_id === "string"
        ? event.target_unit_instance_id
        : typeof event.target_enemy_slug === "string"
          ? event.target_enemy_slug
          : null;
      if (!target) continue;

      const accumulated = damageById.get(target) ?? 0;
      damageById.set(target, accumulated + Math.max(0, Math.floor(damage)));
    }

    return damageById;
  }

  private readCurrentHp(id: string, maxHp: number, snapshot: Record<string, number>): number {
    const hp = snapshot[id];
    if (typeof hp !== "number" || !Number.isFinite(hp)) {
      return Math.max(0, Math.floor(maxHp));
    }
    return Math.max(0, Math.floor(hp));
  }

  private readEncounterDescription(log: BattleLog): string {
    if (!log || typeof log.meta !== "object" || log.meta === null) {
      return "";
    }
    const description = (log.meta as Record<string, unknown>).encounter_description;
    return typeof description === "string" ? description : "";
  }

  private computeHpSnapshot(log: BattleLog, upToTick: number): Record<string, number> {
    const snapshot: Record<string, number> = {};
    const participants = this.extractParticipants(log, "player", 99).concat(this.extractParticipants(log, "enemy", 99));
    participants.forEach((participant) => {
      snapshot[participant.id] = participant.maxHp;
    });

    if (!log || !Array.isArray(log.events)) {
      return snapshot;
    }

    for (const raw of log.events) {
      if (!raw || typeof raw !== "object") continue;
      const event = raw as Record<string, unknown>;
      if (String(event.type ?? "") !== "action") continue;
      const tick = typeof event.tick === "number" ? event.tick : Number(event.tick ?? 0);
      if (!Number.isFinite(tick) || tick > upToTick) continue;
      const hpAfterRaw = event.target_hp_after;
      const hpAfter = typeof hpAfterRaw === "number" ? hpAfterRaw : Number(hpAfterRaw ?? NaN);
      if (!Number.isFinite(hpAfter)) continue;

      const enemyTarget = typeof event.target_enemy_slug === "string" ? event.target_enemy_slug : null;
      const allyTarget = typeof event.target_unit_instance_id === "string" ? event.target_unit_instance_id : null;
      const targetId = enemyTarget ?? allyTarget;
      if (!targetId) continue;
      snapshot[targetId] = Math.max(0, hpAfter);
    }

    return snapshot;
  }

  private readHpPercent(id: string, maxHp: number, snapshot: Record<string, number>): number {
    const hp = snapshot[id] ?? maxHp;
    if (maxHp <= 0) return 0;
    return Phaser.Math.Clamp(hp / maxHp, 0, 1);
  }

  private clearResolutionPanels(): void {
    this.hideHoverHpTooltip();
    for (const obj of this.resolutionUiObjects) {
      obj.destroy();
    }
    this.resolutionUiObjects = [];
    this.hoverHpTooltip = undefined;
    this.logMaskGraphics = undefined;
    this.logViewport = null;
    this.logScrollOffset = 0;
  }

  private handleLogWheel(
    pointer: Phaser.Input.Pointer,
    _gameObjects: Phaser.GameObjects.GameObject[],
    _deltaX: number,
    deltaY: number
  ): void {
    if (!this.logViewport || !this.detailText) {
      return;
    }
    const withinX = pointer.x >= this.logViewport.x && pointer.x <= this.logViewport.x + this.logViewport.width;
    const withinY = pointer.y >= this.logViewport.y && pointer.y <= this.logViewport.y + this.logViewport.height;
    if (!withinX || !withinY) {
      return;
    }

    const textBounds = this.detailText.getBounds();
    const maxScroll = Math.max(0, textBounds.height - this.logViewport.height);
    if (maxScroll <= 0) {
      this.logScrollOffset = 0;
      this.detailText.setY(this.logViewport.y);
      return;
    }

    const direction = deltaY > 0 ? 1 : -1;
    this.logScrollOffset = Phaser.Math.Clamp(this.logScrollOffset + direction * 24, 0, maxScroll);
    this.detailText.setY(this.logViewport.y - this.logScrollOffset);
  }
}

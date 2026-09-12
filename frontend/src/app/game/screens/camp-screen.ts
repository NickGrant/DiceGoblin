import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';
import {
  Bounds,
  RuntimeViewport,
  RuntimeViewportSnapshot,
} from '../runtime/runtime-viewport';
import { GameSceneScreen } from './game-screen-navigation';
export type { GameSceneScreen } from './game-screen-navigation';

const CAMP_BANNER_KEY = 'camp-banner-cloth';
const TEETH_ICON_KEY = 'camp-teeth-icon';
const ENERGY_ICON_KEY = 'camp-energy-icon';

export interface CampViewModel {
  readonly displayName: string;
  readonly teeth: number;
  readonly teethText: string;
  readonly rawChaos: number;
  readonly rawChaosText: string;
  readonly energyCurrent: number;
  readonly energyNormalMaximum: number;
  readonly energyText: string;
  readonly isEnergyOvercap: boolean;
}

export interface CampLayout {
  readonly mode: RuntimeViewportSnapshot['layoutClass'];
  readonly centerX: number;
  readonly banner: Bounds;
  readonly panel: Bounds;
  readonly resourcePlaques: readonly [Bounds, Bounds, Bounds];
  readonly warbandButton: Bounds;
  readonly headingY: number;
  readonly welcomeY: number;
  readonly eyebrowY: number;
  readonly statusY: number;
  readonly dividerY: number;
  readonly showCampfire: boolean;
  readonly headingFontSize: number;
  readonly welcomeFontSize: number;
  readonly resourceLabelFontSize: number;
  readonly resourceValueFontSize: number;
}

export class CampStateUnavailableError extends Error {
  constructor() {
    super('Camp requires an authoritative bootstrap state.');
    this.name = 'CampStateUnavailableError';
  }
}

export function createCampViewModel(store: GameStore): CampViewModel {
  const bootstrap = store.bootstrap;
  if (!bootstrap) throw new CampStateUnavailableError();

  const { current, normal_max: normalMaximum } = bootstrap.player.energy;
  return {
    displayName: bootstrap.account.display_name,
    teeth: bootstrap.player.teeth,
    teethText: bootstrap.player.teeth.toLocaleString('en-US'),
    rawChaos: bootstrap.player.raw_chaos,
    rawChaosText: bootstrap.player.raw_chaos.toLocaleString('en-US'),
    energyCurrent: current,
    energyNormalMaximum: normalMaximum,
    energyText: `${current} / ${normalMaximum}`,
    isEnergyOvercap: current > normalMaximum,
  };
}

function box(x: number, y: number, width: number, height: number): Bounds {
  return { x, y, width, height, right: x + width, bottom: y + height };
}

/** Framework-neutral Camp regions derived only from the runtime viewport contract. */
export function createCampLayout(snapshot: RuntimeViewportSnapshot): CampLayout {
  const { safeBounds, layoutClass: mode } = snapshot;
  const outerMargin = mode === 'compact' ? 28 : mode === 'wide' ? 64 : 48;
  const usableWidth = Math.max(0, safeBounds.width - outerMargin * 2);
  const panelMaxWidth = mode === 'compact' ? 1740 : mode === 'wide' ? 1280 : 1120;
  const panelWidth = Math.min(panelMaxWidth, usableWidth);
  const centerX = safeBounds.x + safeBounds.width / 2;
  const panelY = Math.max(safeBounds.y + outerMargin, mode === 'compact' ? 132 : 150);
  const desiredPanelHeight = mode === 'compact' ? 520 : mode === 'wide' ? 520 : 500;
  const panelHeight = Math.max(
    320,
    Math.min(desiredPanelHeight, safeBounds.bottom - outerMargin - panelY),
  );
  const panel = box(centerX - panelWidth / 2, panelY, panelWidth, panelHeight);
  const resourceSideMargin = mode === 'compact' ? 34 : mode === 'wide' ? 64 : 56;
  const resourceGap = mode === 'wide' ? 22 : mode === 'compact' ? 14 : 18;
  const resourceHeight = mode === 'compact' ? 110 : 92;
  const resourceWidth =
    (panel.width - resourceSideMargin * 2 - resourceGap * 2) / 3;
  const resourceY = panel.bottom - resourceHeight - (mode === 'compact' ? 28 : 34);
  const resourceX = panel.x + resourceSideMargin;
  const resources = [0, 1, 2].map((index) =>
    box(resourceX + index * (resourceWidth + resourceGap), resourceY, resourceWidth, resourceHeight),
  ) as [Bounds, Bounds, Bounds];

  return {
    mode,
    centerX,
    banner: box(
      centerX - Math.min(1600, snapshot.logicalWidth) / 2,
      0,
      Math.min(1600, snapshot.logicalWidth),
      mode === 'compact' ? 128 : 144,
    ),
    panel,
    resourcePlaques: resources,
    warbandButton: box(
      panel.right - (mode === 'compact' ? 250 : 220) - 38,
      panel.y + 40,
      mode === 'compact' ? 250 : 220,
      mode === 'compact' ? 92 : 58,
    ),
    headingY: mode === 'compact' ? 43 : 50,
    welcomeY: mode === 'compact' ? 94 : 101,
    eyebrowY: panel.y + (mode === 'compact' ? 62 : 76),
    statusY: panel.y + (mode === 'compact' ? 101 : 118),
    dividerY: panel.y + (mode === 'compact' ? 145 : 170),
    showCampfire: true,
    headingFontSize: mode === 'compact' ? 48 : mode === 'wide' ? 44 : 42,
    welcomeFontSize: mode === 'compact' ? 22 : 18,
    resourceLabelFontSize: mode === 'compact' ? 20 : 13,
    resourceValueFontSize: mode === 'compact' ? 38 : 25,
  };
}

/** The initial GameScene-owned screen. It renders cached authority and performs no I/O. */
export class CampScreen implements GameSceneScreen {
  readonly key = 'camp' as const;
  private root: Phaser.GameObjects.Container | null = null;
  private view: CampViewModel | null = null;
  private activeLayout: CampLayout | null = null;

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly store: GameStore,
    private readonly viewport: RuntimeViewport,
    private readonly openWarband: () => void = () => undefined,
  ) {}

  static preload(scene: Phaser.Scene): void {
    if (!scene.textures.exists(CAMP_BANNER_KEY)) {
      scene.load.image(CAMP_BANNER_KEY, 'assets/ui/banner_background.jpg');
    }
    if (!scene.textures.exists(TEETH_ICON_KEY)) {
      scene.load.image(TEETH_ICON_KEY, 'assets/ui/icons/tooth_64.png');
    }
    if (!scene.textures.exists(ENERGY_ICON_KEY)) {
      scene.load.image(ENERGY_ICON_KEY, 'assets/ui/icons/energy_64.png');
    }
  }

  get layout(): CampLayout | null {
    return this.activeLayout;
  }

  get viewModel(): CampViewModel | null {
    return this.view;
  }

  create(): void {
    this.view = createCampViewModel(this.store);
    this.reflow(this.viewport.snapshot);
  }

  reflow(snapshot: RuntimeViewportSnapshot): void {
    if (!this.view) return;
    this.root?.destroy(true);
    this.root = null;
    const layout = createCampLayout(snapshot);
    this.activeLayout = layout;
    this.render(snapshot, layout, this.view);
  }

  destroy(): void {
    this.root?.destroy(true);
    this.root = null;
    this.activeLayout = null;
    this.view = null;
  }

  private render(
    snapshot: RuntimeViewportSnapshot,
    layout: CampLayout,
    view: CampViewModel,
  ): void {
    const root = this.scene.add.container(0, 0);
    root.setScale(snapshot.gameScale);
    this.root = root;

    const background = this.scene.add.graphics();
    background.fillGradientStyle(0x132c26, 0x193e32, 0x081b1d, 0x10282a, 1);
    background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
    background.fillStyle(0x8db341, 0.08);
    background.fillCircle(
      snapshot.logicalWidth * 0.18,
      snapshot.logicalHeight * 0.72,
      Math.min(snapshot.logicalWidth, snapshot.logicalHeight) * 0.42,
    );
    background.fillStyle(0x5c8fd8, 0.07);
    background.fillCircle(
      snapshot.logicalWidth * 0.84,
      snapshot.logicalHeight * 0.78,
      Math.min(snapshot.logicalWidth, snapshot.logicalHeight) * 0.5,
    );
    root.add(background);

    const bannerBacking = this.scene.add.graphics();
    bannerBacking.fillStyle(0x5b351f, 1);
    bannerBacking.fillRect(0, 0, snapshot.logicalWidth, layout.banner.height);
    root.add(bannerBacking);
    const banner = this.scene.add.image(layout.centerX, layout.banner.height / 2, CAMP_BANNER_KEY);
    banner.setDisplaySize(layout.banner.width, layout.banner.height).setAlpha(0.92);
    root.add(banner);

    const heading = this.scene.add
      .text(layout.centerX, layout.headingY, 'CAMP', {
        color: '#f5e8c8', fontFamily: 'Georgia, serif',
        fontSize: `${layout.headingFontSize}px`, fontStyle: 'bold',
        stroke: '#3a2a1a', strokeThickness: 7,
      })
      .setOrigin(0.5);
    const welcome = this.scene.add
      .text(layout.centerX, layout.welcomeY, `Welcome back, ${view.displayName}`, {
        color: '#fff4d3', fontFamily: 'system-ui, sans-serif',
        fontSize: `${layout.welcomeFontSize}px`, fontStyle: 'bold',
        stroke: '#3a2a1a', strokeThickness: 4,
      })
      .setOrigin(0.5);
    root.add([heading, welcome]);

    this.addPanel(root, layout.panel);
    this.addWarbandButton(root, layout);
    const eyebrow = this.scene.add
      .text(layout.centerX, layout.eyebrowY, 'THE GOBLINS ARE PLOTTING', {
        color: '#d65a43', fontFamily: 'system-ui, sans-serif',
        fontSize: layout.mode === 'compact' ? '19px' : '16px',
        fontStyle: 'bold', letterSpacing: 2,
      })
      .setOrigin(0.5);
    const status = this.scene.add
      .text(layout.centerX, layout.statusY, 'Supplies counted. Trouble pending.', {
        color: '#3a2a1a', fontFamily: 'Georgia, serif',
        fontSize: layout.mode === 'compact' ? '30px' : '27px', fontStyle: 'bold',
      })
      .setOrigin(0.5);
    root.add([eyebrow, status]);

    const divider = this.scene.add.graphics();
    divider.lineStyle(4, 0x8a5a34, 0.7);
    divider.lineBetween(layout.panel.x + 72, layout.dividerY, layout.panel.right - 72, layout.dividerY);
    divider.fillStyle(0xc9972b, 1);
    divider.fillCircle(layout.centerX, layout.dividerY, 8);
    root.add(divider);

    if (layout.showCampfire) {
      this.addCampfireMedallion(root, layout.centerX, layout.dividerY + 95);
    }

    const [teeth, chaos, energy] = layout.resourcePlaques;
    this.addResourcePlaque(root, teeth, 'TEETH', view.teethText, 0xf2c14e, TEETH_ICON_KEY, layout);
    this.addResourcePlaque(root, chaos, 'RAW CHAOS', view.rawChaosText, 0x9f66d8, null, layout);
    this.addResourcePlaque(
      root, energy, view.isEnergyOvercap ? 'ENERGY · OVERCHARGED' : 'ENERGY',
      view.energyText, view.isEnergyOvercap ? 0xf2c14e : 0x8db341, ENERGY_ICON_KEY, layout,
    );
  }

  private addWarbandButton(root: Phaser.GameObjects.Container, layout: CampLayout): void {
    const { x, y, width, height } = layout.warbandButton;
    const button = this.scene.add.graphics();
    button.fillStyle(0x244b3d, 1);
    button.fillRoundedRect(x, y, width, height, 14);
    button.lineStyle(4, 0xc9972b, 1);
    button.strokeRoundedRect(x, y, width, height, 14);
    button.setInteractive(new Phaser.Geom.Rectangle(x, y, width, height), Phaser.Geom.Rectangle.Contains);
    button.on('pointerup', this.openWarband);
    const label = this.scene.add.text(x + width / 2, y + height / 2, 'OPEN WARBAND  ›', {
      color: '#fff4d3', fontFamily: 'system-ui, sans-serif',
      fontSize: layout.mode === 'compact' ? '30px' : '17px', fontStyle: 'bold',
    }).setOrigin(0.5);
    root.add([button, label]);
  }

  private addPanel(root: Phaser.GameObjects.Container, region: Bounds): void {
    const panel = this.scene.add.graphics();
    panel.fillStyle(0x3a2417, 0.98);
    panel.fillRoundedRect(region.x - 10, region.y - 10, region.width + 20, region.height + 20, 28);
    panel.lineStyle(5, 0xc9972b, 1);
    panel.strokeRoundedRect(region.x - 5, region.y - 5, region.width + 10, region.height + 10, 24);
    panel.fillStyle(0x8a5a34, 1);
    panel.fillRoundedRect(region.x, region.y, region.width, region.height, 20);
    panel.fillStyle(0xf5e8c8, 0.96);
    panel.fillRoundedRect(region.x + 22, region.y + 22, region.width - 44, region.height - 44, 13);
    panel.lineStyle(3, 0x664326, 0.75);
    panel.strokeRoundedRect(region.x + 22, region.y + 22, region.width - 44, region.height - 44, 13);
    root.add(panel);
  }

  private addCampfireMedallion(root: Phaser.GameObjects.Container, centerX: number, centerY: number): void {
    const medallion = this.scene.add.graphics();
    medallion.fillStyle(0x8db341, 0.16);
    medallion.fillCircle(centerX, centerY, 58);
    medallion.lineStyle(3, 0xc9972b, 0.75);
    medallion.strokeCircle(centerX, centerY, 58);
    medallion.lineStyle(11, 0x5b351f, 1);
    medallion.lineBetween(centerX - 32, centerY + 28, centerX + 30, centerY + 12);
    medallion.lineBetween(centerX - 30, centerY + 12, centerX + 32, centerY + 28);
    medallion.fillStyle(0xd65a43, 1);
    medallion.fillTriangle(centerX, centerY - 49, centerX - 31, centerY + 17, centerX + 31, centerY + 17);
    medallion.fillStyle(0xf2c14e, 1);
    medallion.fillTriangle(centerX + 4, centerY - 27, centerX - 17, centerY + 16, centerX + 19, centerY + 16);
    medallion.fillStyle(0xfff1b5, 1);
    medallion.fillCircle(centerX + 1, centerY + 5, 9);
    medallion.fillStyle(0xf2c14e, 0.9);
    medallion.fillCircle(centerX - 44, centerY - 32, 4);
    medallion.fillCircle(centerX + 41, centerY - 42, 5);
    medallion.fillCircle(centerX + 51, centerY - 12, 3);
    root.add(medallion);
  }

  private addResourcePlaque(
    root: Phaser.GameObjects.Container,
    region: Bounds,
    label: string,
    value: string,
    accent: number,
    iconKey: string | null,
    layout: CampLayout,
  ): void {
    const iconCenterX = region.x + (layout.mode === 'compact' ? 51 : 44);
    const iconCenterY = region.y + region.height / 2;
    const plaque = this.scene.add.graphics();
    plaque.fillStyle(0x3a2a1a, 1);
    plaque.fillRoundedRect(region.x, region.y, region.width, region.height, 18);
    plaque.lineStyle(4, accent, 1);
    plaque.strokeRoundedRect(region.x + 2, region.y + 2, region.width - 4, region.height - 4, 16);
    plaque.fillStyle(accent, 1);
    plaque.fillCircle(iconCenterX, iconCenterY, layout.mode === 'compact' ? 31 : 27);
    plaque.lineStyle(3, 0xf5e8c8, 0.9);
    plaque.strokeCircle(iconCenterX, iconCenterY, layout.mode === 'compact' ? 25 : 22);
    root.add(plaque);

    if (iconKey) {
      const icon = this.scene.add.image(iconCenterX, iconCenterY, iconKey);
      const iconSize = layout.mode === 'compact' ? 42 : 36;
      icon.setDisplaySize(iconSize, iconSize);
      root.add(icon);
    } else {
      const gem = this.scene.add.graphics();
      gem.fillStyle(0xf5e8c8, 1);
      gem.fillTriangle(iconCenterX, iconCenterY - 24, iconCenterX - 15, iconCenterY, iconCenterX, iconCenterY + 20);
      gem.fillTriangle(iconCenterX, iconCenterY - 24, iconCenterX + 15, iconCenterY, iconCenterX, iconCenterY + 20);
      gem.lineStyle(2, 0x5d367c, 1);
      gem.lineBetween(iconCenterX, iconCenterY - 24, iconCenterX, iconCenterY + 20);
      root.add(gem);
    }

    const textX = region.x + (layout.mode === 'compact' ? 96 : 82);
    const labelText = this.scene.add.text(textX, region.y + (layout.mode === 'compact' ? 18 : 19), label, {
      color: '#c8b98f', fontFamily: 'system-ui, sans-serif',
      fontSize: `${layout.resourceLabelFontSize}px`, fontStyle: 'bold',
    });
    const valueText = this.scene.add.text(textX, region.y + (layout.mode === 'compact' ? 47 : 43), value, {
      color: '#f5e8c8', fontFamily: 'Georgia, serif',
      fontSize: `${layout.resourceValueFontSize}px`, fontStyle: 'bold',
    });
    root.add([labelText, valueText]);
  }
}

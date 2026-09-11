import Phaser from 'phaser';
import { GameStore } from '../runtime/game-store';

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

export interface GameSceneScreen {
  readonly key: 'camp';
  create(): void;
  destroy(): void;
}

export class CampStateUnavailableError extends Error {
  constructor() {
    super('Camp requires an authoritative bootstrap state.');
    this.name = 'CampStateUnavailableError';
  }
}

export function createCampViewModel(store: GameStore): CampViewModel {
  const bootstrap = store.bootstrap;
  if (!bootstrap) {
    throw new CampStateUnavailableError();
  }

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

/** The initial GameScene-owned screen. It renders cached authority and performs no I/O. */
export class CampScreen implements GameSceneScreen {
  readonly key = 'camp' as const;
  private root: Phaser.GameObjects.Container | null = null;

  constructor(
    private readonly scene: Phaser.Scene,
    private readonly store: GameStore,
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

  create(): void {
    this.destroy();
    const view = createCampViewModel(this.store);
    const width = this.scene.scale.width;
    const height = this.scene.scale.height;
    const centerX = width / 2;
    const root = this.scene.add.container(0, 0);
    this.root = root;

    const background = this.scene.add.graphics();
    background.fillGradientStyle(0x132c26, 0x193e32, 0x081b1d, 0x10282a, 1);
    background.fillRect(0, 0, width, height);
    background.fillStyle(0x8db341, 0.08);
    background.fillCircle(width * 0.18, height * 0.72, Math.min(width, height) * 0.42);
    background.fillStyle(0x5c8fd8, 0.07);
    background.fillCircle(width * 0.84, height * 0.78, Math.min(width, height) * 0.5);
    root.add(background);

    const banner = this.scene.add.image(centerX, 72, CAMP_BANNER_KEY);
    banner.setDisplaySize(width, 144).setAlpha(0.92);
    root.add(banner);

    const heading = this.scene.add
      .text(centerX, 50, 'CAMP', {
        color: '#f5e8c8',
        fontFamily: 'Georgia, serif',
        fontSize: '42px',
        fontStyle: 'bold',
        stroke: '#3a2a1a',
        strokeThickness: 7,
      })
      .setOrigin(0.5);
    const welcome = this.scene.add
      .text(centerX, 101, `Welcome back, ${view.displayName}`, {
        color: '#fff4d3',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '18px',
        fontStyle: 'bold',
        stroke: '#3a2a1a',
        strokeThickness: 4,
      })
      .setOrigin(0.5);
    root.add([heading, welcome]);

    const panelWidth = Math.min(1120, Math.max(720, width - 96));
    const panelHeight = Math.min(500, Math.max(330, height - 190));
    const panelX = centerX - panelWidth / 2;
    const panelY = 150;
    const panel = this.scene.add.graphics();
    panel.fillStyle(0x3a2417, 0.98);
    panel.fillRoundedRect(panelX - 10, panelY - 10, panelWidth + 20, panelHeight + 20, 28);
    panel.lineStyle(5, 0xc9972b, 1);
    panel.strokeRoundedRect(panelX - 5, panelY - 5, panelWidth + 10, panelHeight + 10, 24);
    panel.fillStyle(0x8a5a34, 1);
    panel.fillRoundedRect(panelX, panelY, panelWidth, panelHeight, 20);
    panel.fillStyle(0xf5e8c8, 0.96);
    panel.fillRoundedRect(panelX + 22, panelY + 22, panelWidth - 44, panelHeight - 44, 13);
    panel.lineStyle(3, 0x664326, 0.75);
    panel.strokeRoundedRect(panelX + 22, panelY + 22, panelWidth - 44, panelHeight - 44, 13);
    root.add(panel);

    const eyebrow = this.scene.add
      .text(centerX, panelY + 76, 'THE GOBLINS ARE PLOTTING', {
        color: '#d65a43',
        fontFamily: 'system-ui, sans-serif',
        fontSize: '16px',
        fontStyle: 'bold',
        letterSpacing: 2,
      })
      .setOrigin(0.5);
    const status = this.scene.add
      .text(centerX, panelY + 118, 'Supplies counted. Trouble pending.', {
        color: '#3a2a1a',
        fontFamily: 'Georgia, serif',
        fontSize: '27px',
        fontStyle: 'bold',
      })
      .setOrigin(0.5);
    root.add([eyebrow, status]);

    const divider = this.scene.add.graphics();
    const dividerY = panelY + 170;
    divider.lineStyle(4, 0x8a5a34, 0.7);
    divider.lineBetween(panelX + 72, dividerY, panelX + panelWidth - 72, dividerY);
    divider.fillStyle(0xc9972b, 1);
    divider.fillCircle(centerX, dividerY, 8);
    root.add(divider);

    this.addCampfireMedallion(root, centerX, dividerY + 95);

    const resourceY = panelY + panelHeight - 126;
    const resourceGap = 18;
    const resourceWidth = (panelWidth - 112 - resourceGap * 2) / 3;
    const resourceStartX = panelX + 56;
    this.addResourcePlaque(
      root,
      resourceStartX,
      resourceY,
      resourceWidth,
      'TEETH',
      view.teethText,
      0xf2c14e,
      TEETH_ICON_KEY,
    );
    this.addResourcePlaque(
      root,
      resourceStartX + resourceWidth + resourceGap,
      resourceY,
      resourceWidth,
      'RAW CHAOS',
      view.rawChaosText,
      0x9f66d8,
      null,
    );
    this.addResourcePlaque(
      root,
      resourceStartX + (resourceWidth + resourceGap) * 2,
      resourceY,
      resourceWidth,
      view.isEnergyOvercap ? 'ENERGY · OVERCHARGED' : 'ENERGY',
      view.energyText,
      view.isEnergyOvercap ? 0xf2c14e : 0x8db341,
      ENERGY_ICON_KEY,
    );
  }

  destroy(): void {
    this.root?.destroy(true);
    this.root = null;
  }

  private addCampfireMedallion(
    root: Phaser.GameObjects.Container,
    centerX: number,
    centerY: number,
  ): void {
    const medallion = this.scene.add.graphics();
    medallion.fillStyle(0x8db341, 0.16);
    medallion.fillCircle(centerX, centerY, 58);
    medallion.lineStyle(3, 0xc9972b, 0.75);
    medallion.strokeCircle(centerX, centerY, 58);
    medallion.lineStyle(11, 0x5b351f, 1);
    medallion.lineBetween(centerX - 32, centerY + 28, centerX + 30, centerY + 12);
    medallion.lineBetween(centerX - 30, centerY + 12, centerX + 32, centerY + 28);
    medallion.fillStyle(0xd65a43, 1);
    medallion.fillTriangle(
      centerX,
      centerY - 49,
      centerX - 31,
      centerY + 17,
      centerX + 31,
      centerY + 17,
    );
    medallion.fillStyle(0xf2c14e, 1);
    medallion.fillTriangle(
      centerX + 4,
      centerY - 27,
      centerX - 17,
      centerY + 16,
      centerX + 19,
      centerY + 16,
    );
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
    x: number,
    y: number,
    width: number,
    label: string,
    value: string,
    accent: number,
    iconKey: string | null,
  ): void {
    const height = 92;
    const plaque = this.scene.add.graphics();
    plaque.fillStyle(0x3a2a1a, 1);
    plaque.fillRoundedRect(x, y, width, height, 18);
    plaque.lineStyle(4, accent, 1);
    plaque.strokeRoundedRect(x + 2, y + 2, width - 4, height - 4, 16);
    plaque.fillStyle(accent, 1);
    plaque.fillCircle(x + 44, y + height / 2, 27);
    plaque.lineStyle(3, 0xf5e8c8, 0.9);
    plaque.strokeCircle(x + 44, y + height / 2, 22);
    root.add(plaque);

    if (iconKey) {
      const icon = this.scene.add.image(x + 44, y + height / 2, iconKey);
      icon.setDisplaySize(36, 36);
      root.add(icon);
    } else {
      const chaosGem = this.scene.add.graphics();
      chaosGem.fillStyle(0xf5e8c8, 1);
      chaosGem.fillTriangle(x + 44, y + 24, x + 29, y + 48, x + 44, y + 68);
      chaosGem.fillTriangle(x + 44, y + 24, x + 59, y + 48, x + 44, y + 68);
      chaosGem.lineStyle(2, 0x5d367c, 1);
      chaosGem.lineBetween(x + 44, y + 24, x + 44, y + 68);
      root.add(chaosGem);
    }

    const labelText = this.scene.add.text(x + 82, y + 19, label, {
      color: '#c8b98f',
      fontFamily: 'system-ui, sans-serif',
      fontSize: '13px',
      fontStyle: 'bold',
    });
    const valueText = this.scene.add.text(x + 82, y + 43, value, {
      color: '#f5e8c8',
      fontFamily: 'Georgia, serif',
      fontSize: '25px',
      fontStyle: 'bold',
    });
    root.add([labelText, valueText]);
  }
}

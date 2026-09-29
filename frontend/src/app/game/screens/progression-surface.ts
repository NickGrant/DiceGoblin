import Phaser from 'phaser';
import { Bounds, RuntimeViewportSnapshot } from '../runtime/runtime-viewport';
import { actionCursor } from './action-cursor';

export function progressionBackground(scene: Phaser.Scene, root: Phaser.GameObjects.Container,
  snapshot: RuntimeViewportSnapshot, panel: Bounds): void {
  const background = scene.add.graphics();
  background.fillGradientStyle(0x171c20, 0x24382e, 0x0b1718, 0x12272a, 1);
  background.fillRect(0, 0, snapshot.logicalWidth, snapshot.logicalHeight);
  background.fillStyle(0xf2e4c1, .97);
  background.fillRoundedRect(panel.x, panel.y, panel.width, panel.height, 16);
  root.add(background);
}

export function progressionButton(scene: Phaser.Scene, root: Phaser.GameObjects.Container, bounds: Bounds,
  label: string, action: () => void, enabled = true): void {
  const button = scene.add.graphics();
  button.fillStyle(enabled ? 0x273e35 : 0x6c6658, 1);
  button.fillRoundedRect(bounds.x, bounds.y, bounds.width, bounds.height, 10);
  if (enabled) {
    button.setInteractive(new Phaser.Geom.Rectangle(bounds.x, bounds.y, bounds.width, bounds.height),
      Phaser.Geom.Rectangle.Contains).on('pointerup', action);
    actionCursor(button);
  }
  root.add([button, scene.add.text(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2, label,
    { color: '#fff4d3', fontFamily: 'system-ui', fontSize: `${label === 'RETURN TO UNIT' && bounds.width < 180
      ? 16 : bounds.height >= 50 ? 23 : 17}px`, fontStyle: 'bold' }).setOrigin(.5)]);
}

export function progressionText(scene: Phaser.Scene, root: Phaser.GameObjects.Container, x: number, y: number,
  value: string, size = 18, color = '#3a2a1a', width?: number): void {
  root.add(scene.add.text(x, y, value, { color, fontFamily: 'system-ui', fontSize: `${size}px`,
    wordWrap: width ? { width } : undefined }));
}

import Phaser from 'phaser';

/** Apply after setInteractive with an explicit shape. Informational objects stay non-interactive. */
export function actionCursor(object: Phaser.GameObjects.GameObject): void {
  if (object.input) object.input.cursor = 'pointer';
}

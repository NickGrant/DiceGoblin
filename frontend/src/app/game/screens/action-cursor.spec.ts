import Phaser from 'phaser';
import { actionCursor } from './action-cursor';

describe('vNext Phaser action cursor', () => {
  it('advertises an interactive control with a hand without changing input behavior', () => {
    const input = { cursor: '' };
    const control = { input } as unknown as Phaser.GameObjects.GameObject;
    actionCursor(control);
    expect(input.cursor).toBe('pointer');
  });

  it('does not make informational or disabled objects interactive', () => {
    const object = { input: null } as Phaser.GameObjects.GameObject;
    actionCursor(object);
    expect(object.input).toBeNull();
  });
});

import Phaser from "phaser";

export default class BackgroundImage {
  private readonly scene: Phaser.Scene;
  private readonly image: Phaser.GameObjects.Image;

  constructor(scene: Phaser.Scene, textureKey: string) {
    this.scene = scene;

    // Top-left anchored for predictable placement math
    this.image = scene.add.image(0, 0, textureKey).setOrigin(0, 0);
    this.image.setDepth(-1000);
    this.image.setScrollFactor(0);

    // Initial layout
    this.resize(scene.scale.width, scene.scale.height);

    // Auto-resize if your game is using FIT/RESIZE scaling
    scene.scale.on(Phaser.Scale.Events.RESIZE, this.handleResize, this);

    // Auto-cleanup on scene shutdown/destroy
    scene.events.once(Phaser.Scenes.Events.SHUTDOWN, this.destroy, this);
    scene.events.once(Phaser.Scenes.Events.DESTROY, this.destroy, this);
  }

  private handleResize(gameSize: Phaser.Structs.Size): void {
    this.resize(gameSize.width, gameSize.height);
  }

  private resize(width: number, height: number): void {
    this.image.setPosition(0, 0);

    const scale = Math.max(width / this.image.width, height / this.image.height);
    this.image.setScale(scale);
  }

  public destroy(): void {
    this.scene.scale.off(Phaser.Scale.Events.RESIZE, this.handleResize, this);
    this.image.destroy();
  }
}

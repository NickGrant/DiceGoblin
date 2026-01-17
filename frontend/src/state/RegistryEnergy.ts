import Phaser from "phaser";

const CURRENT_KEY = 'currentEnergy';
const MAX_KEY = 'maxEnergy'

export class RegistryEnergy {
  
  static setCurrent(registry: Phaser.Data.DataManager, current: number): void {
    registry.set(CURRENT_KEY, current);
  }

  static getCurrent(registry: Phaser.Data.DataManager): number {
    return registry.get(CURRENT_KEY) as number;
  }

  static setMax(registry: Phaser.Data.DataManager, max: number): void {
    registry.set(MAX_KEY, max);
  }

  static getMax(registry: Phaser.Data.DataManager): number {
    return registry.get(MAX_KEY) as number;
  }

  static clear(registry: Phaser.Data.DataManager): void {
    registry.remove(CURRENT_KEY);
    registry.remove(MAX_KEY);
  }
}

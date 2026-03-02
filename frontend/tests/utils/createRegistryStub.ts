import type Phaser from "phaser";

export function createRegistryStub(): Phaser.Data.DataManager {
  const store = new Map<string, unknown>();

  return {
    set(key: string, value: unknown) {
      store.set(key, value);
      return this as Phaser.Data.DataManager;
    },
    get(key: string) {
      return store.get(key);
    },
    remove(key: string) {
      store.delete(key);
      return this as Phaser.Data.DataManager;
    }
  } as unknown as Phaser.Data.DataManager;
}

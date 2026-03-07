import { describe, expect, it, vi } from "vitest";

vi.mock("phaser", () => {
  type Handler = (...args: unknown[]) => void;

  class FakeImage {
    public textureKey = "";
    public alpha = 1;
    public scale = 1;
    public tint: number | null = null;

    setOrigin(): this { return this; }
    setScale(v: number): this { this.scale = v; return this; }
    setTexture(key: string): this { this.textureKey = key; return this; }
    setAlpha(v: number): this { this.alpha = v; return this; }
    clearTint(): this { this.tint = null; return this; }
    setTint(v: number): this { this.tint = v; return this; }
  }

  class FakeContainer {
    public scene: any;
    public x: number;
    public y: number;
    private handlers: Record<string, Handler[]> = {};
    public interactive = false;

    constructor(scene: any, x: number, y: number) {
      this.scene = scene;
      this.x = x;
      this.y = y;
    }

    setSize(): this { return this; }
    setInteractive(): this { this.interactive = true; return this; }
    disableInteractive(): this { this.interactive = false; return this; }
    add(): this { return this; }

    on(event: string, cb: Handler): this {
      this.handlers[event] ||= [];
      this.handlers[event].push(cb);
      return this;
    }

    emit(event: string, ...args: unknown[]): this {
      for (const cb of this.handlers[event] || []) {
        cb(...args);
      }
      return this;
    }
  }

  const fakePhaser = {
    GameObjects: {
      Container: FakeContainer,
      Image: FakeImage,
    },
    Geom: {
      Rectangle: class {
        constructor(
          public x: number,
          public y: number,
          public width: number,
          public height: number
        ) {}
      },
    },
  };

  return {
    default: fakePhaser,
    GameObjects: fakePhaser.GameObjects,
    Geom: fakePhaser.Geom,
  };
});

import Node from "../../src/components/encounter-map/Node";

function makeScene() {
  const imageFactory = vi.fn(() => ({
    textureKey: "",
    alpha: 1,
    scale: 1,
    tint: null as number | null,
    setOrigin() { return this; },
    setDisplaySize() { return this; },
    setScale(v: number) { this.scale = v; return this; },
    setTexture(key: string) { this.textureKey = key; return this; },
    setAlpha(v: number) { this.alpha = v; return this; },
    clearTint() { this.tint = null; return this; },
    setTint(v: number) { this.tint = v; return this; },
  }));
  return {
    add: {
      existing: vi.fn(),
      image: imageFactory,
    },
  } as any;
}

describe("Node affordances", () => {
  it("only triggers click callback for available nodes", () => {
    const scene = makeScene();
    const onClick = vi.fn();

    const node = new Node(scene, 0, 0, {
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "combat",
      status: "available",
    } as any, { onClick });

    (node as any).emit("pointerup");
    expect(onClick).toHaveBeenCalledTimes(1);

    onClick.mockClear();
    node.setRecord({
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "combat",
      status: "locked",
    } as any);
    (node as any).emit("pointerup");
    expect(onClick).toHaveBeenCalledTimes(0);

    node.setRecord({
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "combat",
      status: "cleared",
    } as any);
    (node as any).emit("pointerup");
    expect(onClick).toHaveBeenCalledTimes(0);
  });

  it("maps locked and type textures correctly and tints cleared nodes", () => {
    const scene = makeScene();

    const node = new Node(scene, 0, 0, {
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "rest",
      status: "locked",
    } as any);

    const icon = (node as any).icon as {
      textureKey: string;
      tint: number | null;
    };
    expect(icon.textureKey).toBe("icon_encounter_locked");

    node.setRecord({
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "boss",
      status: "available",
    } as any);
    expect(icon.textureKey).toBe("icon_encounter_boss");
    expect(icon.tint).toBeNull();

    node.setRecord({
      id: "1",
      run_id: "1",
      node_index: 0,
      node_type: "loot",
      status: "cleared",
    } as any);
    expect(icon.textureKey).toBe("icon_encounter_loot");
    expect(icon.tint).toBe(0x8fd38a);
  });
});

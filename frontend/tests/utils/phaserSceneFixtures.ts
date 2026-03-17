import { vi } from "vitest";

export class FakeScene {
  registry: Record<string, unknown> = {};
  cameras = { main: { centerX: 480, centerY: 270 } };
  scale = { width: 960, height: 640, on: vi.fn(), off: vi.fn() };
  scene = { start: vi.fn() };
  add = {
    text: vi.fn(() => ({ setOrigin: vi.fn(() => ({ destroy: vi.fn(), setText: vi.fn() })) })),
  };
  time = { delayedCall: vi.fn() };
}

export class FakeContainer {
  constructor(_scene?: unknown, _x?: number, _y?: number) {}
  add() { return this; }
  setSize() { return this; }
  setInteractive() { return this; }
  setOrigin() { return this; }
  setScrollFactor() { return this; }
  setDepth() { return this; }
  destroy() {}
}

export class Rectangle {
  constructor(public x: number, public y: number, public width: number, public height: number) {}
}

export function buildPhaserMock(opts: { includeContainer?: boolean; includeRectangle?: boolean } = {}) {
  const includeContainer = opts.includeContainer === true;
  const includeRectangle = opts.includeRectangle === true;

  const defaultExport: Record<string, unknown> = { Scene: FakeScene };
  const named: Record<string, unknown> = { Scene: FakeScene };

  if (includeContainer) {
    const gameObjects = { Container: FakeContainer };
    defaultExport.GameObjects = gameObjects;
    named.GameObjects = gameObjects;
  }

  if (includeRectangle) {
    const geom = { Rectangle };
    defaultExport.Geom = geom;
    named.Geom = geom;
  }

  return {
    default: defaultExport,
    ...named,
  };
}

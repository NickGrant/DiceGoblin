import { describe, expect, it } from "vitest";
import { resolveEnergyTierIcon } from "./HudPanel";

describe("HudPanel", () => {
  it("maps energy percentages to icon tiers", () => {
    expect(resolveEnergyTierIcon(50, 50)).toBe("icon_energy");
    expect(resolveEnergyTierIcon(40, 50)).toBe("icon_energy_75");
    expect(resolveEnergyTierIcon(25, 50)).toBe("icon_energy_50");
    expect(resolveEnergyTierIcon(15, 50)).toBe("icon_energy_25");
    expect(resolveEnergyTierIcon(10, 50)).toBe("icon_energy_0");
  });
});

import type Phaser from "phaser";

export const FONT_FAMILY_DISPLAY = '"Big Shoulders Stencil Text", "Saira Stencil One", system-ui, -apple-system, "Segoe UI", Roboto, Arial';
export const FONT_FAMILY_BODY = '"IBM Plex Sans Condensed", "Roboto Condensed", system-ui, -apple-system, "Segoe UI", Roboto, Arial';

export const UI_COLORS = {
  starkCream: "#F3EFE0",
  revolutionaryRed: "#B91C1C",
  coldSlate: "#4F5A65",
  dirtyTeal: "#006F7A",
  deepCharcoal: "#23272A"
} as const;

export const UI_TEXT_COLORS = {
  onDarkHigh: "#ffffff",
  onDarkMedium: "#dddddd",
  onDarkMuted: "#cccccc",
  onDarkSubtle: "#d6d6d6",
  onDarkPrimary: "#f0f0f0",
  onDarkDisabled: "#999999",
} as const;

export const TEXT_FONT_BODY: Phaser.Types.GameObjects.Text.TextStyle = {
  fontFamily: FONT_FAMILY_BODY,
};

export const TEXT_OVERLAY_TITLE: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "30px",
  color: UI_TEXT_COLORS.onDarkHigh,
};

export const TEXT_OVERLAY_BODY: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "18px",
  color: UI_TEXT_COLORS.onDarkMedium,
};

export const TEXT_CAPTION_ON_DARK: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "14px",
  color: UI_TEXT_COLORS.onDarkHigh,
};

export const TEXT_LIST_MESSAGE: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "16px",
  color: UI_TEXT_COLORS.onDarkMedium,
};

export const TEXT_LIST_ACTION: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "15px",
  color: UI_TEXT_COLORS.onDarkHigh,
};

export const TEXT_LIST_META: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "13px",
  color: UI_TEXT_COLORS.onDarkSubtle,
};

export const TEXT_LIST_ROW: Phaser.Types.GameObjects.Text.TextStyle = {
  ...TEXT_FONT_BODY,
  fontSize: "16px",
  color: UI_TEXT_COLORS.onDarkPrimary,
};

/**
 * Button label text (placard buttons: BEGIN RUN, WARBAND, etc.)
 * - Stencil display
 * - High contrast, letter-spaced, feels "printed on sign"
 */
export const TEXT_BUTTON: Phaser.Types.GameObjects.Text.TextStyle = {
  fontFamily: FONT_FAMILY_DISPLAY,
  fontSize: "34px",
  color: UI_COLORS.deepCharcoal,
  align: "center",

  // Phaser supports stroke + shadow; we use them lightly so the label stays crisp
  stroke: "rgba(243,239,224,0.55)", // cream-ish halo for busy textures
  strokeThickness: 2,

  shadow: {
    offsetX: 0,
    offsetY: 2, // tiny print offset
    color: "rgba(0,0,0,0.30)",
    blur: 0,
    fill: true,
    stroke: false
  },

  // Keep this for buttons that have enough width
  // (If a label feels too wide, reduce to 0 or 0.5)
  letterSpacing: 1
};

/**
 * Header text (screen titles, big poster headings)
 * - Stencil display
 * - Bigger stroke for readability over textured backplates
 */
export const TEXT_HEADER: Phaser.Types.GameObjects.Text.TextStyle = {
  fontFamily: FONT_FAMILY_DISPLAY,
  fontSize: "64px",
  color: UI_COLORS.deepCharcoal,
  align: "center",

  stroke: "rgba(243,239,224,0.65)",
  strokeThickness: 3,

  shadow: {
    offsetX: 0,
    offsetY: 3,
    color: "rgba(0,0,0,0.35)",
    blur: 0,
    fill: true,
    stroke: false
  },

  letterSpacing: 2
};

/**
 * Body text (paragraphs, labels, small instructions)
 * - Condensed sans for legibility
 * - Softer ink + lighter shadow so it doesn't feel "shouted"
 */
export const TEXT_BODY: Phaser.Types.GameObjects.Text.TextStyle = {
  fontFamily: FONT_FAMILY_BODY,
  fontSize: "22px",
  color: UI_COLORS.deepCharcoal,
  align: "left",

  // Minimal stroke (usually off); enable if needed on noisy paper
  stroke: "rgba(233,216,184,0.35)",
  strokeThickness: 1,

  shadow: {
    offsetX: 0,
    offsetY: 1,
    color: "rgba(0,0,0,0.25)",
    blur: 0,
    fill: true,
    stroke: false
  },

  lineSpacing: 6,
  wordWrap: { width: 720, useAdvancedWrap: true }
};

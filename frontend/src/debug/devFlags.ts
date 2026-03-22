function parseFlag(value: unknown): boolean | null {
  if (typeof value !== "string") {
    return null;
  }

  const normalized = value.trim().toLowerCase();
  if (["1", "true", "yes", "on"].includes(normalized)) {
    return true;
  }
  if (["0", "false", "no", "off"].includes(normalized)) {
    return false;
  }

  return null;
}

export function isDevPanelEnabled(): boolean {
  const explicit = parseFlag(import.meta.env.VITE_ENABLE_DEV_PANEL);
  return explicit ?? false;
}

import { CurrentRunNode } from '../../../core/models/api.models';

export type NodeArtKind = 'loot' | 'shrine';
export type NodeQualityTier = 'poor' | 'good' | 'great';
export type NodeArtVariant = 'a' | 'b';

const NODE_ART_FOLDER: Record<NodeArtKind, string> = {
  loot: 'loot',
  shrine: 'shrines',
};

export function resolveNodeArtUrl(node: CurrentRunNode | null | undefined, kind: NodeArtKind): string {
  const tier = resolveNodeQualityTier(node);
  const variant = resolveNodeArtVariant(node);
  return `/assets/ui/node-art/${NODE_ART_FOLDER[kind]}/${tier}_${variant}.png`;
}

export function resolveNodeQualityTier(node: CurrentRunNode | null | undefined): NodeQualityTier {
  const rawTier = String(node?.meta?.['node_quality_tier'] ?? '').trim().toLowerCase();
  if (rawTier === 'poor' || rawTier === 'good' || rawTier === 'great') {
    return rawTier;
  }

  return 'good';
}

export function resolveNodeArtVariant(node: CurrentRunNode | null | undefined): NodeArtVariant {
  const rawVariant = String(node?.meta?.['node_art_variant'] ?? '').trim().toLowerCase();
  if (rawVariant === 'a' || rawVariant === 'b') {
    return rawVariant;
  }

  const numericId = Number(node?.id);
  if (Number.isFinite(numericId)) {
    return numericId % 2 === 0 ? 'b' : 'a';
  }

  const fallback = `${node?.id ?? node?.node_index ?? ''}`;
  const lastCode = fallback.length ? fallback.charCodeAt(fallback.length - 1) : 1;
  return lastCode % 2 === 0 ? 'b' : 'a';
}

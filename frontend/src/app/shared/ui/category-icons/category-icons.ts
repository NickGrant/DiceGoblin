import { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import {
  faBolt,
  faBullseye,
  faCoins,
  faDiceD20,
  faFlag,
  faGraduationCap,
  faHandFist,
  faShieldHalved,
  faUsers,
  faWandMagicSparkles,
} from '@fortawesome/free-solid-svg-icons';
import { FeatureUnlockCategoryLabel } from '../../../core/feature-unlocks/feature-unlock-categories';

export function resolveUnitRoleIcon(roleOrSlug: string | null | undefined): IconDefinition {
  const normalized = (roleOrSlug ?? '').trim().toLowerCase();

  if (normalized === 'frontline' || normalized.startsWith('frontline_')) {
    return faShieldHalved;
  }

  if (normalized === 'backline' || normalized.startsWith('backline_')) {
    return faBullseye;
  }

  if (normalized === 'support' || normalized.startsWith('support_')) {
    return faFlag;
  }

  if (normalized === 'utility' || normalized.startsWith('utility_')) {
    return faWandMagicSparkles;
  }

  return faHandFist;
}

export function resolveFeatureUnlockIcon(category: FeatureUnlockCategoryLabel): IconDefinition {
  switch (category.trim().toLowerCase()) {
    case 'squad upgrade':
      return faUsers;
    case 'economy upgrade':
      return faCoins;
    case 'energy upgrade':
      return faBolt;
    case 'dice upgrade':
      return faDiceD20;
    default:
      return faGraduationCap;
  }
}

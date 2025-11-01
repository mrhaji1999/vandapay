import type { Role } from '../store/auth';

export type UserProfile = {
  id: number;
  username?: string;
  email?: string;
  name?: string;
  role?: Role;
  roles?: Role[];
  capabilities?: string[];
};

export const hasCapability = (user: UserProfile | null | undefined, capability: string) => {
  if (!user) return false;

  if (user.capabilities?.includes(capability)) {
    return true;
  }

  if (capability === 'manage_wallets') {
    const roles = user.roles ?? (user.role ? [user.role] : []);
    if (roles.includes('administrator')) {
      return true;
    }
  }

  return false;
};

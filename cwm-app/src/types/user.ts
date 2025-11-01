import type { Role } from '../store/auth';

export type UserProfile = {
  id: number;
  username: string;
  email: string;
  name?: string;
  role?: Role;
  roles?: Role[];
  capabilities?: string[];
};

export const hasCapability = (user: UserProfile | null | undefined, capability: string) => {
  if (!user) return false;
  return user.capabilities?.includes(capability) ?? false;
};

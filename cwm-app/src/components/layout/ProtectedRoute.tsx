import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../../store/auth';
import type { Role } from '../../store/auth';

interface Props {
  roles?: Role[];
}

export const ProtectedRoute = ({ roles }: Props) => {
  const { isAuthenticated, user } = useAuth();

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (roles && roles.length > 0) {
    const userRoles = [user?.role, ...(user?.roles ?? [])].filter(Boolean) as Role[];
    const hasRole = roles.some((role) => userRoles.includes(role));
    if (!hasRole) {
      return <Navigate to="/login" replace />;
    }
  }

  return <Outlet />;
};

import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../store/auth';

const rolePathMap = {
  administrator: '/admin',
  company: '/company',
  merchant: '/merchant',
  employee: '/employee'
} as const;

export const useRoleRedirect = () => {
  const navigate = useNavigate();
  const { user } = useAuth();

  useEffect(() => {
    if (!user) return;
    const role = user.role || user.roles?.[0];
    if (role && role in rolePathMap) {
      navigate(rolePathMap[role], { replace: true });
    }
  }, [navigate, user]);
};

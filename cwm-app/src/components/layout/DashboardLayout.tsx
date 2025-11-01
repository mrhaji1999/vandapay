import { NavLink, useNavigate } from 'react-router-dom';
import { PropsWithChildren } from 'react';
import { useAuth } from '../../store/auth';
import { Button } from '../ui/button';

const NAV_ITEMS = {
  administrator: [
    { to: '/admin', label: 'Dashboard' },
    { to: '/admin/transactions', label: 'Transactions' }
  ],
  company: [
    { to: '/company', label: 'Company Overview' }
  ],
  merchant: [
    { to: '/merchant', label: 'Merchant Dashboard' }
  ],
  employee: [
    { to: '/employee', label: 'Employee Dashboard' }
  ]
} as const;

export const DashboardLayout = ({ children }: PropsWithChildren) => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const role = user?.role || user?.roles?.[0] || 'employee';

  const items = NAV_ITEMS[role as keyof typeof NAV_ITEMS] ?? [];

  return (
    <div className="min-h-screen bg-slate-100">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div>
            <p className="text-lg font-semibold">Company Wallet Manager</p>
            <p className="text-sm text-muted-foreground">Welcome back, {user?.name || user?.username}</p>
          </div>
          <Button
            variant="outline"
            onClick={() => {
              logout();
              navigate('/login', { replace: true });
            }}
          >
            Log out
          </Button>
        </div>
      </header>
      <div className="mx-auto flex max-w-6xl gap-8 px-6 py-8">
        <aside className="w-64 space-y-4">
          <nav className="flex flex-col space-y-2">
            {items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `rounded-md px-3 py-2 text-sm font-medium transition hover:bg-secondary hover:text-secondary-foreground ${
                    isActive ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>
        </aside>
        <main className="flex-1 space-y-6">{children}</main>
      </div>
    </div>
  );
};

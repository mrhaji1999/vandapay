import { NavLink, useNavigate, useLocation } from 'react-router-dom';
import { PropsWithChildren, ReactNode } from 'react';
import { useAuth } from '../../store/auth';
import { Button } from '../ui/button';
import { cn } from '../../utils/cn';
import { 
  LayoutDashboard, 
  Store, 
  FolderTree, 
  CreditCard, 
  Building2, 
  User,
  Briefcase,
  Bell,
  Settings,
  LogOut
} from 'lucide-react';

const NAV_ITEMS = {
  administrator: [
    { to: '/admin', label: 'داشبورد مدیریت', icon: LayoutDashboard },
    { to: '/admin/merchants', label: 'مدیریت پذیرندگان', icon: Store },
    { to: '/admin/categories', label: 'دسته‌بندی پذیرنده‌ها', icon: FolderTree },
    { to: '/admin/transactions', label: 'گزارش تراکنش‌ها', icon: CreditCard }
  ],
  company: [{ to: '/company', label: 'پنل شرکت', icon: Building2 }],
  merchant: [{ to: '/merchant', label: 'داشبورد پذیرنده', icon: Store }],
  employee: [{ to: '/employee', label: 'داشبورد', icon: User }]
} as const;

interface DashboardLayoutProps extends PropsWithChildren {
  sidebarTabs?: Array<{ id: string; label: string; icon: string | React.ComponentType<{ className?: string }> }>;
  activeTab?: string;
  onTabChange?: (tabId: string) => void;
}

export const DashboardLayout = ({ 
  children, 
  sidebarTabs, 
  activeTab, 
  onTabChange 
}: DashboardLayoutProps) => {
  const navigate = useNavigate();
  const location = useLocation();
  const { user, logout } = useAuth();
  const role = user?.role || user?.roles?.[0] || 'employee';

  const items = NAV_ITEMS[role as keyof typeof NAV_ITEMS] ?? [];
  const currentPage = items.find((item) => location.pathname === item.to);

  return (
    <div className="flex min-h-screen bg-background">
      {/* Sidebar */}
      <aside className="fixed right-0 top-0 z-40 h-screen w-72 bg-sidebar border-l border-sidebar-border shadow-sidebar">
        <div className="flex h-full flex-col">
          {/* Logo/Brand */}
          <div className="px-6 py-6 border-b border-sidebar-border">
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-primary shadow-lg">
                <Briefcase className="h-6 w-6 text-white" />
              </div>
              <div>
                <h1 className="text-lg font-bold text-foreground">کیف پول سازمانی</h1>
                <p className="text-xs text-muted-foreground">سیستم مدیریت مالی</p>
              </div>
            </div>
          </div>

          {/* Navigation */}
          <nav className="flex-1 space-y-1 overflow-y-auto px-4 py-4">
            {items.map((item) => {
              const IconComponent = item.icon;
              return (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    cn(
                      'flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all duration-200',
                      isActive
                        ? 'bg-gradient-primary text-white shadow-lg shadow-primary/20'
                        : 'text-muted-foreground hover:bg-sidebar-hover hover:text-foreground'
                    )
                  }
                >
                  <IconComponent className="h-5 w-5" />
                  <span>{item.label}</span>
                </NavLink>
              );
            })}

            {/* Sidebar Tabs (for merchant dashboard) */}
            {sidebarTabs && sidebarTabs.length > 0 && (
              <>
                <div className="my-4 border-t border-sidebar-border"></div>
                {sidebarTabs.map((tab) => {
                  const IconComponent = typeof tab.icon === 'string' ? null : tab.icon;
                  return (
                    <button
                      key={tab.id}
                      onClick={() => onTabChange?.(tab.id)}
                      className={cn(
                        'w-full flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all duration-200',
                        activeTab === tab.id
                          ? 'bg-gradient-primary text-white shadow-lg shadow-primary/20'
                          : 'text-muted-foreground hover:bg-sidebar-hover hover:text-foreground'
                      )}
                    >
                      {IconComponent ? (
                        <IconComponent className="h-5 w-5" />
                      ) : typeof tab.icon === 'string' ? (
                        <span className="text-xl">{tab.icon}</span>
                      ) : null}
                      <span>{tab.label}</span>
                    </button>
                  );
                })}
              </>
            )}
          </nav>

          {/* User Info */}
          <div className="border-t border-sidebar-border p-4 bg-sidebar">
            <div className="flex items-center gap-3 rounded-xl p-3 hover:bg-sidebar-hover transition-colors cursor-pointer group">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-primary/10 group-hover:bg-gradient-primary/20 transition-colors">
                <User className="h-5 w-5 text-foreground" />
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-semibold text-foreground truncate">{user?.name || user?.username}</p>
                <p className="text-xs text-muted-foreground truncate">{user?.email || '—'}</p>
              </div>
            </div>
            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                logout();
                navigate('/login', { replace: true });
              }}
              className="w-full mt-2 gap-2 text-muted-foreground hover:text-destructive hover:bg-destructive/10"
            >
              <LogOut className="h-4 w-4" />
              <span>خروج از حساب</span>
            </Button>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <div className="mr-72 flex flex-1 flex-col">
        {/* Header */}
        <header className="sticky top-0 z-30 border-b border-border bg-card backdrop-blur-sm bg-white/95">
          <div className="flex h-16 items-center justify-between px-8">
            <div className="text-right">
              <h2 className="text-2xl font-bold text-foreground">
                {currentPage?.label || 'داشبورد'}
              </h2>
              <p className="text-xs text-muted-foreground mt-1">خوش آمدید، {user?.name || user?.username}</p>
            </div>
            <div className="flex items-center gap-2">
              <button className="relative flex h-10 w-10 items-center justify-center rounded-xl hover:bg-muted transition-colors group">
                <Bell className="h-5 w-5 text-foreground" />
                <span className="absolute top-2 left-2 h-2 w-2 rounded-full bg-destructive"></span>
              </button>
              <button className="flex h-10 w-10 items-center justify-center rounded-xl hover:bg-muted transition-colors">
                <Settings className="h-5 w-5 text-foreground" />
              </button>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 overflow-y-auto bg-background p-6">
          <div className="mx-auto max-w-7xl space-y-6">{children}</div>
        </main>
      </div>
    </div>
  );
};

import { NavLink, useNavigate, useLocation } from 'react-router-dom';
import { PropsWithChildren, ReactNode } from 'react';
import { useAuth } from '../../store/auth';
import { Button } from '../ui/button';

const NAV_ITEMS = {
  administrator: [
    { to: '/admin', label: 'داشبورد مدیریت', icon: '📊' },
    { to: '/admin/merchants', label: 'مدیریت پذیرندگان', icon: '🏪' },
    { to: '/admin/categories', label: 'دسته‌بندی پذیرنده‌ها', icon: '📁' },
    { to: '/admin/transactions', label: 'گزارش تراکنش‌ها', icon: '💳' }
  ],
  company: [{ to: '/company', label: 'نمای کلی شرکت', icon: '🏢' }],
  merchant: [{ to: '/merchant', label: 'داشبورد پذیرنده', icon: '🏬' }],
  employee: [{ to: '/employee', label: 'پنل مشتری', icon: '👤' }]
} as const;

interface DashboardLayoutProps extends PropsWithChildren {
  sidebarTabs?: Array<{ id: string; label: string; icon: string }>;
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
      <aside className="fixed right-0 top-0 z-40 h-screen w-64 bg-[#F9FAFB] border-l border-[#E5E7EB]">
        <div className="flex h-full flex-col">
          {/* Logo/Brand */}
          <div className="px-6 py-6">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary">
                <span className="text-xl text-white">💼</span>
              </div>
              <div>
                <h1 className="text-lg font-bold text-[#1F2937]">کیف پول سازمانی</h1>
                <p className="text-xs text-[#6B7280]">سیستم مدیریت مالی</p>
              </div>
            </div>
          </div>

          {/* Navigation */}
          <nav className="flex-1 space-y-0.5 overflow-y-auto px-3 py-2">
            {items.map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-primary text-white'
                      : 'text-[#4B5563] hover:bg-[#F3F4F6]'
                  }`
                }
              >
                <span className="text-lg">{item.icon}</span>
                <span>{item.label}</span>
              </NavLink>
            ))}

            {/* Sidebar Tabs (for merchant dashboard) */}
            {sidebarTabs && sidebarTabs.length > 0 && (
              <>
                <div className="my-2"></div>
                {sidebarTabs.map((tab) => (
                  <button
                    key={tab.id}
                    onClick={() => onTabChange?.(tab.id)}
                    className={`w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                      activeTab === tab.id
                        ? 'bg-primary text-white'
                        : 'text-[#4B5563] hover:bg-[#F3F4F6]'
                    }`}
                  >
                    <span className="text-lg">{tab.icon}</span>
                    <span>{tab.label}</span>
                  </button>
                ))}
              </>
            )}
          </nav>

          {/* User Info */}
          <div className="border-t border-[#E5E7EB] p-4">
            <div className="flex items-center gap-3 rounded-lg p-3 hover:bg-[#F3F4F6] transition-colors cursor-pointer">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                <span className="text-lg">👤</span>
              </div>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium text-[#1F2937] truncate">{user?.name || user?.username}</p>
                <p className="text-xs text-[#6B7280] truncate">{user?.email || '—'}</p>
              </div>
            </div>
          </div>
        </div>
      </aside>

      {/* Main Content */}
      <div className="mr-64 flex flex-1 flex-col">
        {/* Header */}
        <header className="sticky top-0 z-30 border-b border-[#E5E7EB] bg-white">
          <div className="flex h-16 items-center justify-between px-8">
            <div className="text-right">
              <h2 className="text-xl font-semibold text-[#1F2937]">
                {currentPage?.label || 'داشبورد'}
              </h2>
            </div>
            <div className="flex items-center gap-3">
              <button className="flex h-10 w-10 items-center justify-center rounded-full hover:bg-[#F3F4F6] transition-colors">
                <span className="text-lg">🔔</span>
              </button>
              <button className="flex h-10 w-10 items-center justify-center rounded-full hover:bg-[#F3F4F6] transition-colors">
                <span className="text-lg">⚙️</span>
              </button>
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                <span className="text-lg">👤</span>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={() => {
                  logout();
                  navigate('/login', { replace: true });
                }}
                className="gap-2 text-[#6B7280] hover:text-[#1F2937]"
              >
                <span>🚪</span>
                <span>خروج</span>
              </Button>
            </div>
          </div>
        </header>

        {/* Page Content */}
        <main className="flex-1 overflow-y-auto bg-[#F3F4F6] p-6">
          <div className="mx-auto max-w-7xl space-y-6">{children}</div>
        </main>
      </div>
    </div>
  );
};

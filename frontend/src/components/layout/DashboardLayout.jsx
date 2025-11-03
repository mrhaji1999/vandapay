import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext.jsx';

const roleLabels = {
  company: 'شرکت',
  merchant: 'پذیرنده',
  employee: 'کارمند',
};

const navItems = {
  company: [
    { to: '/dashboard/company', label: 'پیشخوان' },
    { to: '/dashboard/company/reports', label: 'گزارش‌ها (به‌زودی)', disabled: true },
    { to: '/dashboard/company/settings', label: 'تنظیمات (به‌زودی)', disabled: true },
  ],
  merchant: [
    { to: '/dashboard/merchant', label: 'پیشخوان' },
    { to: '/dashboard/merchant', label: 'حساب‌های بانکی', anchor: '#banks' },
    { to: '/dashboard/merchant/settings', label: 'تنظیمات (به‌زودی)', disabled: true },
  ],
  employee: [
    { to: '/dashboard/employee', label: 'پیشخوان' },
    { to: '/dashboard/employee/transactions', label: 'سوابق خرید' },
  ],
};

export default function DashboardLayout({ children, pageTitle = 'پنل وندا پی' }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const links = navItems[user?.role] || [];

  const handleLogout = () => {
    logout();
    navigate('/login');
  };

  return (
    <div className="dashboard-shell">
      <aside className="dashboard-sidebar">
        <div className="sidebar-brand">
          <span className="sidebar-logo">VP</span>
          <p>VandaPay</p>
        </div>
        <nav>
          {links.map((item) => (
            <SidebarLink key={item.to} item={item} />
          ))}
        </nav>
        <button type="button" className="sidebar-logout" onClick={handleLogout}>
          خروج
        </button>
      </aside>
      <div className="dashboard-main">
        <header className="dashboard-header">
          <div>
            <h1>{pageTitle}</h1>
            <p className="dashboard-subtitle">سلام {user?.name || ''}!</p>
          </div>
          <div className="dashboard-user">
            <div className="dashboard-avatar">{user?.name?.slice(0, 2) || 'کاربر'}</div>
            <div>
              <strong>{user?.name}</strong>
              <span>{roleLabels[user?.role] || user?.role}</span>
            </div>
          </div>
        </header>
        <main className="dashboard-content">{children}</main>
      </div>
    </div>
  );
}

function SidebarLink({ item }) {
  if (item.disabled) {
    return (
      <span className="sidebar-link disabled" title="به زودی">
        {item.label}
      </span>
    );
  }

  const destination = `${item.to}${item.anchor || ''}`;

  return (
    <NavLink className={({ isActive }) => `sidebar-link ${isActive ? 'active' : ''}`} to={destination}>
      {item.label}
    </NavLink>
  );
}

import clsx from 'clsx';

export default function DashboardCard({ title, value, icon, footer, variant = 'default' }) {
  return (
    <div className={clsx('card', `dashboard-card-${variant}`)}>
      <div className="dashboard-card-header">
        <div>
          <h3>{title}</h3>
          {footer && <span className="dashboard-card-footer">{footer}</span>}
        </div>
        {icon && <div className="dashboard-card-icon">{icon}</div>}
      </div>
      <p className="dashboard-card-value">{value}</p>
    </div>
  );
}

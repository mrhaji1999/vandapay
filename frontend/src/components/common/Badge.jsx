import clsx from 'clsx';

const statusToVariant = {
  success: 'badge-success',
  pending: 'badge-pending',
  failed: 'badge-failed',
  error: 'badge-failed',
};

export default function Badge({ status, children }) {
  const variant = statusToVariant[status] || 'badge-pending';
  return <span className={clsx('badge', variant)}>{children || status}</span>;
}

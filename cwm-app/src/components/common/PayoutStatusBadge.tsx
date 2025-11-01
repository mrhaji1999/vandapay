import { cn } from '../../utils/cn';

type Props = {
  status: string;
};

const STATUS_MAP: Record<string, string> = {
  pending: 'bg-yellow-100 text-yellow-800',
  approved: 'bg-green-100 text-green-800',
  rejected: 'bg-red-100 text-red-800',
  paid: 'bg-emerald-100 text-emerald-800'
};

const LABEL_MAP: Record<string, string> = {
  pending: 'در انتظار',
  approved: 'تأیید شده',
  rejected: 'رد شده',
  paid: 'پرداخت شده'
};

export const PayoutStatusBadge = ({ status }: Props) => {
  const normalized = status?.toLowerCase() ?? 'pending';
  const classes = STATUS_MAP[normalized] ?? 'bg-slate-100 text-slate-700';
  const label = LABEL_MAP[normalized] ?? status;
  return <span className={cn('inline-flex rounded-full px-3 py-1 text-xs font-medium', classes)}>{label}</span>;
};

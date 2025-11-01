import { useState } from 'react';
import { Input } from '../ui/input';

type Props = {
  onChange: (range: { from?: string; to?: string }) => void;
};

export const DateRangePicker = ({ onChange }: Props) => {
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  return (
    <div className="flex flex-wrap items-end gap-4">
      <div className="flex flex-col space-y-1">
        <span className="text-sm font-medium">From</span>
        <Input
          type="date"
          value={from}
          onChange={(event) => {
            setFrom(event.target.value);
            onChange({ from: event.target.value || undefined, to: to || undefined });
          }}
        />
      </div>
      <div className="flex flex-col space-y-1">
        <span className="text-sm font-medium">To</span>
        <Input
          type="date"
          value={to}
          onChange={(event) => {
            setTo(event.target.value);
            onChange({ from: from || undefined, to: event.target.value || undefined });
          }}
        />
      </div>
    </div>
  );
};

import * as React from 'react';
import { cn } from '../../utils/cn';

export interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {}

const Select = React.forwardRef<HTMLSelectElement, SelectProps>(({ className, children, ...props }, ref) => (
  <select
    ref={ref}
    className={cn(
      'flex h-10 w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2 text-sm text-[#1F2937] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50',
      className
    )}
    {...props}
  >
    {children}
  </select>
));
Select.displayName = 'Select';

export { Select };

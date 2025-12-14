import { PropsWithChildren } from 'react';
import { Briefcase } from 'lucide-react';

export const AuthLayout = ({ children }: PropsWithChildren) => {
  return (
    <div className="flex min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
      <div className="m-auto w-full max-w-md px-4">
        <div className="rounded-2xl border border-border bg-card p-8 shadow-elevated">
          <div className="mb-8 text-center space-y-3">
            <div className="mx-auto w-20 h-20 rounded-2xl bg-gradient-primary flex items-center justify-center mb-4 shadow-lg shadow-primary/20">
              <Briefcase className="h-10 w-10 text-white" />
            </div>
            <h1 className="text-3xl font-bold text-foreground">مدیریت کیف پول سازمانی</h1>
            <p className="text-sm text-muted-foreground">برای ادامه وارد سامانه شوید یا ثبت‌نام کنید.</p>
          </div>
          {children}
        </div>
      </div>
    </div>
  );
};

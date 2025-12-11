import { PropsWithChildren } from 'react';

export const AuthLayout = ({ children }: PropsWithChildren) => {
  return (
    <div className="flex min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
      <div className="m-auto w-full max-w-md">
        <div className="rounded-2xl border border-border bg-white p-8 shadow-card-hover">
          <div className="mb-8 text-center space-y-2">
            <div className="mx-auto w-16 h-16 rounded-full bg-gradient-primary flex items-center justify-center mb-4">
              <span className="text-3xl">💼</span>
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

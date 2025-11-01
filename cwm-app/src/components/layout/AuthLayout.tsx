import { PropsWithChildren } from 'react';

export const AuthLayout = ({ children }: PropsWithChildren) => {
  return (
    <div className="flex min-h-screen bg-slate-100">
      <div className="m-auto w-full max-w-md rounded-xl border bg-white p-8 shadow-lg">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-semibold">Company Wallet Manager</h1>
          <p className="text-sm text-muted-foreground">Secure access to your wallet tools.</p>
        </div>
        {children}
      </div>
    </div>
  );
};

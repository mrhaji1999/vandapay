import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Input from '../components/Input';
import Button from '../components/Button';

const LoginPage = () => {
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');
    const [error, setError] = useState('');
    const auth = useAuth();
    const navigate = useNavigate();

    const handleSubmit = async (event) => {
        event.preventDefault();
        setError('');
        const success = await auth.login(username, password);
        if (success) {
            navigate('/dashboard');
        } else {
            setError('نام کاربری یا رمز عبور صحیح نیست.');
        }
    };

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#050816] px-4">
            <div className="absolute inset-0 -z-10">
                <div className="absolute left-1/2 top-0 h-[480px] w-[480px] -translate-x-1/2 rounded-full bg-sky-500/10 blur-3xl" />
                <div className="absolute bottom-[-160px] right-[-80px] h-[520px] w-[520px] rounded-full bg-indigo-500/10 blur-3xl" />
            </div>
            <div className="w-full max-w-lg rounded-3xl border border-white/10 bg-white/5 p-10 shadow-[0_35px_65px_-35px_rgba(15,23,42,0.8)] backdrop-blur">
                <div className="space-y-3 text-center">
                    <p className="text-xs uppercase tracking-[0.3em] text-slate-400">vandapay wallet suite</p>
                    <h1 className="text-3xl font-semibold text-white">ورود به داشبورد یکپارچه</h1>
                    <p className="text-sm text-slate-300">
                        برای دسترسی به پنل‌های شرکت، پذیرنده یا کارمندان ابتدا با حساب کاربری وردپرس خود وارد شوید.
                    </p>
                </div>
                <form onSubmit={handleSubmit} className="mt-10 space-y-6">
                    <div className="space-y-2">
                        <label className="text-xs uppercase tracking-[0.3em] text-slate-400">نام کاربری</label>
                        <Input
                            value={username}
                            onChange={(event) => setUsername(event.target.value)}
                            placeholder="نام کاربری وردپرس"
                            autoComplete="username"
                            required
                        />
                    </div>
                    <div className="space-y-2">
                        <label className="text-xs uppercase tracking-[0.3em] text-slate-400">رمز عبور</label>
                        <Input
                            type="password"
                            value={password}
                            onChange={(event) => setPassword(event.target.value)}
                            placeholder="••••••••"
                            autoComplete="current-password"
                            required
                        />
                    </div>
                    {error && <p className="text-xs text-rose-300">{error}</p>}
                    <Button type="submit" className="w-full">
                        ورود به سیستم
                    </Button>
                </form>
            </div>
        </div>
    );
};

export default LoginPage;

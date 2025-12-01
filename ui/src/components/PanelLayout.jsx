import React from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import Button from './Button';

const navigation = [
    { label: 'داشبورد', to: '/dashboard' },
    { label: 'پنل شرکت', to: '/company' },
    { label: 'پنل پذیرنده', to: '/merchant' },
    { label: 'پنل مشتری', to: '/employee' },
];

const PanelLayout = ({ title, description, actions, children }) => {
    const location = useLocation();
    const navigate = useNavigate();
    const { user, logout } = useAuth();

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    return (
        <div className="min-h-screen bg-[#050816] text-slate-100">
            <div className="fixed inset-0 -z-10 overflow-hidden">
                <div className="absolute -top-32 right-20 h-64 w-64 rounded-full bg-sky-500/10 blur-3xl" />
                <div className="absolute bottom-0 left-20 h-96 w-96 rounded-full bg-indigo-500/10 blur-3xl" />
            </div>

            <header className="border-b border-white/10 bg-[#050816]/70 backdrop-blur">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-6">
                    <Link to="/dashboard" className="text-lg font-semibold text-white">
                        VandaPay Wallet Suite
                    </Link>
                    <nav className="flex flex-wrap items-center gap-3 text-sm text-slate-300">
                        {navigation.map((item) => (
                            <Link
                                key={item.to}
                                to={item.to}
                                className={`rounded-full px-4 py-1.5 transition ${
                                    location.pathname === item.to
                                        ? 'bg-white/15 text-white'
                                        : 'hover:bg-white/10 hover:text-white'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="flex items-center gap-3 text-sm text-slate-300">
                        {user && (
                            <div className="rounded-full bg-white/5 px-3 py-1">
                                {user.display_name || user.username}
                            </div>
                        )}
                        <Button variant="ghost" onClick={handleLogout}>
                            خروج
                        </Button>
                    </div>
                </div>
            </header>

            <main className="mx-auto flex max-w-6xl flex-col gap-10 px-6 py-12">
                <div className="flex flex-wrap items-end justify-between gap-6">
                    <div className="space-y-3">
                        <p className="text-sm uppercase tracking-[0.3em] text-slate-400">wallet orchestration</p>
                        <h1 className="text-3xl font-semibold text-white sm:text-4xl">{title}</h1>
                        {description && <p className="max-w-2xl text-sm text-slate-300 leading-relaxed">{description}</p>}
                    </div>
                    {actions && <div className="flex items-center gap-3">{actions}</div>}
                </div>

                {children}
            </main>
        </div>
    );
};

export default PanelLayout;

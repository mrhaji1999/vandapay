import React from 'react';

const StatCard = ({ title, value, hint, trend, accent = 'from-sky-500/80 to-indigo-500/60' }) => {
    return (
        <div className="relative overflow-hidden rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_18px_40px_-35px_rgba(15,23,42,0.8)]">
            <div className={`absolute inset-0 -z-10 bg-gradient-to-br ${accent} opacity-60 blur-3xl`} aria-hidden="true" />
            <p className="text-xs uppercase tracking-[0.2em] text-slate-300">{title}</p>
            <p className="mt-4 text-3xl font-semibold text-white">{value}</p>
            {hint && <p className="mt-3 text-sm text-slate-300">{hint}</p>}
            {trend && (
                <p className={`mt-4 inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${
                    trend.direction === 'up'
                        ? 'bg-emerald-500/20 text-emerald-300'
                        : trend.direction === 'down'
                            ? 'bg-rose-500/20 text-rose-300'
                            : 'bg-slate-500/20 text-slate-200'
                }`}
                >
                    {trend.label}
                </p>
            )}
        </div>
    );
};

export default StatCard;

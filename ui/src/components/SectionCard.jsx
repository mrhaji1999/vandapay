import React from 'react';

const SectionCard = ({
    title,
    description,
    action,
    children,
    footer,
    className = '',
}) => {
    return (
        <section
            className={`space-y-6 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-[0_20px_45px_-35px_rgba(15,23,42,0.75)] backdrop-blur ${className}`}
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="space-y-2">
                    <h2 className="text-lg font-semibold text-white">{title}</h2>
                    {description && <p className="text-sm text-slate-300 max-w-2xl leading-relaxed">{description}</p>}
                </div>
                {action && <div className="shrink-0">{action}</div>}
            </div>
            <div className="space-y-5 text-slate-100">{children}</div>
            {footer && <div className="border-t border-white/10 pt-4 text-xs text-slate-400">{footer}</div>}
        </section>
    );
};

export default SectionCard;

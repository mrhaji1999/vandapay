import React from 'react';

const Button = ({
    children,
    onClick,
    type = 'button',
    variant = 'primary',
    className = '',
    icon: Icon,
    disabled = false,
}) => {
    const baseStyles =
        'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60';

    const variants = {
        primary:
            'bg-gradient-to-r from-sky-500 to-indigo-500 text-white shadow-lg shadow-sky-500/20 hover:from-sky-400 hover:to-indigo-400 focus-visible:outline-sky-400',
        secondary:
            'bg-white/10 text-slate-100 ring-1 ring-inset ring-white/15 hover:bg-white/15 focus-visible:outline-white',
        ghost:
            'bg-transparent text-slate-300 hover:text-white hover:bg-white/5 focus-visible:outline-white',
        danger:
            'bg-gradient-to-r from-rose-500 to-amber-500 text-white shadow-lg shadow-rose-500/20 hover:from-rose-400 hover:to-amber-400 focus-visible:outline-rose-400',
    };

    const styles = `${baseStyles} ${variants[variant]} ${className}`;

    return (
        <button type={type} onClick={onClick} className={styles} disabled={disabled}>
            {Icon && <Icon className="h-4 w-4" aria-hidden="true" />}
            {children}
        </button>
    );
};

export default Button;

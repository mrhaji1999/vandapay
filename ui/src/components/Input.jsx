import React from 'react';

const Input = ({
    type = 'text',
    value,
    onChange,
    placeholder = '',
    className = '',
    startAdornment,
    ...props
}) => {
    const baseStyles =
        'block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-100 placeholder:text-slate-400 shadow-inner shadow-black/5 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40';

    return (
        <div className="relative">
            {startAdornment && (
                <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400">
                    {startAdornment}
                </span>
            )}
            <input
                type={type}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                className={`${baseStyles} ${startAdornment ? 'pl-11' : ''} ${className}`}
                {...props}
            />
        </div>
    );
};

export default Input;

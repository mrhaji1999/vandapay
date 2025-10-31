import React from 'react';

const Select = ({ options = [], value, onChange, placeholder = '', className = '', ...props }) => {
    return (
        <select
            value={value}
            onChange={onChange}
            className={`block w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-slate-100 shadow-inner shadow-black/5 focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/40 ${className}`}
            {...props}
        >
            {placeholder && (
                <option value="" disabled hidden>
                    {placeholder}
                </option>
            )}
            {options.map((option) => (
                <option key={option.value} value={option.value} className="text-slate-900">
                    {option.label}
                </option>
            ))}
        </select>
    );
};

export default Select;

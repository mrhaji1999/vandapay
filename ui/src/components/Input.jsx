import React from 'react';

const Input = ({ type = 'text', value, onChange, placeholder = '', className = '', ...props }) => {
    const styles = `w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${className}`;

    return (
        <input
            type={type}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
            className={styles}
            {...props}
        />
    );
};

export default Input;

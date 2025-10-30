import React from 'react';

const Toast = ({ message, type = 'success', onDismiss }) => {
    const baseStyles = 'fixed top-5 right-5 p-4 rounded-md shadow-lg text-white';
    const typeStyles = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
    };

    return (
        <div className={`${baseStyles} ${typeStyles[type]}`}>
            <span>{message}</span>
            <button onClick={onDismiss} className="ml-4 font-bold">X</button>
        </div>
    );
};

export default Toast;

import React, { useEffect } from 'react';

const Modal = ({ isOpen, onClose, title, children }) => {
    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    if (!isOpen) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
            onClick={onClose}
        >
            <div
                className="rounded-3xl border border-white/20 bg-[#050816]/95 backdrop-blur-xl shadow-[0_25px_55px_-35px_rgba(15,23,42,0.9)] w-full max-w-2xl max-h-[90vh] overflow-y-auto"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex justify-between items-center p-6 border-b border-white/10">
                    <h3 className="text-xl font-bold text-white">{title}</h3>
                    <button
                        onClick={onClose}
                        className="text-slate-400 hover:text-white transition rounded-full w-8 h-8 flex items-center justify-center hover:bg-white/10"
                    >
                        <span className="text-2xl leading-none">×</span>
                    </button>
                </div>
                <div className="p-6 text-slate-100">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default Modal;

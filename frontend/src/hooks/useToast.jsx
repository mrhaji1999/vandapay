import { createContext, useCallback, useContext, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { nanoid } from 'nanoid/non-secure';

const ToastContext = createContext();

function ToastContainer({ toasts, removeToast }) {
  return createPortal(
    <div className="toast-container">
      {toasts.map((toast) => (
        <div key={toast.id} className={`toast toast-${toast.type}`}>
          <div>
            <strong>{toast.title}</strong>
            {toast.message && <p>{toast.message}</p>}
          </div>
          <button type="button" onClick={() => removeToast(toast.id)} aria-label="Dismiss">
            ×
          </button>
        </div>
      ))}
    </div>,
    document.body,
  );
}

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([]);

  const removeToast = (id) => {
    setToasts((prev) => prev.filter((item) => item.id !== id));
  };

  const showToast = useCallback(({ title, message, type = 'success', duration = 3000 }) => {
    const id = nanoid();
    setToasts((prev) => [...prev, { id, title, message, type }]);
    setTimeout(() => removeToast(id), duration);
  }, []);

  const value = useMemo(() => ({ showToast }), [showToast]);

  return (
    <ToastContext.Provider value={value}>
      {children}
      <ToastContainer toasts={toasts} removeToast={removeToast} />
    </ToastContext.Provider>
  );
}

export function useToast() {
  return useContext(ToastContext);
}

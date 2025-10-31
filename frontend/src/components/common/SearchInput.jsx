import { useEffect, useRef, useState } from 'react';

export default function SearchInput({ placeholder, value = '', onChange, debounce = 0 }) {
  const timeoutRef = useRef();
  const [internalValue, setInternalValue] = useState(value);

  useEffect(() => {
    setInternalValue(value);
  }, [value]);

  useEffect(() => () => clearTimeout(timeoutRef.current), []);

  const handleChange = (event) => {
    const { value: nextValue } = event.target;
    setInternalValue(nextValue);

    if (debounce > 0) {
      clearTimeout(timeoutRef.current);
      timeoutRef.current = setTimeout(() => onChange(nextValue), debounce);
    } else {
      onChange(nextValue);
    }
  };

  return (
    <div className="search-input">
      <span className="search-icon" aria-hidden>
        🔍
      </span>
      <input value={internalValue} onChange={handleChange} placeholder={placeholder} />
    </div>
  );
}

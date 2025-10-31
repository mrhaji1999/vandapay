import { useEffect, useRef, useState } from 'react';

export default function OtpInput({ length = 5, onComplete, onChange }) {
  const [values, setValues] = useState(Array.from({ length }, () => ''));
  const inputsRef = useRef([]);

  useEffect(() => {
    inputsRef.current[0]?.focus();
  }, []);

  const updateValue = (index, value) => {
    if (!/^\d*$/.test(value)) return;
    const nextValues = [...values];
    nextValues[index] = value.slice(-1);
    setValues(nextValues);
    onChange?.(nextValues.join(''));

    if (value && index < length - 1) {
      inputsRef.current[index + 1]?.focus();
    }

    if (nextValues.every((item) => item !== '')) {
      onComplete(nextValues.join(''));
    }
  };

  const handleKeyDown = (event, index) => {
    if (event.key === 'Backspace' && !values[index] && index > 0) {
      inputsRef.current[index - 1]?.focus();
    }
  };

  return (
    <div className="otp-wrapper">
      {values.map((value, index) => (
        <input
          key={index}
          ref={(element) => {
            inputsRef.current[index] = element;
          }}
          className="otp-input"
          maxLength={1}
          value={value}
          onChange={(event) => updateValue(index, event.target.value)}
          onKeyDown={(event) => handleKeyDown(event, index)}
        />
      ))}
    </div>
  );
}

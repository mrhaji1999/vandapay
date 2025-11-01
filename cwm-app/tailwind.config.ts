import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: ['class'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        border: 'hsl(214, 32%, 91%)',
        background: 'hsl(210, 40%, 98%)',
        foreground: 'hsl(222, 47%, 11%)',
        primary: {
          DEFAULT: 'hsl(222, 47%, 11%)',
          foreground: 'hsl(210, 40%, 98%)'
        },
        secondary: {
          DEFAULT: 'hsl(210, 40%, 96%)',
          foreground: 'hsl(222, 47%, 11%)'
        },
        muted: {
          DEFAULT: 'hsl(210, 40%, 96%)',
          foreground: 'hsl(215, 20%, 40%)'
        },
        input: 'hsl(214, 32%, 91%)',
        accent: 'hsl(210, 40%, 96%)',
        destructive: {
          DEFAULT: 'hsl(0, 84%, 60%)',
          foreground: 'hsl(210, 40%, 98%)'
        }
      },
      borderRadius: {
        lg: '0.75rem',
        md: '0.5rem',
        sm: '0.375rem'
      }
    }
  },
  plugins: []
};

export default config;

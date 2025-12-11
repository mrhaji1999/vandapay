import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: ['class'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        border: '#E5E7EB',
        background: '#F3F4F6',
        foreground: '#1F2937',
        primary: {
          DEFAULT: '#4F46E5',
          foreground: '#FFFFFF'
        },
        secondary: {
          DEFAULT: '#F9FAFB',
          foreground: '#1F2937'
        },
        muted: {
          DEFAULT: '#F3F4F6',
          foreground: '#6B7280'
        },
        input: '#E5E7EB',
        accent: {
          DEFAULT: '#4F46E5',
          foreground: '#FFFFFF'
        },
        destructive: {
          DEFAULT: '#EF4444',
          foreground: '#FFFFFF'
        },
        success: {
          DEFAULT: '#10B981',
          foreground: '#FFFFFF'
        },
        warning: {
          DEFAULT: '#F59E0B',
          foreground: '#FFFFFF'
        },
        sidebar: {
          DEFAULT: '#F9FAFB',
          foreground: '#1F2937',
          hover: '#F3F4F6',
          active: '#4F46E5'
        }
      },
      borderRadius: {
        lg: '1rem',
        md: '0.75rem',
        sm: '0.5rem'
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03)',
        'card-hover': '0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03)',
        'sidebar': '1px 0 0 0 rgba(0, 0, 0, 0.05)'
      }
    }
  },
  plugins: []
};

export default config;

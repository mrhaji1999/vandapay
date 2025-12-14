import type { Config } from 'tailwindcss';

const config: Config = {
  darkMode: ['class'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        border: '#E5E7EB',
        background: '#F8FAFC',
        foreground: '#0F172A',
        primary: {
          DEFAULT: '#2563EB',
          foreground: '#FFFFFF'
        },
        secondary: {
          DEFAULT: '#F1F5F9',
          foreground: '#0F172A'
        },
        muted: {
          DEFAULT: '#F8FAFC',
          foreground: '#64748B'
        },
        input: '#E2E8F0',
        accent: {
          DEFAULT: '#2563EB',
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
        info: {
          DEFAULT: '#3B82F6',
          foreground: '#FFFFFF'
        },
        sidebar: {
          DEFAULT: '#FFFFFF',
          foreground: '#0F172A',
          hover: '#F1F5F9',
          active: '#2563EB',
          border: '#E2E8F0'
        },
        card: {
          DEFAULT: '#FFFFFF',
          hover: '#F8FAFC'
        }
      },
      borderRadius: {
        lg: '1rem',
        md: '0.75rem',
        sm: '0.5rem',
        xl: '1.25rem',
        '2xl': '1.5rem'
      },
      boxShadow: {
        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
        'card-hover': '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
        'sidebar': '-1px 0 0 0 rgba(0, 0, 0, 0.05)',
        'elevated': '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)'
      },
      backgroundImage: {
        'gradient-primary': 'linear-gradient(135deg, #2563EB 0%, #1D4ED8 100%)',
        'gradient-success': 'linear-gradient(135deg, #10B981 0%, #059669 100%)',
        'gradient-warning': 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)',
        'gradient-info': 'linear-gradient(135deg, #3B82F6 0%, #2563EB 100%)'
      }
    }
  },
  plugins: []
};

export default config;

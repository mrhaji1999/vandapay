import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  base: '/',
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    sourcemap: false,
    minify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom', 'react-router-dom'],
          'chart-vendor': ['recharts'],
        },
      },
    },
  },
  server: {
    port: 3000,
    proxy: {
      '/wp-json': {
        target: 'https://mr.vandapay.com',
        changeOrigin: true,
        secure: true,
      },
    },
  },
})

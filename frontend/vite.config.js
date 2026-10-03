import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: '/react/',
  appType: 'spa',
  build: {
    outDir: '../react',
    emptyOutDir: true,
  },
  server: {
    open: '/react/',
    proxy: {
      '/api.php': { target: 'http://127.0.0.1:8000', changeOrigin: false },
      '/index.php': { target: 'http://127.0.0.1:8000', changeOrigin: false },
      '/game.php': { target: 'http://127.0.0.1:8000', changeOrigin: false },
      '/cronjob.php': { target: 'http://127.0.0.1:8000', changeOrigin: false },
    },
  },
})

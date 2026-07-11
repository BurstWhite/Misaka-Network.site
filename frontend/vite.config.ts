import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  base: './',
  plugins: [vue()],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  build: {
    outDir: '../theme/Misaka/assets',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: fileURLToPath(new URL('./src/main.ts', import.meta.url)),
      output: {
        entryFileNames: 'app.js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (asset) => asset.names?.some((name) => name.endsWith('.css')) ? 'app.css' : 'assets/[name]-[hash][extname]',
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined
          if (id.includes('vue-i18n')) return 'i18n'
          if (id.includes('axios')) return 'http'
          return 'vendor'
        },
      },
    },
  },
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: { '/api': 'http://127.0.0.1:7001' },
  },
  test: { environment: 'node', setupFiles: ['./tests/setup.ts'], exclude: ['tests/e2e/**', 'node_modules/**'] },
})

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } },
  build: {
    outDir: '.preview', emptyOutDir: true, cssCodeSplit: false,
    rollupOptions: { input: fileURLToPath(new URL('./src/main.ts', import.meta.url)), output: { inlineDynamicImports: true, entryFileNames: 'preview.js', assetFileNames: 'preview.css' } },
  },
})

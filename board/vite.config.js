// AI Ask Desk's dashboard: one script and one stylesheet in com_askdesk's media, loaded by the console
// only when no chat is open. React and the rest are bundled in; nothing is fetched from elsewhere.
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  define: { 'process.env.NODE_ENV': '"production"' },
  build: {
    outDir: '../src/com_askdesk/media',
    emptyOutDir: false,
    copyPublicDir: false,
    cssCodeSplit: false,
    sourcemap: false,
    target: 'es2020',
    lib: {
      entry: 'src/main.jsx',
      name: 'AskdeskBoard',
      formats: ['iife'],
      fileName: () => 'js/board.js',
    },
    rollupOptions: {
      output: { assetFileNames: 'css/board.css' },
    },
  },
});

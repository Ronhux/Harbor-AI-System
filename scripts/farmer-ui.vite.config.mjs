import tailwindcss from '@tailwindcss/vite';
import {defineConfig} from 'vite';
const entries=['scripts/farmer-review-ui-test.html','scripts/phase2-workflows-ui-test.html'];
export default defineConfig({plugins:[tailwindcss()],server:{host:'127.0.0.1',port:5184,strictPort:true},appType:'mpa',optimizeDeps:{entries},build:{rollupOptions:{input:entries},outDir:'storage/app/private/phase2-ui-regression',emptyOutDir:false}});

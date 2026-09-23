import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { createSvgIconsPlugin } from 'vite-plugin-svg-icons';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/app.js', 'resources/js/styles/index.scss'],
      publicDirectory: 'public',
      refresh: true,
    }),
    vue({
      template: {
        // Templates all over this codebase use root-absolute `src="/images/..."`
        // paths to reference files in public/images directly (not bundled
        // assets). Without this override those references get treated as
        // resolvable module imports during production builds and fail.
        transformAssetUrls: {
          includeAbsolute: false,
        },
      },
    }),
    createSvgIconsPlugin({
      // Matches the old svg-sprite-loader config in webpack.mix.js/webpack.config.js:
      // only resources/js/icons/svg, symbol ids of the form "icon-[name]".
      iconDirs: [path.resolve(__dirname, 'resources/js/icons/svg')],
      symbolId: 'icon-[name]',
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js'),
      // Legacy webpack sass-loader/less-loader "~foo" => node_modules/foo
      // convention, used throughout element-ui's own theme-chalk SCSS as well
      // as our own element-variables.scss. This is Vite's documented fix.
      '~': '',
    },
    // Vite's default extensions list doesn't include .vue.
    extensions: ['.mjs', '.js', '.vue', '.json'],
  },
  server: {
    // Laravel serves the app from a PHP dev server / XAMPP on a different
    // port than Vite's own dev server; this keeps HMR working across origins.
    cors: true,
  },
  build: {
    // xlsx (SheetJS, for Excel import/export) is a genuinely large third-party library, and it's already
    // only ever loaded on demand (dynamic `import('xlsx')`) by the handful of pages that need it — never
    // part of what a visitor downloads up front. 700 quiets that one known, already-lazy case without
    // hiding a future regression in the chunks everyone actually downloads on every visit.
    chunkSizeWarningLimit: 700,
    rollupOptions: {
      output: {
        manualChunks: {
          // Split out so it's cached separately from application code — it changes far less often than
          // app code does, so a deploy that only touches app code doesn't force it to be re-downloaded.
          'vendor-element-plus': ['element-plus', '@element-plus/icons-vue'],
          'vendor-moment': ['moment'],
        },
      },
    },
  },
});

import SvgIcon from '@/components/SvgIcon';
// Virtual module provided by vite-plugin-svg-icons — injects all icons under
// resources/js/icons/svg as an SVG sprite, replacing webpack's require.context
// + svg-sprite-loader combination this used previously.
import 'virtual:svg-icons-register';

// Vue 3 has no global `Vue.component()` — global registration has to happen
// on the specific app instance, so this exports an install function for
// app.js to call after createApp() instead of registering as an import
// side-effect.
export default function installIcons(app) {
  app.component('svg-icon', SvgIcon);
}

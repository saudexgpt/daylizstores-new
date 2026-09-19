// These two must be kept in sync with $color-navy / $color-accent in
// styles/_tokens.scss (and $--color-primary in element-variables.scss).
// They used to be imported from that file directly via Sass's :export {} (an
// ICSS feature webpack's sass-loader supported); Vite's CSS pipeline doesn't
// provide a JS-import path for Sass variables, so the values are duplicated
// here instead.
const THEME_PRIMARY = '#192ea7';
const THEME_SECONDARY = '#be1712';

export default {
  /**
   * @type {String}
   */
  title: 'DayLiz Stores | Your Happiness, Our Priority',
  companyName: 'DayLiz Stores',
  theme: THEME_PRIMARY,
  secondaryTheme: THEME_SECONDARY,
  /**
   * @type {boolean} true | false
   * @description Whether show the settings right-panel
   */
  showSettings: false,

  /**
   * @type {boolean} true | false
   * @description Whether need tagsView
   */
  tagsView: true,

  /**
   * @type {boolean} true | false
   * @description Whether fix the header
   */
  fixedHeader: true,

  /**
   * @type {boolean} true | false
   * @description Whether show the logo in sidebar
   */
  sidebarLogo: true,

  /**
   * @type {string | array} 'production' | ['production','development']
   * @description Need show err logs component.
   * The default is only used in the production env
   * If you want to also use it in dev, you can pass ['production','development']
   */
  errorLog: 'production',
};

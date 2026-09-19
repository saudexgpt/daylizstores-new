import { createI18n } from 'vue-i18n';
import Cookies from 'js-cookie';
import elementEnLocale from 'element-plus/es/locale/lang/en';
import elementRuLocale from 'element-plus/es/locale/lang/ru';
import elementZhLocale from 'element-plus/es/locale/lang/zh-cn';
import elementViLocale from 'element-plus/es/locale/lang/vi';
import enLocale from './en';
import ruLocale from './ru';
import zhLocale from './zh';
import viLocale from './vi';

// Element Plus locale objects are consumed directly by
// app.use(ElementPlus, { locale }) (see app.js) — unlike element-ui's old
// locale files, they're not plain key/value pairs that can be merged into
// vue-i18n's own messages tree, so the two are kept separate now.
const elementLocales = {
  en: elementEnLocale,
  ru: elementRuLocale,
  zh: elementZhLocale,
  vi: elementViLocale,
};

const messages = {
  en: enLocale,
  ru: ruLocale,
  zh: zhLocale,
  vi: viLocale,
};

export function getLanguage() {
  const chooseLanguage = Cookies.get('language');
  if (chooseLanguage) {
    return chooseLanguage;
  }

  // if has not choose language
  const language = (navigator.language || navigator.browserLanguage).toLowerCase();
  const locales = Object.keys(messages);
  for (const locale of locales) {
    if (language.indexOf(locale) > -1) {
      return locale;
    }
  }
  return 'en';
}

export function getElementLocale(language) {
  return elementLocales[language] || elementLocales.en;
}

const i18n = createI18n({
  legacy: true, // Options API components use this.$t()/$i18n, not the Composition API
  // set locale
  // options: en | ru | vi | zh
  locale: getLanguage(),
  // set locale messages
  messages,
});

export default i18n;

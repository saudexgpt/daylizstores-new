import i18n from '@/lang';

// translate router.meta.title, be used in breadcrumb sidebar tagsview
//
// A plain function on the shared vue-i18n instance. (It used to read `this.$te` /
// `this.$t`, so it only worked when a component bound it as a method — calling
// it from anywhere else threw "Cannot read properties of undefined ($te)".)
export function generateTitle(title) {
  const { te, t } = i18n.global;
  const key = 'route.' + title;

  return te(key) ? t(key) : title;
}

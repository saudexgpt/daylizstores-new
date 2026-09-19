<template>
  <component :is="link.tag" v-bind="link.attrs">
    <slot />
  </component>
</template>

<script>
import { RouterLink } from 'vue-router';
import { isExternal } from '@/utils/validate';

export default {
  props: {
    to: {
      type: String,
      required: true,
    },
  },
  computed: {
    // Vue 3 no longer reads `is` out of a v-bind object, so the tag is returned separately.
    link() {
      if (isExternal(this.to)) {
        return { tag: 'a', attrs: { href: this.to, target: '_blank', rel: 'noopener' } };
      }
      return { tag: RouterLink, attrs: { to: this.to } };
    },
  },
};
</script>

<template>
  <div ref="rightPanel" :class="{show:show}" class="rightPanel-container">
    <div class="rightPanel-background" />
    <div class="rightPanel">
      <!--
        The cart/wishlist floating "handle-button" triggers that used to live
        here were removed — SiteHeader's cart/wishlist icons now open this
        panel via the `open()` method (see Public.vue's template ref), so
        having both would be a redundant, unstyled leftover trigger floating
        over the new design. Compare's trigger was already commented out
        before this redesign.
      -->
      <div class="rightPanel-items padded">
        <button type="button" class="rightPanel-close" aria-label="Close" @click="togglePanel()">×</button>
        <!-- <slot /> -->
        <cart v-if="activeView === 'cart'" @close="togglePanel" />
        <compare v-if="activeView === 'compare'" @close="togglePanel" />
        <wish-list v-if="activeView === 'wish_list'" @close="togglePanel" />
      </div>
    </div>
  </div>
</template>

<script>
import Cart from '@/pages/partials/Cart';
import Compare from '@/pages/partials/Compare';
import WishList from '@/pages/partials/WishList';
export default {
  name: 'RightPanel',
  components: {
    Cart, Compare, WishList,
  },
  props: {
    clickNotClose: {
      default: false,
      type: Boolean,
    },
    showPanel: {
      default: false,
      type: Boolean,
    },
  },
  data() {
    return {
      show: false,
      activeView: '',
      form: {
        cart_items: [],
        amount: 0,
      },
    };
  },
  mounted() {
    this.show = this.showPanel;
    this.insertToBody();
  },
  beforeUnmount() {
    const elx = this.$refs.rightPanel;
    elx.remove();
  },
  methods: {
    // Imperative open, called by SiteHeader (via Public.vue's template ref)
    // so its cart/wishlist icons can open this panel — a plain method call
    // avoids the staleness issues a one-shot "open" prop would have across
    // repeated open/close cycles.
    open(viewName) {
      this.show = true;
      this.activeView = viewName;
    },
    addEventClick() {
      window.addEventListener('click', this.closeSidebar);
    },
    closeSidebar(evt) {
      const parent = evt.target.closest('.rightPanel');
      if (!parent) {
        this.show = false;
        window.removeEventListener('click', this.closeSidebar);
      }
    },
    insertToBody() {
      const elx = this.$refs.rightPanel;
      const body = document.querySelector('body');
      body.insertBefore(elx, body.firstChild);
    },
    togglePanel() {
      const app = this;
      app.show = !app.show;
      app.activeView = '';
    },
  },
};
</script>

<style>
.showRightPanel {
  overflow: hidden;
  position: relative;
  width: calc(100% - 15px);
}
</style>

<style lang="scss" scoped>
.rightPanel-background {
  opacity: 0;
  transition: opacity .3s cubic-bezier(.7, .3, .1, 1);
  background: rgba(0, 0, 0, .2);
  width: 0;
  height: 0;
  top: 0;
  left: 0;
  position: fixed;
  z-index: -1;
}

.rightPanel {
  background: #fff;
  z-index: 3000;
  position: fixed;
  height: 100vh;
  width: 100%;
  max-width: 460px;
  top: 0px;
  left: 0px;
  box-shadow: 0px 0px 15px 0px rgba(0, 0, 0, .05);
  transition: all .25s cubic-bezier(.7, .3, .1, 1);
  transform: translate(100%);
  z-index: 100;
  left: auto;
  right: 0px;
}

.rightPanel-items {
  position: absolute;
  inset: 0;
  overflow-y: auto;
}

.show {
  transition: all .3s cubic-bezier(.7, .3, .1, 1);

  .rightPanel-background {
    z-index: 50;
    opacity: 1;
    width: 100%;
    height: 100%;
  }

  .rightPanel {
    transform: translate(0);
  }
}

.rightPanel-close {
  position: absolute;
  top: 16px;
  right: 20px;
  background: none;
  border: none;
  font-size: 28px;
  line-height: 1;
  color: var(--color-text-muted, #6b6b63);
  cursor: pointer;

  &:hover {
    color: var(--color-accent, #c1642a);
  }
}
</style>

import { useAppStore } from '@/store';

const { body } = document;
const WIDTH = 992; // refer to Bootstrap's responsive design

export default {
  watch: {
    $route(route) {
      if (this.device === 'mobile' && this.sidebar.opened) {
        useAppStore().closeSideBar({ withoutAnimation: false });
      }
    },
  },
  beforeMount() {
    window.addEventListener('resize', this.resizeHandler);
  },
  beforeUnmount() {
    window.removeEventListener('resize', this.resizeHandler);
  },
  mounted() {
    const isMobile = this.isMobile();
    if (isMobile) {
      useAppStore().toggleDevice('mobile');
      useAppStore().closeSideBar({ withoutAnimation: true });
    }
  },
  methods: {
    isMobile() {
      const rect = body.getBoundingClientRect();
      return rect.width - 1 < WIDTH;
    },
    resizeHandler() {
      if (!document.hidden) {
        const isMobile = this.isMobile();
        useAppStore().toggleDevice(isMobile ? 'mobile' : 'desktop');

        if (isMobile) {
          useAppStore().closeSideBar({ withoutAnimation: true });
        }
      }
    },
  },
};

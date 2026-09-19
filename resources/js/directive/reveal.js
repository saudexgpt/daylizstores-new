// v-reveal: fades/translates an element in once it scrolls into view.
// CSS-only transition (no animation library) — this directive just toggles
// a class via IntersectionObserver.
const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.15 },
);

export default {
  mounted(el) {
    el.classList.add('reveal-on-scroll');
    observer.observe(el);
  },
  unmounted(el) {
    observer.unobserve(el);
  },
};

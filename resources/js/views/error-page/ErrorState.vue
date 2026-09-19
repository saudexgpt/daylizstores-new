<template>
  <div class="error-state">
    <p class="error-state__code" aria-hidden="true">{{ code }}</p>
    <h1 class="error-state__title">{{ title }}</h1>
    <p class="error-state__message">{{ message }}</p>
    <div class="error-state__actions">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  code: { type: String, required: true },
  title: { type: String, required: true },
  message: { type: String, default: '' },
});
</script>

<style lang="scss" scoped>
// Used both inside the admin layout (/error/*) and on its own (/404, /401 for
// signed-out visitors), so it centres itself and doesn't rely on the admin shell.
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: min(70vh, 640px);
  padding: 40px 20px;
  text-align: center;
  color: var(--admin-text, #1c2340);
  font-family: var(--font-sans, 'Inter', sans-serif);

  &__code {
    margin: 0;
    font-size: clamp(88px, 18vw, 168px);
    font-weight: 800;
    line-height: 1;
    letter-spacing: -0.04em;
    background: linear-gradient(135deg, var(--admin-primary, #192ea7) 0%, var(--admin-accent, #be1712) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    user-select: none;
  }

  &__title {
    margin: 12px 0 0;
    font-size: 26px;
    font-weight: 700;
  }

  &__message {
    max-width: 460px;
    margin: 10px 0 0;
    font-size: 15px;
    line-height: 1.6;
    color: var(--admin-muted, #6b7394);
  }

  &__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 28px;
  }
}
</style>

<template>
  <div class="empty-state">
    <i v-if="icon" :class="icon" class="empty-state__icon" aria-hidden="true" />
    <p class="empty-state__message">{{ message }}</p>
    <BaseButton v-if="ctaText" :to="ctaTo" variant="secondary" @click="emit('cta')">
      {{ ctaText }}
    </BaseButton>
  </div>
</template>

<script setup>
import BaseButton from './BaseButton.vue';

defineProps({
  icon: {
    type: String,
    default: '',
  },
  message: {
    type: String,
    required: true,
  },
  ctaText: {
    type: String,
    default: '',
  },
  ctaTo: {
    type: [String, Object],
    default: null,
  },
});

const emit = defineEmits(['cta']);
</script>

<style lang="scss" scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 64px 24px;

  &__icon {
    font-size: 40px;
    color: var(--color-border);
    margin-bottom: 20px;
  }

  &__message {
    font-family: var(--font-sans);
    font-size: 15px;
    color: var(--color-text-muted);
    margin: 0 0 24px;
  }
}
</style>

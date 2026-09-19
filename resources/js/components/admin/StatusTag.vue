<template>
  <span class="status-tag" :class="`status-tag--${resolvedTone}`">
    <span class="status-tag__dot" aria-hidden="true" />
    {{ text }}
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: [String, Number, Boolean], default: '' },
  // order | payment | enabled | generic (tone given directly)
  kind: { type: String, default: 'order' },
  tone: { type: String, default: '' },
  label: { type: String, default: '' },
});

const TONES = {
  order: { Pending: 'warning', CARP: 'info', 'On Transit': 'info', Delivered: 'success', Cancelled: 'danger' },
  payment: { pending: 'warning', paid: 'success', cancelled: 'danger', carp: 'info' },
};

const isOn = computed(() => props.status === true || props.status === 1 || props.status === '1');

const resolvedTone = computed(() => {
  if (props.tone) {
    return props.tone;
  }
  if (props.kind === 'enabled') {
    return isOn.value ? 'success' : 'neutral';
  }
  return (TONES[props.kind] && TONES[props.kind][props.status]) || 'neutral';
});

const text = computed(() => {
  if (props.label) {
    return props.label;
  }
  if (props.kind === 'enabled') {
    return isOn.value ? 'Active' : 'Disabled';
  }
  if (props.kind === 'payment' && props.status) {
    return String(props.status).charAt(0).toUpperCase() + String(props.status).slice(1);
  }
  return props.status === '' || props.status === null ? '—' : String(props.status);
});
</script>

<style lang="scss" scoped>
.status-tag {
  --tone: var(--admin-muted);
  --tone-soft: #eef0f6;

  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 4px 11px 4px 9px;
  border-radius: 999px;
  background: var(--tone-soft);
  color: var(--tone);
  font-size: 12px;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;

  &__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: currentColor;
  }

  &--success { --tone: var(--admin-success); --tone-soft: var(--admin-success-soft); }
  &--warning { --tone: var(--admin-warning); --tone-soft: var(--admin-warning-soft); }
  &--danger { --tone: var(--admin-danger); --tone-soft: var(--admin-danger-soft); }
  &--info { --tone: var(--admin-info); --tone-soft: var(--admin-info-soft); }
}
</style>

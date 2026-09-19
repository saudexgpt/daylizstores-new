<template>
  <el-date-picker
    :model-value="modelValue"
    type="daterange"
    range-separator="to"
    start-placeholder="From"
    end-placeholder="To"
    value-format="YYYY-MM-DD"
    format="D MMM YYYY"
    :shortcuts="shortcuts"
    :clearable="false"
    :disabled-date="disabledDate"
    class="period-picker"
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>

<script setup>
import { computed } from 'vue';
import { periodPresets } from '@/utils/reportFormat';

/**
 * A date range with the reporting periods people actually ask for (this month, last month,
 * this quarter, this year …). v-model is [from, to] as 'YYYY-MM-DD' strings.
 */
const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  // transactions can't be dated in the future; reports are happy to look at any range
  noFuture: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);

const shortcuts = computed(() => periodPresets().map(p => ({
  text: p.label,
  value: () => [new Date(p.from + 'T00:00:00'), new Date(p.to + 'T00:00:00')],
})));

const disabledDate = (date) => props.noFuture && date.getTime() > Date.now();
</script>

<style scoped>
.period-picker {
  max-width: 100%;
}
</style>

<template>
  <div class="mt">
    <div class="mt__legend">
      <span class="mt__key"><i class="mt__swatch mt__swatch--income" />Income</span>
      <span class="mt__key"><i class="mt__swatch mt__swatch--cost" />Costs &amp; expenses</span>
      <span class="mt__key"><i class="mt__swatch mt__swatch--net" />Profit</span>
    </div>

    <div class="mt__plot" @mouseleave="hover = -1">
      <svg :viewBox="`0 0 ${W} ${H}`" class="mt__svg" role="img" :aria-label="summary">
        <g v-for="tick in ticks" :key="tick.value">
          <line :x1="M.left" :x2="W - M.right" :y1="tick.y" :y2="tick.y" :class="tick.value === 0 ? 'mt__zero' : 'mt__grid'" />
          <text :x="M.left - 8" :y="tick.y + 4" class="mt__axis" text-anchor="end">{{ compact(tick.value) }}</text>
        </g>

        <g v-for="(m, i) in months" :key="m.month" @mouseenter="hover = i">
          <rect :x="m.slotX" :y="M.top" :width="m.slotW" :height="innerH" fill="transparent" />
          <rect :x="m.incomeX" :y="m.incomeY" :width="m.barW" :height="m.incomeH" rx="3" class="mt__bar mt__bar--income" :class="{ 'is-active': hover === i }" />
          <rect :x="m.costX" :y="m.costY" :width="m.barW" :height="m.costH" rx="3" class="mt__bar mt__bar--cost" :class="{ 'is-active': hover === i }" />
          <text :x="m.cx" :y="H - 10" class="mt__axis" text-anchor="middle">{{ m.label }}</text>
        </g>

        <polyline :points="linePoints" class="mt__line" />
        <circle v-for="(m, i) in months" :key="'n' + m.month" :cx="m.cx" :cy="m.netY" :r="hover === i ? 5 : 3" class="mt__dot" :class="{ 'is-loss': m.net < 0 }" />
      </svg>

      <div v-if="hover >= 0" class="mt__tip" :style="tipStyle">
        <strong>{{ months[hover].full }}</strong>
        <span>Income {{ naira(months[hover].income) }}</span>
        <span>Costs &amp; expenses {{ naira(months[hover].costs) }}</span>
        <span :class="months[hover].net < 0 ? 'is-loss' : 'is-profit'">{{ months[hover].net < 0 ? 'Loss' : 'Profit' }} {{ naira(Math.abs(months[hover].net)) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { naira } from '@/utils/reportFormat';

const props = defineProps({
  // [{ month: 'YYYY-MM', income, cost_of_sales, expenses, net }]
  points: { type: Array, default: () => [] },
});

const W = 760;
const H = 300;
const M = { top: 14, right: 16, bottom: 32, left: 58 };
const innerW = W - M.left - M.right;
const innerH = H - M.top - M.bottom;
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

const hover = ref(-1);

// a "nice" step so the axis lands on round numbers
const niceStep = (span) => {
  const raw = span / 4;
  const pow = 10 ** Math.floor(Math.log10(raw || 1));
  const n = raw / pow;
  return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow;
};

const scale = computed(() => {
  const top = Math.max(1, ...props.points.map(p => Math.max(p.income, p.cost_of_sales + p.expenses)));
  const bottom = Math.min(0, ...props.points.map(p => p.net));
  const step = niceStep(top - bottom);
  return { min: Math.floor(bottom / step) * step, max: Math.ceil(top / step) * step, step };
});

const y = (value) => M.top + innerH - ((value - scale.value.min) / (scale.value.max - scale.value.min || 1)) * innerH;

const ticks = computed(() => {
  const out = [];
  for (let v = scale.value.min; v <= scale.value.max + 1e-9; v += scale.value.step) {
    out.push({ value: v, y: y(v) });
  }
  return out;
});

const months = computed(() => {
  const n = props.points.length || 1;
  const slotW = innerW / n;
  const barW = Math.min(20, slotW * 0.34);
  const zero = y(0);
  return props.points.map((p, i) => {
    const slotX = M.left + slotW * i;
    const cx = slotX + slotW / 2;
    const costs = p.cost_of_sales + p.expenses;
    const [yr, mo] = p.month.split('-').map(Number);
    return {
      ...p, costs, slotX, slotW, cx, barW,
      incomeX: cx - barW - 1, costX: cx + 1,
      incomeY: Math.min(y(p.income), zero), incomeH: Math.abs(zero - y(p.income)),
      costY: Math.min(y(costs), zero), costH: Math.abs(zero - y(costs)),
      netY: y(p.net),
      label: MONTHS[mo - 1],
      full: `${MONTHS[mo - 1]} ${yr}`,
    };
  });
});

const linePoints = computed(() => months.value.map(m => `${m.cx},${m.netY}`).join(' '));

const tipStyle = computed(() => {
  if (hover.value < 0) {
    return {};
  }
  return { left: `${Math.min(84, Math.max(16, (months.value[hover.value].cx / W) * 100))}%` };
});

const compact = (v) => {
  const a = Math.abs(v);
  const sign = v < 0 ? '-' : '';
  if (a >= 1e6) return `${sign}${+(a / 1e6).toFixed(1)}M`;
  if (a >= 1e3) return `${sign}${+(a / 1e3).toFixed(1)}k`;
  return `${v}`;
};

const summary = computed(() => {
  const income = props.points.reduce((s, p) => s + p.income, 0);
  const net = props.points.reduce((s, p) => s + p.net, 0);
  return `Income ${naira(income)} and ${net < 0 ? 'loss' : 'profit'} ${naira(Math.abs(net))} over the last ${props.points.length} months`;
});
</script>

<style lang="scss" scoped>
.mt {
  &__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 20px;
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--admin-muted);
  }

  &__key {
    display: inline-flex;
    align-items: center;
    gap: 7px;
  }

  &__swatch {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 3px;

    &--income { background: var(--admin-success); }
    &--cost { background: var(--admin-warning); }
    &--net { height: 3px; border-radius: 2px; background: var(--admin-primary); }
  }

  &__plot {
    position: relative;
  }

  &__svg {
    display: block;
    width: 100%;
    height: auto;
    overflow: visible;
  }

  &__grid { stroke: var(--admin-border); stroke-width: 1; }
  &__zero { stroke: var(--admin-muted); stroke-width: 1.2; }
  &__axis { fill: var(--admin-muted); font-size: 11px; }

  &__bar {
    transition: opacity 0.15s;

    &--income { fill: var(--admin-success); }
    &--cost { fill: var(--admin-warning); }
    &:not(.is-active) { opacity: 0.85; }
  }

  &__line {
    fill: none;
    stroke: var(--admin-primary);
    stroke-width: 2.5;
    stroke-linejoin: round;
  }

  &__dot {
    fill: var(--admin-primary);
    stroke: var(--admin-surface);
    stroke-width: 2;

    &.is-loss { fill: var(--admin-danger); }
  }

  &__tip {
    position: absolute;
    top: 0;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 12px;
    background: var(--admin-text);
    color: #fff;
    border-radius: 8px;
    font-size: 12px;
    white-space: nowrap;
    pointer-events: none;

    .is-profit { color: #7ee2a8; }
    .is-loss { color: #ff9b9b; }
  }
}
</style>

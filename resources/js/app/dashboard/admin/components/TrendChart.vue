<template>
  <div class="trend">
    <div class="trend__legend">
      <span class="trend__key"><i class="trend__swatch trend__swatch--orders" />Orders per day</span>
      <span class="trend__key"><i class="trend__swatch trend__swatch--revenue" />Revenue received</span>
    </div>

    <div class="trend__plot" @mouseleave="hover = -1">
      <svg :viewBox="`0 0 ${W} ${H}`" class="trend__svg" role="img" :aria-label="summary">
        <!-- horizontal grid + order-count axis -->
        <g v-for="tick in yTicks" :key="tick.value">
          <line :x1="M.left" :x2="W - M.right" :y1="tick.y" :y2="tick.y" class="trend__grid" />
          <text :x="M.left - 10" :y="tick.y + 4" class="trend__axis" text-anchor="end">{{ tick.value }}</text>
        </g>

        <g v-for="(bar, i) in bars" :key="bar.date" @mouseenter="hover = i">
          <!-- a full-height hit area, so thin bars are still easy to hover -->
          <rect :x="bar.slotX" :y="M.top" :width="bar.slotW" :height="innerH" fill="transparent" />
          <rect
            :x="bar.x"
            :y="bar.y"
            :width="bar.w"
            :height="Math.max(bar.h, bar.orders > 0 ? 3 : 0)"
            rx="4"
            class="trend__bar"
            :class="{ 'is-active': hover === i }"
          />
          <text v-if="i % labelEvery === 0" :x="bar.cx" :y="H - 10" class="trend__axis" text-anchor="middle">
            {{ bar.label }}
          </text>
        </g>

        <polyline v-if="hasRevenue" :points="linePoints" class="trend__line" />
        <circle
          v-for="(bar, i) in bars"
          v-show="hasRevenue && (hover === i || bar.revenue > 0)"
          :key="'p' + bar.date"
          :cx="bar.cx"
          :cy="bar.ry"
          :r="hover === i ? 5 : 3"
          class="trend__dot"
        />
      </svg>

      <div v-if="hover >= 0" class="trend__tip" :style="tipStyle">
        <strong>{{ bars[hover].full }}</strong>
        <span>{{ bars[hover].orders }} {{ bars[hover].orders === 1 ? 'order' : 'orders' }}</span>
        <span>{{ currency }}{{ money(bars[hover].revenue) }} received</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import moment from 'moment';
import { formatNumber } from '@/utils/index';

const props = defineProps({
  // [{ date: 'YYYY-MM-DD', orders: Number, revenue: Number }]
  points: { type: Array, default: () => [] },
  currency: { type: String, default: '₦' },
});

const W = 720;
const H = 270;
const M = { top: 14, right: 16, bottom: 34, left: 40 };
const innerW = W - M.left - M.right;
const innerH = H - M.top - M.bottom;

const hover = ref(-1);

const money = (value) => formatNumber(value, 0);

// round the axis up to a "nice" maximum so the grid lines land on whole numbers
const maxOrders = computed(() => {
  const raw = Math.max(1, ...props.points.map(p => p.orders));
  const step = raw <= 4 ? 1 : Math.ceil(raw / 4);
  return step * 4;
});
const maxRevenue = computed(() => Math.max(1, ...props.points.map(p => p.revenue)));
const hasRevenue = computed(() => props.points.some(p => p.revenue > 0));

const yTicks = computed(() => [0, 1, 2, 3, 4].map(i => ({
  value: (maxOrders.value / 4) * i,
  y: M.top + innerH - (innerH / 4) * i,
})));

const bars = computed(() => {
  const n = props.points.length || 1;
  const slotW = innerW / n;
  const w = Math.min(28, slotW * 0.55);
  return props.points.map((p, i) => {
    const slotX = M.left + slotW * i;
    const cx = slotX + slotW / 2;
    const h = (p.orders / maxOrders.value) * innerH;
    return {
      ...p,
      slotX,
      slotW,
      cx,
      w,
      x: cx - w / 2,
      h,
      y: M.top + innerH - h,
      ry: M.top + innerH - (p.revenue / maxRevenue.value) * innerH,
      label: moment(p.date).format('D MMM'),
      full: moment(p.date).format('ddd, D MMM YYYY'),
    };
  });
});

const linePoints = computed(() => bars.value.map(b => `${b.cx},${b.ry}`).join(' '));
const labelEvery = computed(() => (props.points.length > 10 ? 2 : 1));

const tipStyle = computed(() => {
  if (hover.value < 0) {
    return {};
  }
  const pct = (bars.value[hover.value].cx / W) * 100;
  // keep the tooltip inside the plot at both edges
  return { left: `${Math.min(86, Math.max(14, pct))}%` };
});

const summary = computed(() => {
  const orders = props.points.reduce((sum, p) => sum + p.orders, 0);
  return `${orders} orders over the last ${props.points.length} days`;
});
</script>

<style lang="scss" scoped>
.trend {
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
    gap: 8px;
  }

  &__swatch {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 4px;

    &--orders { background: var(--admin-primary); }
    &--revenue { background: var(--admin-success); border-radius: 50%; }
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

  &__grid {
    stroke: var(--admin-border);
    stroke-width: 1;
    stroke-dasharray: 3 4;
  }

  &__axis {
    fill: var(--admin-muted);
    font-size: 11.5px;
    font-family: inherit;
  }

  &__bar {
    fill: var(--el-color-primary-light-5);
    transition: fill 0.15s ease;

    &.is-active {
      fill: var(--admin-primary);
    }
  }

  &__line {
    fill: none;
    stroke: var(--admin-success);
    stroke-width: 2.5;
    stroke-linejoin: round;
    stroke-linecap: round;
    pointer-events: none;
  }

  &__dot {
    fill: #fff;
    stroke: var(--admin-success);
    stroke-width: 2.5;
    pointer-events: none;
  }

  &__tip {
    position: absolute;
    top: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 9px 13px;
    border-radius: 10px;
    background: var(--admin-sidebar-bg);
    color: #fff;
    font-size: 12.5px;
    line-height: 1.4;
    white-space: nowrap;
    transform: translateX(-50%);
    pointer-events: none;
    box-shadow: var(--admin-shadow-lg);

    strong { font-size: 13px; }
    span { color: rgba(255, 255, 255, 0.82); }
  }
}
</style>

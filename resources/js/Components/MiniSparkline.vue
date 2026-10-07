<template>
  <div class="relative w-20 h-6 flex items-center justify-center">
    <svg v-if="points.length > 1" :viewBox="`0 0 ${width} ${height}`" class="w-full h-full overflow-visible">
      <defs>
        <linearGradient :id="`grad-${id}`" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" :stop-color="color" stop-opacity="0.3" />
          <stop offset="100%" :stop-color="color" stop-opacity="0.0" />
        </linearGradient>
      </defs>
      <!-- Area fill -->
      <path :d="areaPath" :fill="`url(#grad-${id})`" />
      <!-- Line -->
      <path
        :d="linePath"
        fill="none"
        :stroke="color"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <!-- Last Point Pulse -->
      <circle
        v-if="lastPoint"
        :cx="lastPoint.x"
        :cy="lastPoint.y"
        r="2.5"
        :fill="color"
        class="animate-pulse"
      />
    </svg>
    <div v-else class="text-[10px] text-slate-500 font-num">--</div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  color: {
    type: String,
    default: '#10B981', // Emerald green
  },
  id: {
    type: String,
    default: () => 'spark-' + Math.random().toString(36).substr(2, 9),
  },
});

const width = 80;
const height = 24;

const points = computed(() => {
  if (!props.data || props.data.length === 0) return [];
  const min = Math.min(...props.data);
  const max = Math.max(...props.data);
  const range = max - min || 1;
  const padding = 3;

  return props.data.map((val, idx) => {
    const x = (idx / (props.data.length - 1 || 1)) * (width - 4) + 2;
    const y = height - padding - ((val - min) / range) * (height - padding * 2);
    return { x, y, val };
  });
});

const linePath = computed(() => {
  if (points.value.length < 2) return '';
  return points.value.reduce((acc, pt, i) => {
    return i === 0 ? `M ${pt.x} ${pt.y}` : `${acc} L ${pt.x} ${pt.y}`;
  }, '');
});

const areaPath = computed(() => {
  if (points.value.length < 2) return '';
  const first = points.value[0];
  const last = points.value[points.value.length - 1];
  return `${linePath.value} L ${last.x} ${height} L ${first.x} ${height} Z`;
});

const lastPoint = computed(() => {
  if (points.value.length === 0) return null;
  return points.value[points.value.length - 1];
});
</script>

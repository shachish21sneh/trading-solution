<template>
  <div class="flex flex-col items-center">
    <div class="relative w-28 h-14 overflow-hidden">
      <!-- Arc Background -->
      <div class="w-28 h-28 rounded-full border-[10px] border-slate-800 border-b-transparent border-l-transparent transform -rotate-45"></div>
      
      <!-- Colored Gauge Segment -->
      <svg class="absolute top-0 left-0 w-28 h-28 transform -rotate-90">
        <circle
          cx="56"
          cy="56"
          r="46"
          fill="none"
          stroke="currentColor"
          stroke-width="10"
          stroke-dasharray="144.5"
          :stroke-dashoffset="dashOffset"
          :class="strokeColor"
          class="transition-all duration-700 ease-out"
        />
      </svg>

      <!-- Center Value -->
      <div class="absolute bottom-0 inset-x-0 flex flex-col items-center">
        <span class="text-xl font-bold font-num leading-tight" :class="textColor">
          {{ pcr.toFixed(2) }}
        </span>
      </div>
    </div>
    <span class="text-[11px] text-slate-400 mt-1 uppercase tracking-wider font-medium">
      PCR Ratio
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  pcr: {
    type: Number,
    default: 1.0,
  },
});

// Normalized between 0.4 and 1.8 for the gauge
const percentage = computed(() => {
  const min = 0.4;
  const max = 1.8;
  const clamped = Math.min(max, Math.max(min, props.pcr));
  return (clamped - min) / (max - min);
});

const dashOffset = computed(() => {
  const maxDash = 144.5;
  return maxDash - (percentage.value * maxDash);
});

const strokeColor = computed(() => {
  if (props.pcr >= 1.2) return 'text-emerald-500';
  if (props.pcr <= 0.8) return 'text-rose-500';
  return 'text-amber-500';
});

const textColor = computed(() => {
  if (props.pcr >= 1.2) return 'text-emerald-400';
  if (props.pcr <= 0.8) return 'text-rose-400';
  return 'text-amber-400';
});
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm transition-opacity">
    <div class="bg-[#111827] border border-slate-700/80 rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
      
      <!-- Modal Header -->
      <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/50">
        <div class="flex items-center space-x-3">
          <div class="px-3 py-1 bg-amber-500/10 border border-amber-500/30 rounded-lg text-amber-400 font-bold font-num text-lg">
            {{ strike?.strike_price }}
          </div>
          <div>
            <h3 class="text-base font-semibold text-white flex items-center gap-2">
              <span>Strike Intelligence Analysis</span>
              <span class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-normal">
                {{ strike?.atm_relation }}
              </span>
            </h3>
            <p class="text-xs text-slate-400 font-num">
              Spot Reference: {{ spotPrice }} | Symbol: {{ symbol }}
            </p>
          </div>
        </div>

        <button
          @click="$emit('close')"
          class="p-2 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body -->
      <div class="p-6 overflow-y-auto space-y-6">
        
        <!-- Two Column Comparison: CALL vs PUT -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          
          <!-- CALL CARD -->
          <div class="p-4 rounded-xl bg-emerald-950/20 border border-emerald-800/30">
            <div class="flex items-center justify-between mb-3 border-b border-emerald-900/40 pb-2">
              <span class="text-sm font-bold text-emerald-400">CALL (CE) Intelligence</span>
              <TrendBadge :trend="strike?.call?.current_trend" />
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
              <div>
                <span class="text-slate-400 block">Current OI</span>
                <span class="text-base font-bold font-num text-slate-100">{{ formatNum(strike?.call?.oi) }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">Change in OI</span>
                <span :class="['text-base font-bold font-num', strike?.call?.change_oi >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ strike?.call?.change_oi >= 0 ? '+' : '' }}{{ formatNum(strike?.call?.change_oi) }}
                </span>
              </div>
              <div>
                <span class="text-slate-400 block">Peak OI Reached</span>
                <span class="font-bold font-num text-slate-200">{{ formatNum(strike?.call?.peak_oi) }}</span>
                <span class="text-[10px] text-slate-500 block">at {{ strike?.call?.peak_time || '--' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">Drop from Peak</span>
                <span class="font-bold font-num text-rose-400">{{ strike?.call?.drop_percent }}%</span>
                <span class="text-[10px] text-slate-500 block">-{{ formatNum(strike?.call?.current_vs_peak) }} contracts</span>
              </div>
              <div>
                <span class="text-slate-400 block">Lowest OI (Bottom)</span>
                <span class="font-bold font-num text-slate-300">{{ formatNum(strike?.call?.trend_history?.lowest_point) }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">LTP / Change</span>
                <span class="font-bold font-num text-slate-100">₹{{ strike?.call?.ltp }}</span>
                <span :class="['text-[10px] ml-1 font-num', strike?.call?.change >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  ({{ strike?.call?.change >= 0 ? '+' : '' }}{{ strike?.call?.change }})
                </span>
              </div>
            </div>
          </div>

          <!-- PUT CARD -->
          <div class="p-4 rounded-xl bg-rose-950/20 border border-rose-800/30">
            <div class="flex items-center justify-between mb-3 border-b border-rose-900/40 pb-2">
              <span class="text-sm font-bold text-rose-400">PUT (PE) Intelligence</span>
              <TrendBadge :trend="strike?.put?.current_trend" />
            </div>

            <div class="grid grid-cols-2 gap-3 text-xs">
              <div>
                <span class="text-slate-400 block">Current OI</span>
                <span class="text-base font-bold font-num text-slate-100">{{ formatNum(strike?.put?.oi) }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">Change in OI</span>
                <span :class="['text-base font-bold font-num', strike?.put?.change_oi >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ strike?.put?.change_oi >= 0 ? '+' : '' }}{{ formatNum(strike?.put?.change_oi) }}
                </span>
              </div>
              <div>
                <span class="text-slate-400 block">Peak OI Reached</span>
                <span class="font-bold font-num text-slate-200">{{ formatNum(strike?.put?.peak_oi) }}</span>
                <span class="text-[10px] text-slate-500 block">at {{ strike?.put?.peak_time || '--' }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">Drop from Peak</span>
                <span class="font-bold font-num text-rose-400">{{ strike?.put?.drop_percent }}%</span>
                <span class="text-[10px] text-slate-500 block">-{{ formatNum(strike?.put?.current_vs_peak) }} contracts</span>
              </div>
              <div>
                <span class="text-slate-400 block">Lowest OI (Bottom)</span>
                <span class="font-bold font-num text-slate-300">{{ formatNum(strike?.put?.trend_history?.lowest_point) }}</span>
              </div>
              <div>
                <span class="text-slate-400 block">LTP / Change</span>
                <span class="font-bold font-num text-slate-100">₹{{ strike?.put?.ltp }}</span>
                <span :class="['text-[10px] ml-1 font-num', strike?.put?.change >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  ({{ strike?.put?.change >= 0 ? '+' : '' }}{{ strike?.put?.change }})
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- ApexCharts OI Trend Graph -->
        <div class="p-4 rounded-xl bg-slate-900 border border-slate-800">
          <h4 class="text-sm font-semibold text-slate-200 mb-3 flex items-center justify-between">
            <span>Historical OI Trajectory (Last 30 - 60 Min)</span>
            <span class="text-xs text-slate-400 font-normal">Real-Time Snapshot Multi-Series</span>
          </h4>
          <div class="h-64">
            <apexchart
              type="area"
              height="100%"
              :options="chartOptions"
              :series="chartSeries"
            />
          </div>
        </div>

        <!-- Trend Formation Timeline -->
        <div class="p-4 rounded-xl bg-slate-900/60 border border-slate-800">
          <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-wider mb-3">
            Trend Formation Chronology
          </h4>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
            <div class="p-2.5 rounded-lg bg-slate-800/50">
              <span class="text-slate-400 block">Started Increasing</span>
              <span class="font-num font-semibold text-emerald-400">
                {{ strike?.call?.trend_history?.started_increasing || '--:--:--' }}
              </span>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-800/50">
              <span class="text-slate-400 block">Peak Formed At</span>
              <span class="font-num font-semibold text-amber-400">
                {{ strike?.call?.trend_history?.peak_time || '--:--:--' }}
              </span>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-800/50">
              <span class="text-slate-400 block">Started Decreasing</span>
              <span class="font-num font-semibold text-rose-400">
                {{ strike?.call?.trend_history?.started_decreasing || '--:--:--' }}
              </span>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-800/50">
              <span class="text-slate-400 block">Bottom Reversal Recovery</span>
              <span class="font-num font-semibold text-teal-400">
                {{ strike?.call?.trend_history?.started_recovering || '--:--:--' }}
              </span>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="px-6 py-3 border-t border-slate-800 bg-slate-900/80 flex items-center justify-between text-xs text-slate-400">
        <span>Press ESC or click Outside to close</span>
        <button
          @click="$emit('close')"
          class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-white rounded-lg transition"
        >
          Close
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import TrendBadge from './TrendBadge.vue';

const props = defineProps({
  isOpen: Boolean,
  strike: Object,
  symbol: String,
  spotPrice: [Number, String],
});

defineEmits(['close']);

function formatNum(val) {
  if (val === undefined || val === null) return '--';
  return Number(val).toLocaleString('en-IN');
}

const chartSeries = computed(() => {
  const ceData = props.strike?.call?.sparkline || [10000, 12000, 11500, 14000];
  const peData = props.strike?.put?.sparkline || [9000, 9500, 10500, 11000];

  return [
    { name: 'Call (CE) OI', data: ceData },
    { name: 'Put (PE) OI', data: peData },
  ];
});

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    background: 'transparent',
    fontFamily: 'JetBrains Mono, monospace',
    animations: { enabled: true, easing: 'linear', dynamicAnimation: { speed: 800 } },
  },
  colors: ['#10B981', '#F43F5E'],
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.35,
      opacityTo: 0.05,
      stops: [0, 90, 100],
    },
  },
  dataLabels: { enabled: false },
  grid: {
    borderColor: '#1E293B',
    strokeDashArray: 3,
  },
  xaxis: {
    labels: { style: { colors: '#64748B', fontSize: '10px' } },
    axisBorder: { color: '#334155' },
    axisTicks: { color: '#334155' },
  },
  yaxis: {
    labels: {
      style: { colors: '#64748B', fontSize: '10px' },
      formatter: (v) => formatNum(v),
    },
  },
  tooltip: {
    theme: 'dark',
    y: {
      formatter: (v) => formatNum(v) + ' contracts',
    },
  },
  legend: {
    position: 'top',
    labels: { colors: '#94A3B8' },
  },
}));
</script>

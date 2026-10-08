<template>
  <div class="min-h-screen bg-[#080C14] text-slate-100 flex flex-col selection:bg-emerald-500 selection:text-black">
    
    <!-- Top Terminal Navigation Bar -->
    <header class="border-b border-slate-800/80 bg-[#0D1322]/90 backdrop-blur sticky top-0 z-40 px-4 py-2.5">
      <div class="max-w-[1700px] mx-auto flex flex-wrap items-center justify-between gap-4">
        
        <!-- Left: Logo & Underlying Selector -->
        <div class="flex items-center space-x-6">
          <div class="flex items-center space-x-2.5">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center font-bold text-black text-base shadow-lg shadow-emerald-500/20">
              OI
            </div>
            <div>
              <div class="text-sm font-bold tracking-tight text-white flex items-center gap-1.5">
                <span>TERMINAL</span>
                <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">
                  PRO
                </span>
              </div>
              <p class="text-[10px] text-slate-400 font-mono">OI Intelligence Engine</p>
            </div>
          </div>

          <!-- Symbol Tabs -->
          <div class="flex bg-slate-900/90 p-1 rounded-xl border border-slate-800">
            <button
              v-for="item in underlyings"
              :key="item.symbol"
              @click="switchSymbol(item.symbol)"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200',
                activeSymbol === item.symbol
                  ? 'bg-emerald-500 text-black shadow-md shadow-emerald-500/30'
                  : 'text-slate-400 hover:text-white hover:bg-slate-800/50'
              ]"
            >
              {{ item.symbol }}
            </button>
          </div>

          <!-- Expiry Date Selector -->
          <div class="flex items-center space-x-2 text-xs">
            <span class="text-slate-400 font-medium">Expiry:</span>
            <select
              v-model="currentExpiry"
              @change="fetchLiveData"
              class="bg-slate-900 border border-slate-800 text-slate-200 text-xs rounded-lg px-2.5 py-1.5 focus:ring-1 focus:ring-emerald-500 focus:outline-none font-num"
            >
              <option v-for="exp in availableExpiries" :key="exp" :value="exp">
                {{ formatExpiry(exp) }}
              </option>
            </select>
          </div>
        </div>

        <!-- Center: Real-time Spot Ticker with Flash Animation -->
        <div class="flex items-center space-x-4 bg-slate-900/70 border border-slate-800 px-4 py-1.5 rounded-xl">
          <div>
            <div class="flex items-center justify-between gap-2">
              <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">
                {{ activeSymbol }} SPOT
              </span>
              <span class="text-[9px] font-mono text-amber-400/90 bg-amber-500/10 px-1 rounded border border-amber-500/20">
                NSE LIVE
              </span>
            </div>
            <div class="flex items-baseline space-x-2">
              <span
                :class="[
                  'text-lg font-bold font-num transition-colors duration-500',
                  spotFlashClass,
                  spotChange >= 0 ? 'text-emerald-400' : 'text-rose-400'
                ]"
              >
                {{ formatCurrency(spotPrice) }}
              </span>
              <span
                :class="[
                  'text-xs font-semibold font-num',
                  spotChange >= 0 ? 'text-emerald-400' : 'text-rose-400'
                ]"
              >
                {{ spotChange >= 0 ? '+' : '' }}{{ spotChange.toFixed(2) }} ({{ spotChangePercent.toFixed(2) }}%)
              </span>
            </div>
            <span class="text-[10px] text-slate-400 font-mono block -mt-0.5">
              As on <strong class="text-amber-300 font-medium">{{ asOnTimestamp }}</strong>
            </span>
          </div>

          <div class="h-9 w-[1px] bg-slate-800"></div>

          <!-- ATM Strike Display -->
          <div>
            <span class="text-[10px] text-slate-400 uppercase tracking-wider block font-semibold">ATM STRIKE</span>
            <span class="text-base font-bold font-num text-amber-400">
              {{ currentAtm }}
            </span>
            <span class="text-[10px] text-slate-400 block font-mono">
              Step: ±{{ props.activeUnderlying?.strike_step || 50 }}
            </span>
          </div>
        </div>

        <!-- Right: Real-time Controls & Connection Status -->
        <div class="flex items-center space-x-3">
          <!-- Provider / Broker Setup Button -->
          <button
            @click="isBrokerModalOpen = true"
            class="flex items-center space-x-1.5 px-2.5 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700/80 text-[11px] text-slate-300 transition group cursor-pointer shadow-sm hover:border-purple-500/60"
            title="Configure Angel One, Zerodha Kite, or Upstox API credentials"
          >
            <span class="w-2 h-2 rounded-full bg-emerald-400 group-hover:scale-125 transition"></span>
            <span class="text-slate-400">Broker:</span>
            <span class="font-semibold text-emerald-400">{{ activeProviderName }}</span>
            <span class="text-[10px] text-purple-300 font-bold ml-1 bg-purple-950/80 px-1.5 py-0.5 rounded border border-purple-800/60">⚙ Setup</span>
          </button>

          <!-- Market Session Indicator -->
          <div
            :class="[
              'flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold border',
              isMarketOpen
                ? 'bg-emerald-950/50 text-emerald-300 border-emerald-800/60'
                : 'bg-rose-950/40 text-rose-300 border-rose-800/60'
            ]"
            :title="marketSession?.message"
          >
            <span :class="['w-2 h-2 rounded-full', isMarketOpen ? 'bg-emerald-400 animate-ping' : 'bg-rose-500']"></span>
            <span>{{ isMarketOpen ? 'MARKET OPEN (09:15-15:30)' : 'MARKET CLOSED' }}</span>
          </div>

          <!-- Replay Mode Button Toggle -->
          <button
            @click="toggleReplayMode"
            :class="[
              'px-3 py-1.5 rounded-lg text-xs font-medium transition border flex items-center gap-1.5',
              isReplayMode
                ? 'bg-amber-500 text-black border-amber-400 font-bold shadow-md shadow-amber-500/20'
                : 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
            ]"
          >
            <span>⏱️</span>
            <span>{{ isReplayMode ? 'Live Mode' : 'Time-Travel Replay' }}</span>
          </button>

          <!-- Pause/Play Stream Toggle (only in live mode) -->
          <button
            v-if="!isReplayMode"
            @click="toggleStreaming"
            :class="[
              'p-2 rounded-lg text-xs font-medium transition border',
              isStreaming ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700' : 'bg-emerald-600 hover:bg-emerald-500 text-black border-emerald-500 font-bold'
            ]"
            :title="isStreaming ? 'Pause Real-time Polling' : 'Resume Real-time Polling'"
          >
            {{ isStreaming ? '⏸ Pause' : '▶ Resume' }}
          </button>

          <!-- Instant Refresh Button -->
          <button
            @click="fetchLiveData"
            :disabled="loading"
            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 active:scale-95 text-slate-200 text-xs rounded-lg border border-slate-700 font-medium transition flex items-center gap-1.5"
            title="Force immediate update"
          >
            <span :class="{'animate-spin': loading}">↻</span>
            <span>Tick</span>
          </button>
        </div>

      </div>
    </header>

    <!-- Market Closed / Session Notice Banner -->
    <div
      v-if="!isMarketOpen && !isReplayMode"
      class="bg-gradient-to-r from-slate-900 via-rose-950/20 to-slate-900 border-b border-rose-900/30 px-4 py-2 text-xs"
    >
      <div class="max-w-[1700px] mx-auto flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center space-x-2">
          <span class="text-rose-400 font-bold">🛑 Market Session Inactive:</span>
          <span class="text-slate-300">
            NSE Live trading stopped at 03:30 PM IST. Displaying final <strong>End-Of-Day (EOD) Closing Snapshot</strong>.
          </span>
          <span class="text-slate-400">Next session opens in <strong class="text-amber-300 font-num">{{ marketSession?.time_to_open }}</strong> ({{ marketSession?.next_open }}).</span>
        </div>
        <div class="flex items-center space-x-3">
          <button
            @click="toggleReplayMode"
            class="px-2.5 py-1 bg-amber-500/20 hover:bg-amber-500/30 border border-amber-500/40 text-amber-300 rounded font-semibold transition"
          >
            ▶ Launch Intraday Time-Travel Replay
          </button>
        </div>
      </div>
    </div>

    <!-- Replay Controller Bar (When Replay Mode is Active) -->
    <div
      v-if="isReplayMode"
      class="bg-gradient-to-r from-amber-950/30 via-slate-900 to-amber-950/30 border-b border-amber-600/40 px-4 py-3"
    >
      <div class="max-w-[1700px] mx-auto flex flex-wrap items-center justify-between gap-4">
        
        <div class="flex items-center space-x-3">
          <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-ping"></span>
            Historical Day Replay Mode
          </span>
          <span class="text-xs text-slate-300 font-num bg-slate-900 border border-slate-800 px-2.5 py-1 rounded">
            Replaying At: <strong class="text-white">{{ activeReplayTime || '09:15:00 AM' }}</strong>
          </span>
        </div>

        <!-- Slider & Timeline Scrubber -->
        <div class="flex-1 max-w-2xl flex items-center space-x-4">
          <span class="text-[11px] text-slate-400 font-num">09:15 AM</span>
          <input
            type="range"
            min="0"
            :max="Math.max(0, timelinePoints.length - 1)"
            v-model.number="currentTimelineIndex"
            @input="onTimelineScrub"
            class="flex-1 h-2 bg-slate-800 rounded-lg appearance-none cursor-pointer accent-amber-500"
          />
          <span class="text-[11px] text-slate-400 font-num">03:30 PM</span>
        </div>

        <!-- Playback Controls -->
        <div class="flex items-center space-x-2">
          <button
            @click="togglePlayReplay"
            class="px-3 py-1 bg-amber-500 hover:bg-amber-400 text-black font-bold text-xs rounded transition flex items-center gap-1"
          >
            <span>{{ isReplaying ? '⏸ Pause' : '▶ Play Replay' }}</span>
          </button>
          
          <select
            v-model.number="replaySpeed"
            class="bg-slate-900 border border-slate-700 text-slate-200 text-xs rounded px-2 py-1 focus:outline-none"
          >
            <option :value="1000">Speed: 1x</option>
            <option :value="500">Speed: 2x</option>
            <option :value="200">Speed: 5x</option>
          </select>

          <button
            @click="toggleReplayMode"
            class="text-xs text-slate-400 hover:text-white px-2 py-1 rounded hover:bg-slate-800 transition"
          >
            Exit Replay
          </button>
        </div>

      </div>
    </div>

    <!-- Sub-Header: Market Intelligence Dashboard Grid -->
    <div class="bg-[#0B101D] border-b border-slate-800/70 px-4 py-3">
      <div class="max-w-[1700px] mx-auto grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        
        <!-- CARD 1: PCR Meter -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex items-center justify-between">
          <div>
            <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold block">PCR (PUT/CALL)</span>
            <span class="text-xl font-bold font-num text-slate-100">
              {{ totals?.pcr?.toFixed(2) || '1.00' }}
            </span>
            <span :class="['text-[10px] block font-medium mt-0.5', pcrSentimentColor]">
              {{ totals?.market_sentiment || 'Neutral' }}
            </span>
          </div>
          <PcrGauge :pcr="totals?.pcr || 1.0" class="scale-75 -my-2" />
        </div>

        <!-- CARD 2: Max Pain -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex flex-col justify-between">
          <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">MAX PAIN STRIKE</span>
          <div class="flex items-baseline space-x-2 my-1">
            <span class="text-xl font-bold font-num text-amber-400">
              {{ levels?.max_pain || '--' }}
            </span>
            <span class="text-xs text-slate-400 font-num">
              Dist: {{ (levels?.max_pain ? spotPrice - levels?.max_pain : 0).toFixed(1) }}
            </span>
          </div>
          <span class="text-[10px] text-slate-400">Options Writer Profit Zone</span>
        </div>

        <!-- CARD 3: Primary Resistance -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">RESISTANCE (CALL OI)</span>
            <span v-if="levels?.resistance_shift" class="text-[9px] px-1.5 py-0.2 rounded bg-rose-500/20 text-rose-300 border border-rose-500/30">
              {{ levels?.resistance_shift }}
            </span>
          </div>
          <div class="flex items-baseline space-x-2 my-1">
            <span class="text-xl font-bold font-num text-rose-400">
              {{ levels?.resistance_1 || '--' }}
            </span>
            <span class="text-xs text-slate-400 font-num">
              R2: {{ levels?.resistance_2 || '--' }}
            </span>
          </div>
          <span class="text-[10px] text-slate-400">Highest Open Call Positions</span>
        </div>

        <!-- CARD 4: Primary Support -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex flex-col justify-between">
          <div class="flex items-center justify-between">
            <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">SUPPORT (PUT OI)</span>
            <span v-if="levels?.support_shift" class="text-[9px] px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
              {{ levels?.support_shift }}
            </span>
          </div>
          <div class="flex items-baseline space-x-2 my-1">
            <span class="text-xl font-bold font-num text-emerald-400">
              {{ levels?.support_1 || '--' }}
            </span>
            <span class="text-xs text-slate-400 font-num">
              S2: {{ levels?.support_2 || '--' }}
            </span>
          </div>
          <span class="text-[10px] text-slate-400">Highest Open Put Positions</span>
        </div>

        <!-- CARD 5: Net Open Interest Difference -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex flex-col justify-between">
          <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">NET OI (PUT - CALL)</span>
          <div class="flex items-baseline space-x-1 my-1">
            <span :class="['text-xl font-bold font-num', (totals?.difference || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400']">
              {{ (totals?.difference || 0) >= 0 ? '+' : '' }}{{ formatShortNum(totals?.difference) }}
            </span>
          </div>
          <div class="flex justify-between text-[10px] text-slate-400 font-num">
            <span>CE: {{ formatShortNum(totals?.total_call_oi) }}</span>
            <span>PE: {{ formatShortNum(totals?.total_put_oi) }}</span>
          </div>
        </div>

        <!-- CARD 6: Visible Strikes Ladder Count -->
        <div class="bg-[#111827]/80 border border-slate-800 rounded-xl p-3 flex flex-col justify-between">
          <span class="text-[10px] text-slate-400 uppercase tracking-wider font-semibold">ACTIVE LADDER</span>
          <div class="text-xl font-bold font-num text-slate-100 my-1">
            ATM ± {{ Math.floor(visibleStrikes.length / 2) }} <span class="text-xs font-normal text-slate-400">({{ visibleStrikes.length }} Strikes)</span>
          </div>
          <div class="flex items-center justify-between text-[10px] text-slate-400 font-mono">
            <span>{{ visibleStrikes[0]?.strike_price }}</span>
            <span>→</span>
            <span>{{ visibleStrikes[visibleStrikes.length - 1]?.strike_price }}</span>
          </div>
        </div>

      </div>
    </div>

    <!-- Main Content Area: Option Chain Table + Live Alerts Drawer -->
    <main class="flex-1 max-w-[1700px] w-full mx-auto px-4 py-4 space-y-4">
      
      <!-- Option Chain Terminal Table Container -->
      <div class="bg-[#0E1526] border border-slate-800/90 rounded-2xl shadow-2xl overflow-hidden">
        
        <!-- Table Top Control Bar -->
        <div class="px-5 py-3 border-b border-slate-800 flex flex-wrap items-center justify-between gap-3 bg-[#0A101D]">
          <div class="flex items-center space-x-3">
            <span class="text-xs font-bold text-slate-200 uppercase tracking-wider flex items-center gap-2">
              <span :class="['w-2 h-2 rounded-full', isReplayMode ? 'bg-amber-400' : 'bg-emerald-500 animate-pulse']"></span>
              {{ isReplayMode ? 'Historical Option Chain Matrix (Replay)' : 'Real-Time Option Chain Matrix' }}
            </span>
            <span class="text-xs text-slate-400 font-num bg-slate-900/90 border border-slate-800 px-3 py-1 rounded-lg flex items-center gap-2">
              <span>📅 Date: <strong class="text-slate-200">{{ snapshotDate }}</strong></span>
              <span class="text-slate-600">|</span>
              <span>⏰ Time: <strong class="text-amber-400 font-bold">{{ snapshotTime }}</strong></span>
              <span class="text-slate-600">|</span>
              <span>🎯 Expiry: <strong class="text-emerald-400">{{ formatExpiry(currentExpiry) }}</strong></span>
            </span>
          </div>

          <div class="flex items-center space-x-3 text-xs">
            <span class="text-slate-400">Click any row for Deep-Dive OI Analytics & ApexCharts</span>
          </div>
        </div>

        <!-- The Option Chain Table -->
        <div class="overflow-x-auto">
          <table class="w-full border-collapse text-xs text-left">
            <thead>
              <!-- Top Category Header -->
              <tr class="border-b border-slate-800 bg-[#080D1A] text-[11px] font-bold uppercase tracking-wider text-center">
                <th colspan="7" class="py-2.5 px-3 text-emerald-400 bg-emerald-950/20 border-r border-slate-800">
                  CALLS (CE)
                </th>
                <th rowspan="2" class="py-2.5 px-4 text-amber-300 bg-amber-950/20 border-r border-slate-800 w-28">
                  STRIKE
                </th>
                <th colspan="7" class="py-2.5 px-3 text-rose-400 bg-rose-950/20">
                  PUTS (PE)
                </th>
              </tr>

              <!-- Column Names Header (Exact User Specified Columns) -->
              <tr class="border-b border-slate-800 bg-[#0B101F] text-[10px] text-slate-400 uppercase font-semibold text-center tracking-tight">
                <!-- CALL SIDE -->
                <th class="py-2 px-2 text-left">Trend</th>
                <th class="py-2 px-2 text-right">OI</th>
                <th class="py-2 px-2 text-right">Chg in OI</th>
                <th class="py-2 px-2 text-right">Volume</th>
                <th class="py-2 px-2 text-right">IV</th>
                <th class="py-2 px-2 text-right">LTP</th>
                <th class="py-2 px-2 text-right border-r border-slate-800">Change</th>

                <!-- PUT SIDE -->
                <th class="py-2 px-2 text-left">LTP</th>
                <th class="py-2 px-2 text-left">Change</th>
                <th class="py-2 px-2 text-left">IV</th>
                <th class="py-2 px-2 text-left">Volume</th>
                <th class="py-2 px-2 text-left">Chg in OI</th>
                <th class="py-2 px-2 text-left">OI</th>
                <th class="py-2 px-2 text-right">Trend</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-800/60 font-num">
              <tr
                v-for="row in visibleStrikes"
                :key="row.strike_price"
                @click="openStrikeModal(row)"
                :class="[
                  'cursor-pointer transition-colors duration-150',
                  row.is_atm
                    ? 'bg-amber-500/10 hover:bg-amber-500/15 border-y-2 border-amber-500/50'
                    : 'hover:bg-slate-800/50'
                ]"
              >
                <!-- CALL: Trend & Sparkline -->
                <td class="py-2 px-2 text-left">
                  <div class="flex items-center space-x-1.5">
                    <TrendBadge :trend="row.call.current_trend" />
                    <MiniSparkline :data="row.call.sparkline" color="#10B981" />
                  </div>
                </td>

                <!-- CALL: OI -->
                <td class="py-2 px-2 text-right font-medium text-slate-200">
                  {{ formatNum(row.call.oi) }}
                </td>

                <!-- CALL: Change in OI -->
                <td :class="['py-2 px-2 text-right font-semibold', row.call.change_oi >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ row.call.change_oi >= 0 ? '+' : '' }}{{ formatNum(row.call.change_oi) }}
                </td>

                <!-- CALL: Volume -->
                <td class="py-2 px-2 text-right text-slate-400">
                  {{ formatShortNum(row.call.volume) }}
                </td>

                <!-- CALL: IV -->
                <td class="py-2 px-2 text-right text-slate-300">
                  {{ row.call.iv.toFixed(1) }}
                </td>

                <!-- CALL: LTP -->
                <td class="py-2 px-2 text-right font-bold text-slate-100">
                  ₹{{ row.call.ltp.toFixed(2) }}
                </td>

                <!-- CALL: Change -->
                <td :class="['py-2 px-2 text-right border-r border-slate-800 font-medium', row.call.change >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ row.call.change >= 0 ? '+' : '' }}{{ row.call.change.toFixed(2) }}
                </td>

                <!-- STRIKE (CENTER) -->
                <td :class="[
                  'py-2 px-3 text-center border-r border-slate-800 font-bold text-sm tracking-tight',
                  row.is_atm ? 'bg-amber-500/20 text-amber-300 shadow-inner' : 'bg-slate-900/60 text-slate-100'
                ]">
                  <div class="flex flex-col items-center">
                    <span>{{ row.strike_price }}</span>
                    <span class="text-[9px] font-normal text-slate-400">
                      {{ row.atm_relation }}
                    </span>
                  </div>
                </td>

                <!-- PUT: LTP -->
                <td class="py-2 px-2 text-left font-bold text-slate-100">
                  ₹{{ row.put.ltp.toFixed(2) }}
                </td>

                <!-- PUT: Change -->
                <td :class="['py-2 px-2 text-left font-medium', row.put.change >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ row.put.change >= 0 ? '+' : '' }}{{ row.put.change.toFixed(2) }}
                </td>

                <!-- PUT: IV -->
                <td class="py-2 px-2 text-left text-slate-300">
                  {{ row.put.iv.toFixed(1) }}
                </td>

                <!-- PUT: Volume -->
                <td class="py-2 px-2 text-left text-slate-400">
                  {{ formatShortNum(row.put.volume) }}
                </td>

                <!-- PUT: Change in OI -->
                <td :class="['py-2 px-2 text-left font-semibold', row.put.change_oi >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ row.put.change_oi >= 0 ? '+' : '' }}{{ formatNum(row.put.change_oi) }}
                </td>

                <!-- PUT: OI -->
                <td class="py-2 px-2 text-left font-medium text-slate-200">
                  {{ formatNum(row.put.oi) }}
                </td>

                <!-- PUT: Trend & Sparkline -->
                <td class="py-2 px-2 text-right">
                  <div class="flex items-center justify-end space-x-1.5">
                    <MiniSparkline :data="row.put.sparkline" color="#F43F5E" />
                    <TrendBadge :trend="row.put.current_trend" />
                  </div>
                </td>

              </tr>
            </tbody>

            <!-- TOTALS ROW (AT THE BOTTOM AS SPECIFIED) -->
            <tfoot>
              <tr class="bg-[#090E1B] border-t-2 border-slate-700 text-xs font-bold text-center">
                <!-- CALL TOTAL -->
                <td class="py-3 px-2 text-left text-emerald-400 font-semibold uppercase">
                  CALL TOTAL
                </td>
                <td class="py-3 px-2 text-right text-emerald-400 font-num">
                  {{ formatNum(totals?.call_total?.oi) }}
                </td>
                <td :class="['py-3 px-2 text-right font-num', (totals?.call_total?.change_oi || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ (totals?.call_total?.change_oi || 0) >= 0 ? '+' : '' }}{{ formatNum(totals?.call_total?.change_oi) }}
                </td>
                <td class="py-3 px-2 text-right text-slate-300 font-num">
                  {{ formatShortNum(totals?.call_total?.volume) }}
                </td>
                <td class="py-3 px-2 text-right text-slate-300 font-num">
                  {{ totals?.call_total?.avg_iv }}
                </td>
                <td class="py-3 px-2 text-right text-slate-100 font-num">
                  ₹{{ totals?.call_total?.avg_ltp }}
                </td>
                <td class="py-3 px-2 border-r border-slate-800 text-slate-500 font-num">
                  --
                </td>

                <!-- STRIKE TOTAL DIVIDER -->
                <td class="py-3 px-2 bg-slate-900 border-r border-slate-800 text-slate-400 text-[10px] uppercase font-semibold">
                  Visible Totals
                </td>

                <!-- PUT TOTAL -->
                <td class="py-3 px-2 text-left text-slate-100 font-num">
                  ₹{{ totals?.put_total?.avg_ltp }}
                </td>
                <td class="py-3 px-2 text-left text-slate-500 font-num">
                  --
                </td>
                <td class="py-3 px-2 text-left text-slate-300 font-num">
                  {{ totals?.put_total?.avg_iv }}
                </td>
                <td class="py-3 px-2 text-left text-slate-300 font-num">
                  {{ formatShortNum(totals?.put_total?.volume) }}
                </td>
                <td :class="['py-3 px-2 text-left font-num', (totals?.put_total?.change_oi || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                  {{ (totals?.put_total?.change_oi || 0) >= 0 ? '+' : '' }}{{ formatNum(totals?.put_total?.change_oi) }}
                </td>
                <td class="py-3 px-2 text-left text-rose-400 font-num">
                  {{ formatNum(totals?.put_total?.oi) }}
                </td>
                <td class="py-3 px-2 text-right text-rose-400 font-semibold uppercase">
                  PUT TOTAL
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Totals Summary Banner at Table Bottom -->
        <div class="px-5 py-3 bg-[#0B1020] border-t border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs">
          <div class="flex items-center space-x-6">
            <div>
              <span class="text-slate-400 mr-1.5">PCR Ratio:</span>
              <span :class="['font-bold font-num', pcrSentimentColor]">{{ totals?.pcr?.toFixed(2) }}</span>
            </div>
            <div>
              <span class="text-slate-400 mr-1.5">Total Call OI:</span>
              <span class="font-bold font-num text-emerald-400">{{ formatNum(totals?.total_call_oi) }}</span>
            </div>
            <div>
              <span class="text-slate-400 mr-1.5">Total Put OI:</span>
              <span class="font-bold font-num text-rose-400">{{ formatNum(totals?.total_put_oi) }}</span>
            </div>
            <div>
              <span class="text-slate-400 mr-1.5">Net Difference:</span>
              <span :class="['font-bold font-num', (totals?.difference || 0) >= 0 ? 'text-emerald-400' : 'text-rose-400']">
                {{ (totals?.difference || 0) >= 0 ? '+' : '' }}{{ formatNum(totals?.difference) }}
              </span>
            </div>
          </div>

          <div class="text-[11px] text-slate-400">
            Auto-calculates totals exclusively for the 11 visible ATM strikes ladder.
          </div>
        </div>

      </div>

      <!-- Bottom Grid: Alerts Engine Feed & Trend Analytics Matrix -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <!-- Live Alerts Panel (1 Column) -->
        <div class="lg:col-span-1">
          <AlertsPanel :alerts="liveAlerts" @clear="liveAlerts = []" />
        </div>

        <!-- Strike Trend & Reversal Intelligence Monitor (2 Columns) -->
        <div class="lg:col-span-2 bg-[#111827] border border-slate-800 rounded-xl p-4 shadow-lg flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
              <h3 class="text-xs font-bold uppercase tracking-wider text-slate-200">
                OI Trend & Reversal Intelligence Monitor
              </h3>
              <span class="text-xs text-slate-400 font-num">
                Peak Drop Detection Threshold: > 5% drop from peak
              </span>
            </div>

            <!-- Strike Quick Scan Matrix -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 max-h-64 overflow-y-auto pr-1">
              <div
                v-for="row in visibleStrikes"
                :key="'card-' + row.strike_price"
                @click="openStrikeModal(row)"
                class="p-2.5 rounded-lg bg-slate-900/80 border border-slate-800 hover:border-slate-700 cursor-pointer transition text-xs"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <span class="font-bold font-num text-amber-400">{{ row.strike_price }}</span>
                  <span class="text-[10px] text-slate-400">{{ row.atm_relation }}</span>
                </div>

                <!-- Call Status -->
                <div class="flex items-center justify-between text-[11px] mb-1">
                  <span class="text-emerald-400 font-medium">CE:</span>
                  <TrendBadge :trend="row.call.current_trend" />
                </div>

                <!-- Put Status -->
                <div class="flex items-center justify-between text-[11px]">
                  <span class="text-rose-400 font-medium">PE:</span>
                  <TrendBadge :trend="row.put.current_trend" />
                </div>
              </div>
            </div>
          </div>

          <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
            <span>🔴 OI Unwinding: OI falling from peak</span>
            <span>🟢 Fresh OI Build-up: OI rebounding from bottom</span>
            <span>Support/Resistance: Aggressive Put/Call writing</span>
          </div>
        </div>

      </div>

    </main>

    <!-- Deep-Dive Strike Detail Modal -->
    <StrikeDetailModal
      :isOpen="isModalOpen"
      :strike="selectedStrike"
      :symbol="activeSymbol"
      :spotPrice="spotPrice"
      @close="isModalOpen = false"
    />

    <!-- Broker Live Feed Setup Modal -->
    <BrokerSetupModal
      :isOpen="isBrokerModalOpen"
      @close="isBrokerModalOpen = false"
      @provider-changed="handleProviderChanged"
    />

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import MiniSparkline from '../Components/MiniSparkline.vue';
import TrendBadge from '../Components/TrendBadge.vue';
import PcrGauge from '../Components/PcrGauge.vue';
import AlertsPanel from '../Components/AlertsPanel.vue';
import StrikeDetailModal from '../Components/StrikeDetailModal.vue';
import BrokerSetupModal from '../Components/BrokerSetupModal.vue';

const props = defineProps({
  underlyings: Array,
  activeUnderlying: Object,
  selectedExpiry: String,
  initialAnalysis: Object,
  initialAlerts: Array,
  providerName: String,
  marketSession: Object,
});

// State
const activeSymbol = ref(props.activeUnderlying?.symbol || 'NIFTY');
const currentExpiry = ref(props.selectedExpiry || props.activeUnderlying?.selected_expiry);
const liveData = ref(props.initialAnalysis || {});
const liveAlerts = ref(props.initialAlerts || []);
const loading = ref(false);
const isStreaming = ref(true);
const isBrokerModalOpen = ref(false);
const activeProviderName = ref(props.providerName || 'SimulatedLiveStream');
let streamTimer = null;

const handleProviderChanged = (provider) => {
  activeProviderName.value = provider;
  fetchLiveData();
};


// Market Session
const marketSession = ref(props.marketSession || props.initialAnalysis?.market_session || {});
const isMarketOpen = computed(() => marketSession.value?.is_open ?? false);

// Replay State
const isReplayMode = ref(false);
const isReplaying = ref(false);
const replaySpeed = ref(500); // ms per step
const timelinePoints = ref([]);
const currentTimelineIndex = ref(0);
const activeReplayTime = ref('');
let replayTimer = null;

// Modal
const isModalOpen = ref(false);
const selectedStrike = ref(null);

// Spot Ticker Animation Tracking
const prevSpot = ref(props.initialAnalysis?.spot_price || 0);
const spotFlashClass = ref('');

// Computed Properties
const spotPrice = computed(() => liveData.value?.spot_price || 0);

const spotChange = computed(() => {
  if (liveData.value?.spot_change !== undefined && liveData.value?.spot_change !== null) {
    return Number(liveData.value.spot_change);
  }
  return spotPrice.value - (props.activeUnderlying?.spot_price || spotPrice.value);
});

const spotChangePercent = computed(() => {
  if (liveData.value?.spot_change_percent !== undefined && liveData.value?.spot_change_percent !== null) {
    return Number(liveData.value.spot_change_percent);
  }
  const base = props.activeUnderlying?.spot_price || spotPrice.value || 1;
  return (spotChange.value / base) * 100;
});

const currentAtm = computed(() => liveData.value?.atm_strike || 0);
const visibleStrikes = computed(() => liveData.value?.strikes || []);
const totals = computed(() => liveData.value?.totals || {});
const levels = computed(() => liveData.value?.levels || {});

const asOnTimestamp = computed(() => {
  if (isReplayMode.value) {
    return `${activeReplayDate.value || '08-Oct-2026'} ${activeReplayTime.value || '15:30:00'} IST`;
  }
  if (liveData.value?.current_time_ist) {
    return liveData.value.current_time_ist;
  }
  if (marketSession.value?.current_time_ist) {
    return marketSession.value.current_time_ist;
  }
  return '08-Oct-2026 15:30:00 IST';
});

const snapshotDate = computed(() => {
  if (isReplayMode.value) return activeReplayDate.value || '08-Oct-2026';
  if (liveData.value?.date_ist) return liveData.value.date_ist;
  if (marketSession.value?.date_ist) return marketSession.value.date_ist;
  if (liveData.value?.current_time_ist) {
    const parts = liveData.value.current_time_ist.split(' ');
    if (parts.length >= 2) return parts[0];
  }
  return '08-Oct-2026';
});

const snapshotTime = computed(() => {
  if (isReplayMode.value) return (activeReplayTime.value || '15:30:00') + ' IST';
  if (liveData.value?.time_ist) return liveData.value.time_ist;
  if (marketSession.value?.time_ist) return marketSession.value.time_ist;
  if (liveData.value?.current_time_ist) {
    const parts = liveData.value.current_time_ist.split(' ');
    if (parts.length >= 2) return parts[1] + (parts[2] ? ' ' + parts[2] : ' IST');
  }
  return '15:30:00 IST';
});

const availableExpiries = computed(() => {
  if (liveData.value?.available_expiries && liveData.value.available_expiries.length > 0) {
    return liveData.value.available_expiries;
  }
  const und = props.underlyings?.find(u => u.symbol === activeSymbol.value);
  return und?.available_expiries || [currentExpiry.value];
});

function formatExpiry(dateStr) {
  if (!dateStr) return '';
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    const [year, month, day] = dateStr.split('-');
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const m = monthNames[parseInt(month, 10) - 1] || month;
    return `${day}-${m}-${year}`;
  }
  return dateStr;
}

const pcrSentimentColor = computed(() => {
  const pcr = totals.value?.pcr || 1.0;
  if (pcr >= 1.2) return 'text-emerald-400';
  if (pcr <= 0.8) return 'text-rose-400';
  return 'text-amber-400';
});

// Formatters
function formatNum(val) {
  if (val === undefined || val === null) return '--';
  return Number(val).toLocaleString('en-IN');
}

function formatShortNum(val) {
  if (!val && val !== 0) return '--';
  const num = Math.abs(Number(val));
  if (num >= 10000000) return (val / 10000000).toFixed(2) + ' Cr';
  if (num >= 100000) return (val / 100000).toFixed(2) + ' L';
  if (num >= 1000) return (val / 1000).toFixed(1) + ' k';
  return Number(val).toLocaleString('en-IN');
}

function formatCurrency(val) {
  if (!val) return '₹0.00';
  return '₹' + Number(val).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Live Data Fetching
async function fetchLiveData() {
  if (isReplayMode.value) return;

  try {
    loading.value = true;
    const res = await fetch(`/api/option-chain/live/${activeSymbol.value}?expiry=${currentExpiry.value}`);
    if (res.ok) {
      const json = await res.json();
      if (json.data) {
        // Ticker animation detection
        const oldPrice = spotPrice.value;
        const newPrice = json.data.spot_price;
        if (newPrice > oldPrice) {
          spotFlashClass.value = 'flash-green';
        } else if (newPrice < oldPrice) {
          spotFlashClass.value = 'flash-red';
        }
        setTimeout(() => { spotFlashClass.value = ''; }, 1000);

        liveData.value = json.data;
        if (json.market_session) {
          marketSession.value = json.market_session;
        }

        // Fetch recent alerts
        fetchAlerts();
      }
    }
  } catch (err) {
    console.error('Failed to fetch live option chain tick:', err);
  } finally {
    loading.value = false;
  }
}

async function fetchAlerts() {
  try {
    const res = await fetch(`/api/option-chain/alerts/${activeSymbol.value}`);
    if (res.ok) {
      const json = await res.json();
      if (json.alerts) {
        liveAlerts.value = json.alerts;
      }
    }
  } catch (err) {
    console.error('Failed to fetch alerts:', err);
  }
}

function switchSymbol(symbol) {
  activeSymbol.value = symbol;
  const und = props.underlyings?.find(u => u.symbol === symbol);
  if (und && und.available_expiries && und.available_expiries.length > 0) {
    currentExpiry.value = und.available_expiries[0];
  }
  if (isReplayMode.value) {
    loadTimelineAndReplay();
  } else {
    fetchLiveData();
  }
}

function toggleStreaming() {
  isStreaming.value = !isStreaming.value;
  if (isStreaming.value) {
    startStreaming();
  } else {
    stopStreaming();
  }
}

function startStreaming() {
  stopStreaming();
  streamTimer = setInterval(() => {
    fetchLiveData();
  }, 5000);
}

function stopStreaming() {
  if (streamTimer) {
    clearInterval(streamTimer);
    streamTimer = null;
  }
}

// Replay Functions
async function toggleReplayMode() {
  isReplayMode.value = !isReplayMode.value;
  if (isReplayMode.value) {
    stopStreaming();
    await loadTimelineAndReplay();
  } else {
    stopReplayPlayback();
    startStreaming();
    fetchLiveData();
  }
}

async function loadTimelineAndReplay() {
  try {
    const res = await fetch(`/api/option-chain/replay-timeline/${activeSymbol.value}`);
    if (res.ok) {
      const json = await res.json();
      timelinePoints.value = json.timeline || [];
      if (timelinePoints.value.length > 0) {
        currentTimelineIndex.value = 0;
        await fetchReplaySnapshot(timelinePoints.value[0].time);
      }
    }
  } catch (err) {
    console.error('Failed to load replay timeline:', err);
  }
}

async function fetchReplaySnapshot(timestamp) {
  try {
    loading.value = true;
    const res = await fetch(`/api/option-chain/replay/${activeSymbol.value}?timestamp=${encodeURIComponent(timestamp)}`);
    if (res.ok) {
      const json = await res.json();
      if (json.data) {
        liveData.value = json.data;
        activeReplayTime.value = new Date(timestamp).toLocaleTimeString('en-IN', { hour12: false });
      }
    }
  } catch (err) {
    console.error('Failed to fetch replay snapshot:', err);
  } finally {
    loading.value = false;
  }
}

function onTimelineScrub() {
  const pt = timelinePoints.value[currentTimelineIndex.value];
  if (pt) {
    fetchReplaySnapshot(pt.time);
  }
}

function togglePlayReplay() {
  isReplaying.value = !isReplaying.value;
  if (isReplaying.value) {
    startReplayPlayback();
  } else {
    stopReplayPlayback();
  }
}

function startReplayPlayback() {
  stopReplayPlayback();
  replayTimer = setInterval(async () => {
    if (currentTimelineIndex.value < timelinePoints.value.length - 1) {
      currentTimelineIndex.value++;
      const pt = timelinePoints.value[currentTimelineIndex.value];
      if (pt) {
        await fetchReplaySnapshot(pt.time);
      }
    } else {
      stopReplayPlayback();
      isReplaying.value = false;
    }
  }, replaySpeed.value);
}

function stopReplayPlayback() {
  if (replayTimer) {
    clearInterval(replayTimer);
    replayTimer = null;
  }
}

function openStrikeModal(row) {
  selectedStrike.value = row;
  isModalOpen.value = true;
}

onMounted(() => {
  const urlParams = new URLSearchParams(window.location.search);
  const brokerConnected = urlParams.get('broker_connected');
  if (brokerConnected) {
    activeProviderName.value = brokerConnected;
    liveAlerts.value.unshift({
      id: Date.now(),
      symbol: activeSymbol.value,
      alert_type: 'BROKER_CONNECTED',
      severity: 'HIGH',
      message: `🎉 Successfully connected to ${brokerConnected.toUpperCase()}! Real-time broker live stream is active.`,
      detected_at: new Date().toLocaleTimeString(),
    });
  }
  fetchLiveData();
  startStreaming();
});

onUnmounted(() => {
  stopStreaming();
  stopReplayPlayback();
});
</script>

# OptionChain Intelligence Terminal & OI Intelligence Engine

A professional real-time **Option Chain Analyzer & OI Intelligence Engine** built with **Laravel 12/13, Vue 3, Inertia.js, Tailwind CSS, Redis, and ApexCharts**.

Unlike standard Option Chain viewers that merely display static tables, this system implements an **algorithmic Open Interest (OI) Intelligence Engine** that analyzes historical OI movements, detects trend reversals, tracks peak & bottom formations, identifies support/resistance shifts, and generates actionable trading alerts in real time.

---

## 🏛️ Architecture & Clean Domain Separation

The application strictly follows Clean Architecture and SOLID design principles, decoupling market data providers, indicator engines, database snapshots, and the presentation dashboard:

```text
Market Data Provider (Zerodha / Upstox / Angel One / FYERS / Simulation)
        │
        ▼
Market Data Service (Resolution & Redis Live Caching)
        │
        ▼
Redis (Live Memory Buffer)
        │
        ▼
OI Intelligence Engine (ATM Ladder, Peak/Bottom Reversal Detection, Max Pain, PCR)
        │
        ├────────► Alert Engine (Breakout/Breakdown, Heavy OI Build-up, Writing Shifts)
        │
        ▼
MySQL History (Immutable 5-Second Snapshots: Never Overwritten)
        │
        ▼
WebSocket Server (Laravel Reverb / Pusher Protocol)
        │
        ▼
Vue 3 Inertia Dashboard (Dark Theme, Sparklines, ApexCharts, Zero Page Reload)
```

---

## 🚀 Key Features

### 1. Spot Price & Dynamic ATM Strike Ladder
- Continuously tracks Spot Price with instantaneous tick flash animations (green on uptick, red on downtick).
- Automatically calculates the nearest At-The-Money (ATM) strike based on underlying strike step (`50` for NIFTY, `100` for BANKNIFTY, `50` for FINNIFTY).
- Dynamically generates the **11 visible strikes ladder**:
  `ATM -5`, `ATM -4`, `ATM -3`, `ATM -2`, `ATM -1`, **`ATM`**, `ATM +1`, `ATM +2`, `ATM +3`, `ATM +4`, `ATM +5`.
- As spot price moves across strike boundaries, the visible ladder automatically shifts while preserving all prior strike history in the database.

### 2. Standard Display Columns
- **CALL (CE)**: OI, Change in OI, Volume, IV, LTP, Change.
- **STRIKE**: Central strike column with ATM distance indicator (`ATM`, `ATM +1`, `ATM -2`).
- **PUT (PE)**: LTP, Change, IV, Volume, Change in OI, OI.

### 3. OI Trend & Price-Action Classification
Every strike row and option type is evaluated across 12 automated trend categories:
- **`🔴 OI Unwinding`**: Triggered when OI drops significantly from its recorded intraday peak.
- **`🟢 Fresh OI Build-up`**: Triggered when OI rebounds from its intraday bottom.
- **`Long Build-up`**: Price Rising + OI Increasing.
- **`Short Build-up`**: Price Falling + OI Increasing.
- **`Short Covering`**: Price Rising + OI Decreasing.
- **`Long Unwinding`**: Price Falling + OI Decreasing.
- **`Call Writing`**: Aggressive Call addition as price weakens (resistance solidifying).
- **`Put Writing`**: Aggressive Put addition creating a support floor.
- **`Support Shift`**: Primary Put concentration migrating to higher or lower strikes.
- **`Resistance Shift`**: Primary Call concentration migrating to higher or lower strikes.

### 4. Peak & Bottom Formation Engine
- Maintains exact intraday statistics per strike:
  - **Peak OI Reached** & exact **Peak Time**
  - **Bottom OI Reached** & exact **Bottom Time**
  - **Difference from Peak** & **Percentage Drop from Peak**
  - **Trend Chronology**: Started Increasing, Peak Time, Started Decreasing, Lowest Point, Started Recovering.

### 5. Mini Sparklines & Interactive ApexCharts
- Every strike displays a lightweight SVG mini sparkline showing the last 30 minutes to 1 hour of OI movement.
- Clicking any strike row opens a **Deep-Dive Strike Detail Modal** with full interactive multi-axis **ApexCharts** comparing Call OI vs. Put OI over time.

### 6. Visible Strikes Totals & Market Sentiment
Calculated exclusively across the 11 visible strikes:
- **CALL TOTAL**: Total OI, Net Change in OI, Total Volume, Average IV, Average LTP.
- **PUT TOTAL**: Total OI, Net Change in OI, Total Volume, Average IV, Average LTP.
- **PCR (Put/Call Ratio)**: Put OI ÷ Call OI with a visual SVG gauge and sentiment classification (Strongly Bullish, Mildly Bullish, Rangebound, Strongly Bearish).
- **Net OI Difference**: Put OI - Call OI.
- **Max Pain Strike**: Algorithmic strike with minimum cash payout liability for option writers.

### 7. Real-Time Alert Engine
Monitors and logs real-time events to the database and broadcasts over WebSockets:
- 🚀 **Possible Breakout**: Spot testing primary Call Resistance with heavy Put writing.
- ⚠️ **Possible Breakdown**: Spot breaking primary Put Support with heavy Call writing.
- 🔄 **Support / Resistance Shifted**: Significant migration in writer concentration.
- 📈 **Heavy OI Build-up**: Aggressive position addition (>15,000 contracts in snapshot).
- 📉 **Heavy OI Unwinding**: Rapid writer covering (>10% drop from peak).
- 🛡️ **Fresh Call / Put Writing**.

---

## 📁 Clean Architecture Folder Structure

```text
app/
├── Console/Commands/
│   └── StreamOptionChainCommand.php     # Ingestion worker (php artisan option-chain:stream)
├── Contracts/
│   ├── MarketDataProviderInterface.php # Abstract market data contract
│   ├── OiIntelligenceEngineInterface.php # Core intelligence contract
│   └── AlertEngineInterface.php         # Real-time alert dispatch contract
├── Events/
│   ├── OptionChainUpdated.php           # ShouldBroadcastNow live chain event
│   └── MarketAlertCreated.php           # Real-time alert broadcast event
├── Http/Controllers/
│   ├── DashboardController.php          # Inertia page renderer
│   └── Api/OptionChainApiController.php # REST & live tick APIs
├── Models/
│   ├── Underlying.php                   # NIFTY, BANKNIFTY, FINNIFTY with ATM ladder logic
│   ├── OptionSnapshot.php               # Immutable historical 5s time-series
│   ├── StrikeAnalytic.php               # Peak/bottom tracking & drop %
│   └── MarketAlert.php                  # System alert history
├── Providers/
│   └── AppServiceProvider.php           # Dependency Injection bindings
├── Repositories/
│   ├── Contracts/                       # Repository interfaces
│   └── Eloquent/                        # Eloquent implementations
└── Services/
    ├── Alerts/AlertEngine.php           # Threshold & pattern evaluation
    ├── Analytics/
    │   ├── OiIntelligenceEngine.php     # Master intelligence coordinator
    │   ├── PeakBottomDetectorService.php# Peak & bottom formation tracking
    │   ├── TrendDetectionService.php    # Price-action & OI trend classification
    │   └── HistoricalSparklineService.php # Sparkline time-series builder
    └── MarketData/
        ├── MarketDataService.php        # Provider resolution & Redis caching
        └── Providers/
            ├── SimulatedLiveMarketDataProvider.php # High-fidelity test simulator
            ├── KiteConnectProvider.php   # Zerodha Kite Connect adapter
            └── UpstoxMarketDataProvider.php # Upstox API v2 adapter

resources/js/
├── Pages/
│   └── OptionChainTerminal.vue          # Main dark terminal dashboard
└── Components/
    ├── MiniSparkline.vue                # Lightweight SVG sparkline
    ├── TrendBadge.vue                   # Glowing trend indicators
    ├── PcrGauge.vue                     # Put-Call Ratio meter
    ├── AlertsPanel.vue                  # Streaming live alerts feed
    └── StrikeDetailModal.vue            # ApexCharts strike history modal
```

---

## ⚙️ Configuration & Data Providers

### Abstract Data Provider
You can switch providers in `.env` without modifying any business logic:

```env
# Supported drivers: simulation | zerodha | upstox | angelone | fyers
MARKET_DATA_PROVIDER=simulation
MARKET_DATA_STREAM_INTERVAL=5

# Zerodha Kite Connect
KITE_API_KEY=your_key
KITE_ACCESS_TOKEN=your_token

# Upstox API v2
UPSTOX_API_KEY=your_key
UPSTOX_ACCESS_TOKEN=your_token
```

> **Simulation Provider Included**: A high-fidelity mathematical simulation engine is provided by default. It simulates spot price drift, Black-Scholes IV, realistic Open Interest build-ups and unwinding, and orderbook volume for NIFTY, BANKNIFTY, and FINNIFTY. This allows full testing and demonstration at any time, even outside of market hours.

---

## 🛠️ Step-by-Step Installation & Execution

### 1. Database & Migrations
```bash
# Run database migrations
php artisan migrate

# Seed initial Underlyings and initial option snapshots
php artisan db:seed
```

### 2. Build Frontend Assets
```bash
npm run build
# Or start Vite dev server:
npm run dev
```

### 3. Start Laravel Web Server
```bash
php artisan serve --port=8000
```
Visit **`http://localhost:8000`** in your browser.

### 4. Run the Background Real-Time Ingestion Worker
Run the streaming daemon to poll market feeds every 5 seconds, run the OI Intelligence Engine, write snapshots to MySQL, and broadcast live ticks:
```bash
php artisan option-chain:stream --interval=5
```
Options:
- `--interval=5` (streaming cadence in seconds)
- `--symbol=NIFTY` (stream only a specific symbol)
- `--once` (run a single snapshot cycle and exit)

---

## 📡 REST API Reference

| Endpoint | Method | Description |
|---|---|---|
| `/api/option-chain/live/{symbol}` | GET | Returns the live 11-strike ladder, ATM strike, spot price, totals, PCR, support/resistance, and trend indicators. |
| `/api/option-chain/history/{symbol}/{strike}` | GET | Returns historical snapshots for a specific strike across CE and PE. |
| `/api/option-chain/alerts/{symbol}` | GET | Returns recent and critical OI intelligence alerts. |
| `/api/option-chain/replay/{symbol}?timestamp=...` | GET | Returns the exact option chain state at any historical moment today. |

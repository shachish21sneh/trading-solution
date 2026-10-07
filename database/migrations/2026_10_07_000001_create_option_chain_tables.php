<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('underlyings', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique(); // NIFTY, BANKNIFTY, FINNIFTY
            $table->string('name', 100);
            $table->decimal('spot_price', 12, 2)->default(0);
            $table->decimal('strike_step', 10, 2)->default(50); // 50 for NIFTY, 100 for BANKNIFTY
            $table->unsignedInteger('lot_size')->default(50);
            $table->json('available_expiries')->nullable();
            $table->string('selected_expiry', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('option_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('underlying_id')->constrained('underlyings')->cascadeOnDelete();
            $table->string('symbol', 20)->index();
            $table->date('expiry_date')->index();
            $table->decimal('spot_price', 12, 2);
            $table->decimal('strike_price', 12, 2)->index();
            $table->string('option_type', 2)->index(); // CE or PE
            $table->unsignedBigInteger('oi')->default(0);
            $table->bigInteger('change_oi')->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->decimal('iv', 8, 2)->default(0);
            $table->decimal('ltp', 12, 2)->default(0);
            $table->decimal('price_change', 12, 2)->default(0);
            $table->timestamp('snapshot_time')->index();

            // Indexes for lightning fast time-series queries
            $table->index(['symbol', 'snapshot_time'], 'idx_snap_symbol_time');
            $table->index(['symbol', 'strike_price', 'option_type', 'snapshot_time'], 'idx_snap_strike_type_time');
        });

        Schema::create('strike_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('underlying_id')->constrained('underlyings')->cascadeOnDelete();
            $table->string('symbol', 20)->index();
            $table->decimal('strike_price', 12, 2)->index();
            $table->string('option_type', 2)->index(); // CE or PE
            $table->unsignedBigInteger('peak_oi')->default(0);
            $table->timestamp('peak_time')->nullable();
            $table->unsignedBigInteger('lowest_point')->default(0);
            $table->timestamp('bottom_time')->nullable();
            $table->string('current_trend', 50)->default('Neutral');
            $table->timestamp('trend_started_at')->nullable();
            $table->timestamp('started_increasing_at')->nullable();
            $table->timestamp('started_decreasing_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->unsignedBigInteger('current_oi')->default(0);
            $table->unsignedBigInteger('prev_oi')->default(0);
            $table->decimal('highest_drop_pct', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['underlying_id', 'strike_price', 'option_type'], 'uniq_strike_option');
        });

        Schema::create('market_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('underlying_id')->nullable()->constrained('underlyings')->cascadeOnDelete();
            $table->string('symbol', 20)->index();
            $table->string('alert_type', 50)->index(); // Fresh Call Writing, OI Unwinding, Support Shift, etc.
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('option_type', 2)->nullable();
            $table->string('title');
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_alerts');
        Schema::dropIfExists('strike_analytics');
        Schema::dropIfExists('option_snapshots');
        Schema::dropIfExists('underlyings');
    }
};

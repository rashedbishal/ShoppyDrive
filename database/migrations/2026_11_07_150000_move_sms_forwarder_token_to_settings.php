<?php

use App\Models\Setting;
use App\Support\SmsForwarderToken;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Carries an existing SMS_FORWARDER_TOKEN value (including one an owner
     * may have just rotated through the old .env-based flow) into the new
     * Setting-backed location, so moving away from .env doesn't silently
     * log out the forwarder phone. Reads the raw env var directly rather
     * than config('services.sms_forwarder.*'), which this same deploy
     * removes — safe to run on an environment with no value at all, same
     * as a fresh courier credential.
     */
    public function up(): void
    {
        if (Setting::where('key', SmsForwarderToken::KEY)->exists()) {
            return;
        }

        $existingToken = trim((string) env('SMS_FORWARDER_TOKEN', ''));

        if ($existingToken === '') {
            return;
        }

        Setting::create(['key' => SmsForwarderToken::KEY, 'value' => $existingToken, 'group' => 'sms_forwarder']);
    }

    public function down(): void
    {
        Setting::where('key', SmsForwarderToken::KEY)->delete();
    }
};

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SmsPattern;
use Illuminate\Database\Seeder;

class SmsPatternSeeder extends Seeder
{
    public function run(): void
    {
        $patterns = [
            // ── Bkash ────────────────────────────────────────────────────────
            // "You have received Tk 10.00 from 01867603414. ... TrxID DED66E1DFW at ..."
            [
                'provider' => 'bkash',
                'name' => 'Bkash Money Received',
                'identifier' => 'TrxID',
                'txn_id_regex' => 'TrxID\s+([A-Z0-9]+)',
                'amount_regex' => 'received Tk\s+([\d,]+(?:\.\d+)?)',
                'sort_order' => 1,
            ],

            // ── Nagad ────────────────────────────────────────────────────────
            // "Money Received.\nAmount: Tk 507.50\n...\nTxnID: 75AIB4WJ\n..."
            [
                'provider' => 'nagad',
                'name' => 'Nagad Money Received',
                'identifier' => 'Money Received.',
                'txn_id_regex' => 'TxnID:\s*([A-Z0-9]+)',
                'amount_regex' => 'Amount:\s*Tk\s*([\d,]+(?:\.\d+)?)',
                'sort_order' => 2,
            ],

            // "Cash In Received.\nAmount: Tk 2000.00\n...\nTxnID: 75BVN1S6\n..."
            [
                'provider' => 'nagad',
                'name' => 'Nagad Cash In Received',
                'identifier' => 'Cash In Received.',
                'txn_id_regex' => 'TxnID:\s*([A-Z0-9]+)',
                'amount_regex' => 'Amount:\s*Tk\s*([\d,]+(?:\.\d+)?)',
                'sort_order' => 3,
            ],
        ];

        foreach ($patterns as $data) {
            SmsPattern::firstOrCreate(
                ['provider' => $data['provider'], 'name' => $data['name']],
                $data + ['is_active' => true],
            );
        }
    }
}

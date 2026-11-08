<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * SEC-013: the Custom Header/Footer fields hold code pasted by the mother
 * admin. An enforced Content Security Policy only runs scripts carrying this
 * request's nonce, so each pasted script gets it at render time and the saved
 * code never has to change. A script that already has a nonce is left alone.
 */
class CspNonce
{
    public static function addToScripts(string $html): string
    {
        $nonce = Vite::cspNonce();

        if (! $nonce) {
            return $html;
        }

        return (string) preg_replace('/<script\b(?![^>]*\snonce\s*=)/i', '<script nonce="'.e($nonce).'"', $html);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Mpdf\Language\LanguageToFont;

/**
 * DISC-40: mPDF tags each run of Bangla text in an invoice (autoScriptToLang)
 * and asks this class which font to draw it with (autoLangToFont). Bangla
 * gets the bundled Noto Sans Bengali (resources/fonts/noto-sans-bengali),
 * whose OpenType rules mPDF applies so vowel signs and conjuncts render
 * correctly; every other language keeps mPDF's own choice.
 */
class InvoiceLanguageToFont extends LanguageToFont
{
    public const string BANGLA_FONT = 'notosansbengali';

    /**
     * @param  string  $llcc
     * @param  bool  $adobeCJK
     * @return array{0: bool, 1: string}
     */
    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $language = strtolower(explode('-', (string) $llcc)[0]);

        if (in_array($language, ['bn', 'ben'], true)) {
            return [false, self::BANGLA_FONT];
        }

        return parent::getLanguageOptions($llcc, $adobeCJK);
    }
}

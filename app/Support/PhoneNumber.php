<?php

namespace App\Support;

/**
 * Normalización de números telefónicos.
 *
 * Fuente única de verdad: antes esta lógica estaba duplicada (con reglas
 * ligeramente distintas) en CreateCampaignAction, WppCampaignService,
 * WppConnectService y SendThankYouMessageOnButtonResponse.
 */
class PhoneNumber
{
    /** Indicativo que se antepone a los números locales de 10 dígitos. */
    public const DEFAULT_COUNTRY_CODE = '57';

    private const MIN_DIGITS = 11;

    private const MAX_DIGITS = 15;

    /**
     * Solo dígitos, anteponiendo el indicativo si viene un número local de 10.
     */
    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (strlen($digits) === 10) {
            $digits = self::DEFAULT_COUNTRY_CODE.$digits;
        }

        return $digits;
    }

    /**
     * Verifica que el número tenga una longitud plausible en formato internacional.
     */
    public static function isValid(?string $phone): bool
    {
        $length = strlen(self::digits($phone));

        return $length >= self::MIN_DIGITS && $length <= self::MAX_DIGITS;
    }

    /**
     * Formato E.164 (+573001234567). Null si el número no es válido.
     */
    public static function toE164(?string $phone): ?string
    {
        return self::isValid($phone) ? '+'.self::digits($phone) : null;
    }

    /**
     * Formato que espera Twilio (whatsapp:+573001234567). Null si no es válido.
     *
     * Si el valor ya viene con el prefijo whatsapp: se devuelve tal cual, para
     * no alterar los registros creados antes de unificar esta lógica.
     */
    public static function toWhatsApp(?string $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'whatsapp:')) {
            return $raw;
        }

        $e164 = self::toE164($raw);

        return $e164 ? 'whatsapp:'.$e164 : null;
    }

    /**
     * Quita el prefijo whatsapp: y devuelve E.164.
     *
     * La columna `phone` de whatsapp_campaign_responses es VARCHAR(20) y
     * "whatsapp:+573001234567" son 22 caracteres: guardarlo con prefijo
     * truncaba el valor y rompía la deduplicación de respuestas.
     */
    public static function fromWhatsApp(?string $phone): ?string
    {
        return self::toE164(str_replace('whatsapp:', '', (string) $phone));
    }
}

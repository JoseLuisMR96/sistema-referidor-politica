<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_antepone_el_indicativo_a_numeros_locales(): void
    {
        $this->assertSame('573001234567', PhoneNumber::digits('3001234567'));
        $this->assertSame('573001234567', PhoneNumber::digits('300 123 4567'));
    }

    public function test_respeta_numeros_que_ya_traen_indicativo(): void
    {
        $this->assertSame('573001234567', PhoneNumber::digits('+57 300 123 4567'));
        $this->assertSame('573001234567', PhoneNumber::digits('573001234567'));
    }

    public function test_formatea_a_whatsapp_en_e164(): void
    {
        $this->assertSame('whatsapp:+573001234567', PhoneNumber::toWhatsApp('3001234567'));
    }

    public function test_no_vuelve_a_prefijar_un_numero_ya_formateado(): void
    {
        $this->assertSame(
            'whatsapp:+573001234567',
            PhoneNumber::toWhatsApp('whatsapp:+573001234567'),
        );
    }

    public function test_rechaza_numeros_de_longitud_invalida(): void
    {
        $this->assertNull(PhoneNumber::toWhatsApp('300-123'));
        $this->assertNull(PhoneNumber::toWhatsApp(''));
        $this->assertNull(PhoneNumber::toWhatsApp('5730012345678901234'));
    }

    /**
     * La columna whatsapp_campaign_responses.phone es VARCHAR(20): guardar el
     * valor con el prefijo "whatsapp:" lo truncaba.
     */
    public function test_quita_el_prefijo_para_guardar_en_base_de_datos(): void
    {
        $phone = PhoneNumber::fromWhatsApp('whatsapp:+573001234567');

        $this->assertSame('+573001234567', $phone);
        $this->assertLessThanOrEqual(20, strlen($phone));
    }
}

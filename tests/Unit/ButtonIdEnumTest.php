<?php

namespace Tests\Unit;

use App\Enums\ButtonIdEnum;
use PHPUnit\Framework\TestCase;

class ButtonIdEnumTest extends TestCase
{
    /**
     * Twilio devuelve el texto del botón en `Body`, no un ButtonPayload.
     */
    public static function textosDeBotones(): array
    {
        return [
            'nombre completo' => ['Paloma Valencia', ButtonIdEnum::PALOM],
            'con emoji' => ['Paloma Valencia🕊️', ButtonIdEnum::PALOM],
            'solo apellido' => ['Cepeda', ButtonIdEnum::CEPEDA],
            'cepeda con emoji' => ['Cepeda🎯', ButtonIdEnum::CEPEDA],
            'con tilde' => ['Iván Cepeda', ButtonIdEnum::CEPEDA],
            'sin tilde y en mayúsculas' => ['IVAN CEPEDA', ButtonIdEnum::CEPEDA],
            'otro candidato' => ['Otro Candidato🤝', ButtonIdEnum::OTRO_CANDIDATO],
            'otro a secas' => ['otro', ButtonIdEnum::OTRO_CANDIDATO],
        ];
    }

    /**
     * @dataProvider textosDeBotones
     */
    public function test_reconoce_el_boton_desde_el_texto(string $body, ButtonIdEnum $esperado): void
    {
        $this->assertSame($esperado, ButtonIdEnum::fromText($body));
    }

    public function test_ignora_mensajes_que_no_son_botones(): void
    {
        $this->assertNull(ButtonIdEnum::fromText('Hola, buenas tardes'));
        $this->assertNull(ButtonIdEnum::fromText(''));
        $this->assertNull(ButtonIdEnum::fromText(null));
    }

    public function test_cada_boton_tiene_su_propio_campo_de_conteo(): void
    {
        $campos = array_map(
            fn (ButtonIdEnum $b) => $b->countField(),
            ButtonIdEnum::cases(),
        );

        $this->assertSame($campos, array_unique($campos));
    }

    public function test_valida_identificadores_conocidos(): void
    {
        $this->assertTrue(ButtonIdEnum::isValid('palom'));
        $this->assertFalse(ButtonIdEnum::isValid('inexistente'));
        $this->assertFalse(ButtonIdEnum::isValid(null));
    }
}

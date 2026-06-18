<?php

namespace Tests\Fase4;

use Tests\TestCase;
use App\Casts\MoedaBR;
use App\Cliente;

/**
 * M1.4 — cast de moeda/decimal BR (componente compartilhado da UX nova).
 */
class MoedaBRCastTest extends TestCase
{
    private MoedaBR $cast;
    private Cliente $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cast = new MoedaBR();
        $this->model = new Cliente();
    }

    private function castSet($v)
    {
        return $this->cast->set($this->model, 'valor', $v, []);
    }

    private function castGet($v)
    {
        return $this->cast->get($this->model, 'valor', $v, []);
    }

    public function test_set_formato_br_com_milhar()
    {
        $this->assertSame(1234.56, $this->castSet('1.234,56'));
        $this->assertSame(1000.0, $this->castSet('1.000,00'));
    }

    public function test_set_formato_br_sem_milhar()
    {
        $this->assertSame(1234.56, $this->castSet('1234,56'));
    }

    public function test_set_formato_us_e_numerico()
    {
        $this->assertSame(1234.56, $this->castSet('1234.56'));
        $this->assertSame(99.9, $this->castSet(99.9));
        $this->assertSame(100.0, $this->castSet(100));
    }

    public function test_set_vazio_e_nulo()
    {
        $this->assertNull($this->castSet(''));
        $this->assertNull($this->castSet(null));
    }

    public function test_get_sempre_float_ou_null()
    {
        $this->assertSame(10.5, $this->castGet('10.5'));
        $this->assertNull($this->castGet(null));
    }
}

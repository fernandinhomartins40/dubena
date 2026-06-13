<?php

namespace App\Enums;

use stdClass;

class ContaextratoAcao
{
    // Defina as constantes que representam os "casos" do enum
    const Ignorar    = 1;
    const Lancar     = 2;
    const Baixar     = 3;
    const Transferir = 4;
    const LancarBaixar = 5;

    /**
     * @var int O valor da ação
     */
    private $value;

    /**
     * @var string A descrição da ação
     */
    private $description;

    // Construtor privado para evitar instâncias diretas
    private function __construct(int $value, string $description)
    {
        $this->value = $value;
        $this->description = $description;
    }

    /**
     * Mapeia os valores e suas descrições.
     * @return array
     */
    private static function getMap()
    {
        return [
            self::Ignorar       => "Ignorar Lançamento",
            self::Lancar        => "Lançar no Caixa",
            self::Baixar        => "Baixar Título",
            self::Transferir    => "Transferir para outro Caixa",
            self::LancarBaixar  => "Lançar no Caixa ou Baixar Titulo",
        ];
    }

    // Métodos para obter o valor e a descrição
    public function getValue(): int
    {
        return $this->value;
    }

    public function getDesc(): string
    {
        return $this->description;
    }

    // --- Métodos de Criação e Conversão ---

    /**
     * Cria uma instância do enum a partir de um valor.
     * @param int $value
     * @return self|null
     */
    public static function from(int $value): ?self
    {
        if (!in_array($value, self::getAllValues())) {
            return null; // ou jogue uma exceção, se preferir
        }

        $map = self::getMap();
        return new self($value, $map[$value]);
    }

    /**
     * Retorna todas as instâncias do enum.
     * @return self[]
     */
    public static function all(): array
    {
        $cases = [];
        $map = self::getMap();

        foreach ($map as $value => $description) {
            $cases[] = new self($value, $description);
        }

        return $cases;
    }

    // --- Métodos de Utilidade ---

    /**
     * Retorna todos os valores como array.
     * @return int[]
     */
    public static function getAllValues(): array
    {
        return array_keys(self::getMap());
    }

    /**
     * Retorna os casos para uso em um campo de seleção.
     * @return array
     */
    public static function getCasesForSelect(): array
    {
        return self::getMap();
    }

    /**
     * Retorna os casos formatados para JSON.
     * @return stdClass[]
     */
    public static function getForJson(): array
    {
        $cases = [];
        $map = self::getMap();

        foreach ($map as $value => $description) {
            $perf = new stdClass();
            $perf->id = $value;
            $perf->label = $description;
            $cases[] = $perf;
        }

        return $cases;
    }
}

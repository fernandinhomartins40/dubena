<?php

namespace App\Domain\Monitora\Contracts;

/**
 * Driver de sincronização com o SGCasa (GATE externo — N11). Isola a integração de
 * rastreamento (busca de posições, envio de localização de clientes) para o
 * MonitoraSyncService ser testável sem o serviço externo.
 */
interface SgcasaDriver
{
    /**
     * Busca as posições mais recentes dos veículos no provedor externo.
     *
     * @param list<string> $imeis
     * @return list<array{imei:string, latitude:float, longitude:float, velocidade:float, ignicao:bool, registrado_em:string}>
     */
    public function buscarPosicoes(array $imeis): array;
}

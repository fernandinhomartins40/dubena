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

    /**
     * Aparelhos conhecidos pelo provedor, com o nome que o operador deu a cada um.
     *
     * Permite descobrir rastreador instalado num veículo que ainda não foi
     * cadastrado no ERP. Sem isso, um aparelho novo fica invisível até alguém
     * perceber a ausência e cadastrá-lo à mão.
     *
     * @return list<array{imei:string, nome:string}>
     */
    public function listarAparelhos(): array;
}

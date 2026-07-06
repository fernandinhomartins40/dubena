import Http from "@/helpers/http"

/**
 * MarketplaceService (F7) — descoberta PÚBLICA de revendas por geolocalização.
 * O app lista as empresas que atendem o ponto e o usuário escolhe a "loja ativa";
 * a cobertura é REVALIDADA no servidor na criação do pedido (a escolha aqui é UX).
 * Rotas públicas (sem Bearer) e rate-limited no servidor.
 */

export interface EmpresaMarketplace {
    id: number
    nome: string
    telefone: string | null
    latitude: number | null
    longitude: number | null
    distancia_km: number | null
    tempo_entrega_min: number | null
    cidade: { id: number; nome: string; uf: string } | null
}

export interface CidadePlataforma {
    id: number
    nome: string
    uf: string
}

/** Empresas que atendem o ponto (mais perto primeiro). */
const GetEmpresas = (latitude: number, longitude: number): Promise<EmpresaMarketplace[]> =>
    Http.PrepareRequest("app/v1/marketplace/empresas", "POST", { latitude, longitude }, false)

/** Cidades ativas atendidas pela plataforma. */
const GetCidades = (): Promise<CidadePlataforma[]> =>
    Http.PrepareRequest("app/v1/marketplace/cidades", "GET", null, false)

const MarketplaceService = {
    GetEmpresas,
    GetCidades,
}

export default MarketplaceService

import Http from "@/helpers/http"
import { ArquivoUpload } from "@/helpers/camera"
import { MissaoAtiva, ProdutoVenda, ProximaCasa, StatusVisita } from "@/types/types"

/**
 * MissaoService (L7/L8) — execução de missões de campo contra
 * `app/v1/entregador/missao/*`. Toda regra (evidência obrigatória, status,
 * preço da venda) vive no ERP; aqui só montamos as requisições.
 */

const Atual = (): Promise<MissaoAtiva | null> =>
    Http.PrepareRequest("app/v1/entregador/missao", "GET")

const Iniciar = (): Promise<{ id: number; status: string }> =>
    Http.PrepareRequest("app/v1/entregador/missao/iniciar", "POST")

/** Registra uma visita (residência) — multipart com foto de evidência. */
const RegistrarVisita = (
    dados: {
        status: StatusVisita
        latitude?: number | null
        longitude?: number | null
        cliente_id?: number | null
        observacao?: string
        tipo_foto?: "fachada" | "panfleto" | "visita"
    },
    foto?: ArquivoUpload | null,
): Promise<{ id: number; status: string }> => {
    const form = new FormData()
    form.append("status", dados.status)
    if (dados.latitude != null) form.append("latitude", String(dados.latitude))
    if (dados.longitude != null) form.append("longitude", String(dados.longitude))
    if (dados.cliente_id != null) form.append("cliente_id", String(dados.cliente_id))
    if (dados.observacao) form.append("observacao", dados.observacao)
    if (dados.tipo_foto) form.append("tipo_foto", dados.tipo_foto)
    if (foto) form.append("foto", foto as any)
    return Http.PrepareForm("app/v1/entregador/missao/visitas", form)
}

/** Envia a trilha GPS em lote. */
const Trilha = (
    pontos: { latitude: number; longitude: number; registrado_em?: string }[],
): Promise<{ gravados: number }> =>
    Http.PrepareRequest("app/v1/entregador/missao/trilha", "POST", { pontos })

const ProximaCasaSugestao = (lat: number, lng: number): Promise<ProximaCasa | null> =>
    Http.PrepareRequest(`app/v1/entregador/missao/proxima-casa?lat=${lat}&lng=${lng}`, "GET")

const Adiar = (
    motivo: "nova_entrega" | "emergencia" | "veiculo" | "clima" | "outro",
    detalhe?: string,
): Promise<{ id: number; status: string }> =>
    Http.PrepareRequest("app/v1/entregador/missao/adiar", "POST", { motivo, detalhe: detalhe ?? null })

const Concluir = (): Promise<{ id: number; status: string }> =>
    Http.PrepareRequest("app/v1/entregador/missao/concluir", "POST")

// ── Vendas em campo (L8) ──

const Produtos = (): Promise<ProdutoVenda[]> =>
    Http.PrepareRequest("app/v1/entregador/missao/produtos", "GET")

/** Venda de gás entregue na hora (multipart — foto conforme missão). */
const VenderGas = (
    dados: {
        cliente_id: number
        itens: { produto_id: number; quantidade: number }[]
        latitude?: number | null
        longitude?: number | null
        observacao?: string
    },
    foto?: ArquivoUpload | null,
): Promise<{ visita_id: number; pedido_id: number }> => {
    const form = new FormData()
    form.append("cliente_id", String(dados.cliente_id))
    dados.itens.forEach((i, idx) => {
        form.append(`itens[${idx}][produto_id]`, String(i.produto_id))
        form.append(`itens[${idx}][quantidade]`, String(i.quantidade))
    })
    if (dados.latitude != null) form.append("latitude", String(dados.latitude))
    if (dados.longitude != null) form.append("longitude", String(dados.longitude))
    if (dados.observacao) form.append("observacao", dados.observacao)
    if (foto) form.append("foto", foto as any)
    return Http.PrepareForm("app/v1/entregador/missao/venda", form)
}

/** Cadastro rápido de cliente em campo. */
const CadastrarCliente = (dados: {
    nome: string
    endereco?: string
    numero?: string
    telefone?: string
    latitude?: number | null
    longitude?: number | null
}): Promise<{ id: number; nome: string }> =>
    Http.PrepareRequest("app/v1/entregador/missao/clientes", "POST", dados)

const MissaoService = {
    Atual, Iniciar, RegistrarVisita, Trilha, ProximaCasaSugestao,
    Adiar, Concluir, Produtos, VenderGas, CadastrarCliente,
}

export default MissaoService

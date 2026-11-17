import * as FileSystem from "expo-file-system"

/**
 * Arquivos de uma escrita multipart que pode ir para a fila offline (P2-C-05).
 *
 * **Por que copiar.** A foto da câmera e o PNG da assinatura nascem no
 * `cacheDirectory`, que o sistema pode limpar quando quiser — e a fila precisa
 * deles depois de um reinício, talvez horas depois, quando o sinal voltar.
 * Aqui eles vão para `documentDirectory/fila-offline/<chave>/`, que só o app
 * apaga; a fila guarda o CAMINHO, nunca o conteúdo em base64 dentro do MMKV
 * (uma foto de 1 MB inflaria o JSON da fila a cada gravação).
 *
 * A pasta leva a chave de idempotência no nome: quando o item sai da fila
 * (enviado, descartado, dispensado), `apagarArquivos(chave)` remove tudo dele.
 */

export type ArquivoUpload = { uri: string; name: string; type: string }

const pasta = (chave: string) => `${FileSystem.documentDirectory}fila-offline/${chave}/`

/** Copia os anexos para a pasta da chave e devolve os novos caminhos. */
export async function guardarArquivos(
    chave: string,
    arquivos: Record<string, ArquivoUpload | null | undefined>,
): Promise<Record<string, ArquivoUpload>> {
    const destino = pasta(chave)
    await FileSystem.makeDirectoryAsync(destino, { intermediates: true }).catch(() => {})

    const guardados: Record<string, ArquivoUpload> = {}
    for (const [campo, arq] of Object.entries(arquivos)) {
        if (!arq) continue
        const nome = `${campo}-${arq.name}`.replace(/[^\w.\-]/g, "_")
        const uri = `${destino}${nome}`
        await FileSystem.copyAsync({ from: arq.uri, to: uri })
        guardados[campo] = { uri, name: arq.name, type: arq.type }
    }
    return guardados
}

/** Remove os anexos de uma chave. Idempotente: pasta inexistente não é erro. */
export async function apagarArquivos(chave: string): Promise<void> {
    await FileSystem.deleteAsync(pasta(chave), { idempotent: true }).catch(() => {})
}

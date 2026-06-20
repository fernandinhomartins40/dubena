import { useRef, useState } from 'react'
import { ShieldCheck, ShieldAlert, Upload, KeyRound } from 'lucide-react'
import { Button, Field, Input, Badge, Skeleton, toast } from '@/components/ui'
import { useCertificadoStatus, useUploadCertificado, useSalvarNfceToken } from './api'

/**
 * Certificado digital (.pfx) + senha + tokens CSC da NFC-e.
 * Replica a configuração fiscal do legado (saveCertNf) no SPA. A emissão real
 * depende de validar em homologação SEFAZ (gate externo).
 */
export function CertificadoSection({ empresaId }: { empresaId: number }) {
  const { data: status, isLoading } = useCertificadoStatus(empresaId)
  const upload = useUploadCertificado(empresaId)
  const salvarToken = useSalvarNfceToken(empresaId)
  const fileRef = useRef<HTMLInputElement>(null)
  const [file, setFile] = useState<File | null>(null)
  const [senha, setSenha] = useState('')
  const [tokens, setTokens] = useState<Record<string, string>>({})

  async function enviar() {
    if (!file) { toast.error('Selecione o arquivo .pfx do certificado.'); return }
    if (!senha) { toast.error('Informe a senha do certificado.'); return }
    try {
      await upload.mutateAsync({ file, senha })
      toast.success('Certificado digital enviado.'); setFile(null); setSenha('')
      if (fileRef.current) fileRef.current.value = ''
    } catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao enviar o certificado.') }
  }

  async function salvarTokens() {
    try { await salvarToken.mutateAsync(tokens); toast.success('Tokens NFC-e salvos.'); setTokens({}) }
    catch (e: any) { toast.error(e?.response?.data?.message ?? 'Erro ao salvar tokens.') }
  }

  if (isLoading) return <div className="border-t border-border pt-4 mt-4"><Skeleton className="h-24 w-full" /></div>

  return (
    <div className="border-t border-border pt-4 mt-4 space-y-6">
      {/* Certificado */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <p className="text-sm font-semibold">Certificado digital (A1 / .pfx)</p>
          {status?.tem_certificado
            ? <Badge variant="success"><ShieldCheck size={12} className="mr-1" /> Configurado</Badge>
            : <Badge variant="warning"><ShieldAlert size={12} className="mr-1" /> Não enviado</Badge>}
        </div>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
          <Field label="Arquivo (.pfx / .p12)" className="md:col-span-1">
            <input ref={fileRef} type="file" accept=".pfx,.p12"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-secondary file:px-3 file:py-2 file:text-sm file:font-medium hover:file:bg-secondary/80" />
          </Field>
          <Field label="Senha do certificado" hint={status?.tem_senha ? 'Já existe uma senha salva' : undefined}>
            <Input type="password" value={senha} onChange={(e) => setSenha(e.target.value)} />
          </Field>
          <Button loading={upload.isPending} onClick={enviar}><Upload size={16} /> Enviar certificado</Button>
        </div>
        <p className="mt-2 text-xs text-muted-foreground">O certificado é necessário para transmitir NF-e/NFC-e à SEFAZ. Valide em homologação antes de produção.</p>
      </div>

      {/* CSC / Token NFC-e */}
      <div>
        <div className="flex items-center justify-between mb-2">
          <p className="text-sm font-semibold flex items-center gap-2"><KeyRound size={15} /> CSC / Token NFC-e</p>
          <div className="flex gap-1">
            {status?.tem_csc_homolog && <Badge variant="secondary">Homolog</Badge>}
            {status?.tem_csc_producao && <Badge variant="success">Produção</Badge>}
          </div>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <Field label="CSC (homologação)"><Input value={tokens.nfcetoken ?? ''} onChange={(e) => setTokens((t) => ({ ...t, nfcetoken: e.target.value }))} placeholder={status?.tem_csc_homolog ? '•••••• (salvo)' : ''} /></Field>
          <Field label="ID do CSC (homologação)"><Input value={tokens.nfcetokenid ?? ''} onChange={(e) => setTokens((t) => ({ ...t, nfcetokenid: e.target.value }))} /></Field>
          <Field label="CSC (produção)"><Input value={tokens.nfcetoken_prod ?? ''} onChange={(e) => setTokens((t) => ({ ...t, nfcetoken_prod: e.target.value }))} placeholder={status?.tem_csc_producao ? '•••••• (salvo)' : ''} /></Field>
          <Field label="ID do CSC (produção)"><Input value={tokens.nfcetokenid_prod ?? ''} onChange={(e) => setTokens((t) => ({ ...t, nfcetokenid_prod: e.target.value }))} /></Field>
        </div>
        <Button variant="outline" className="mt-3" loading={salvarToken.isPending} onClick={salvarTokens}>Salvar tokens NFC-e</Button>
      </div>
    </div>
  )
}

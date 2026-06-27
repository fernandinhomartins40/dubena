import { useNavigate } from 'react-router-dom'
import { ShieldX } from 'lucide-react'
import { Button } from '@/components/ui'

/**
 * Página "Sem acesso" (403 amigável) — mostrada quando o usuário abre uma rota
 * para a qual não tem permissão. NÃO redireciona: explica e oferece voltar.
 * A autoridade final continua sendo o backend; isto é a borda do front.
 */
export function SemAcessoPage() {
  const navigate = useNavigate()
  return (
    <div className="grid min-h-[60vh] place-items-center">
      <div className="max-w-md text-center">
        <div className="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-destructive/10 text-destructive">
          <ShieldX size={28} />
        </div>
        <h1 className="text-xl font-bold text-foreground">Sem acesso</h1>
        <p className="mt-2 text-sm text-muted-foreground">
          Você não tem permissão para acessar esta área. Se acredita que isso é um engano,
          fale com o administrador da sua empresa.
        </p>
        <Button className="mt-5" onClick={() => navigate('/')}>Voltar ao início</Button>
      </div>
    </div>
  )
}

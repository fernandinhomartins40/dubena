import { Link } from 'react-router-dom'
import { Button } from '@/components/ui'

export function NotFoundPage() {
  return <div className="grid min-h-[60vh] place-items-center text-center"><div className="max-w-md">
    <p className="text-sm font-semibold text-muted-foreground">404</p><h1 className="mt-3 text-2xl font-bold">Página não encontrada</h1>
    <p className="mt-3 text-muted-foreground">Este endereço não corresponde a uma página do sistema. Use o menu ou volte ao início.</p>
    <Button asChild className="mt-6"><Link to="/">Voltar ao início</Link></Button>
  </div></div>
}

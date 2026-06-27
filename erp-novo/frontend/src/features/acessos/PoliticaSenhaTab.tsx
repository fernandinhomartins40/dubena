import { useEffect, useState } from 'react'
import {
  Card, CardHeader, CardTitle, CardDescription, CardContent,
  Button, Input, Field, CheckboxField, toast,
} from '@/components/ui'
import { usePoliticaSenha, useSalvarPoliticaSenha, type PoliticaSenha } from './api'

/**
 * Política de senha da empresa (A5) — mínimo de caracteres, exigência de
 * complexidade e expiração. Aplicada na criação/reset de senha de usuários.
 */
export function PoliticaSenhaTab() {
  const { data } = usePoliticaSenha()
  const salvar = useSalvarPoliticaSenha()
  const [form, setForm] = useState<PoliticaSenha>({ min_len: 8, exige_complexidade: false, expira_dias: 0 })

  useEffect(() => { if (data) setForm(data) }, [data])

  async function onSalvar() {
    try {
      await salvar.mutateAsync(form)
      toast.success('Política de senha salva.')
    } catch (e: any) {
      toast.error(e?.response?.data?.message ?? 'Erro ao salvar.')
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Política de senha</CardTitle>
        <CardDescription>Regras aplicadas a novas senhas e redefinições nesta empresa.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 max-w-md">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Mínimo de caracteres">
            <Input type="number" min={6} max={64} value={form.min_len}
              onChange={(e) => setForm((f) => ({ ...f, min_len: Number(e.target.value) }))} />
          </Field>
          <Field label="Expira em (dias, 0 = nunca)">
            <Input type="number" min={0} max={3650} value={form.expira_dias}
              onChange={(e) => setForm((f) => ({ ...f, expira_dias: Number(e.target.value) }))} />
          </Field>
        </div>
        <CheckboxField
          label="Exigir complexidade (maiúsculas, minúsculas e números)"
          checked={form.exige_complexidade}
          onChange={(c) => setForm((f) => ({ ...f, exige_complexidade: c }))}
        />
        <Button loading={salvar.isPending} onClick={onSalvar}>Salvar política</Button>
      </CardContent>
    </Card>
  )
}

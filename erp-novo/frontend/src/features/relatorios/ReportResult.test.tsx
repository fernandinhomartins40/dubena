import { render, screen } from '@testing-library/react'
import { it, expect } from 'vitest'
import { ReportResult, reportValue } from './ReportResult'

it('apresenta DRE com resultado negativo e contas detalhadas', () => {
  render(<ReportResult value={{ receitas: [{ plano: 'Vendas', total: 100 }], despesas: [{ plano: 'Custos', total: 150 }], total_receitas: 100, total_despesas: 150, resultado: -50 }} />)
  expect(screen.getByText('Resultado')).toBeVisible()
  expect(screen.getByText(/-R\$\s*50,00/)).toBeVisible()
  expect(screen.getByText('Custos')).toBeVisible()
  expect(screen.getAllByRole('table')).toHaveLength(2)
})
it('não formata quantidade como dinheiro ou transforma ausência em zero', () => {
  expect(reportValue('quantidade', 12)).toBe('12')
  expect(reportValue('saldo_final', null)).toBe('—')
  expect(reportValue('cpf', '01234567890')).toBe('01234567890')
})
it('preserva colunas que existem apenas em linhas posteriores', () => {
  render(<ReportResult value={[{ cliente: 'Ana' }, { cliente: 'Bia', situacao: 'ATIVO' }]} />)
  expect(screen.getByRole('columnheader', { name: 'Situação' })).toBeVisible()
  expect(screen.getByText('ATIVO')).toBeVisible()
})

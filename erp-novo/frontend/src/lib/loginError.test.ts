import { it, expect } from 'vitest'
import { loginError } from './loginError'
it('diferencia conexão, credencial e servidor indisponível', () => {
  expect(loginError(new Error('Network Error'))).toContain('conexão')
  expect(loginError({ response: { status: 401 } })).toContain('senha inválidos')
  expect(loginError({ response: { status: 503 } })).toContain('serviço de acesso está indisponível')
})
it('só informa intervalo quando o servidor fornece segundos válidos', () => {
  expect(loginError({ response: { status: 429 } })).not.toMatch(/minutos|segundos/)
  expect(loginError({ response: { status: 429, headers: { 'retry-after': '45' } } })).toContain('45 segundos')
  expect(loginError({ response: { status: 429, headers: { 'retry-after': 'invalid' } } })).not.toContain('segundos')
})

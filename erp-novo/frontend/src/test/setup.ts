import '@testing-library/jest-dom/vitest'
import { configure } from '@testing-library/react'

// findBy/waitFor desistem em 1 s por padrão. Com a máquina ocupada a falha
// pulava de teste em teste (convênio, cliente, config) a cada rodada — sinal de
// espera curta, não de defeito: defeito real falha sempre no mesmo lugar.
configure({ asyncUtilTimeout: 4000 })

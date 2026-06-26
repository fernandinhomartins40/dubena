/** Props compartilhadas pelas sub-abas de configuração da empresa (F18.5). */
export interface ConfigSubtabProps {
  form: Record<string, any>
  campo: (k: string, v: any) => void
  labels: Record<string, string | null>
  lbl: (k: string, v: string | null) => void
}

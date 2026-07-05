/**
 * Validadores puros — agora vivem no pacote COMPARTILHADO (@shared/validators),
 * reusados pelos dois apps (M-2). Reexportados aqui para os imports existentes
 * (`@/helpers/validators`) continuarem valendo.
 */
export { validateCpf, validateBirthDate } from "@shared/validators"

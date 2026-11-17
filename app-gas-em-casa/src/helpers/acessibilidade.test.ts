import { describe, it, expect } from "vitest"
import fs from "node:fs"
import path from "node:path"

/**
 * P2-B-14 — guardião: todo tocável do app tem papel e nome para o leitor de tela.
 *
 * A Rodada 2 contou 0 `accessibilityLabel` em 36 tocáveis: botões só com ícone
 * (voltar, +/−, lixeira, copiar PIX) eram anunciados como "botão" sem nome, ou
 * nem isso. Este teste varre os .tsx e falha se um `<Pressable>`,
 * `<TouchableOpacity>`, `<AnimatedPressable>` ou `<IconButton>` nascer sem
 * `accessibilityRole` E `accessibilityLabel`. O `Button` da casa já os define
 * por dentro (título vira rótulo), por isso não entra na lista.
 *
 * Piso de contagem: um guardião que varre zero arquivos passa verde (CLAUDE.md).
 */
const RAIZ = path.resolve(__dirname, "..")
const TAGS = /<(Pressable|TouchableOpacity|TouchableHighlight|AnimatedPressable|IconButton)\b/g

function arquivosTsx(dir: string): string[] {
    return fs.readdirSync(dir, { withFileTypes: true }).flatMap((d) => {
        const p = path.join(dir, d.name)
        return d.isDirectory() ? arquivosTsx(p) : p.endsWith(".tsx") ? [p] : []
    })
}

/** Texto da tag de abertura: do `<Nome` até o `>` que fecha a tag (ignora `>` dentro de `{...}`). */
function tagDeAbertura(fonte: string, inicio: number): string {
    let chaves = 0
    for (let i = inicio; i < fonte.length; i++) {
        const c = fonte[i]
        if (c === "{") chaves++
        else if (c === "}") chaves--
        else if (c === ">" && chaves === 0 && fonte[i - 1] !== "=") return fonte.slice(inicio, i + 1)
    }
    return fonte.slice(inicio)
}

export function tocaveisSemRotulo(fonte: string): string[] {
    const faltando: string[] = []
    for (const m of fonte.matchAll(TAGS)) {
        const tag = tagDeAbertura(fonte, m.index!)
        // A definição do próprio componente (`<Pressable {...rest}>` dentro de
        // AnimatedPressable) recebe as props de quem o usa.
        // Idem para o botão da tab bar: o react-navigation já entrega papel e
        // rótulo nas props. `accessible={false}` marca o invólucro que só leva o
        // foco a um TextInput — o elemento acessível é o campo.
        if (/\{\.\.\.\(?(rest|buttonProps|props)\b/.test(tag)) continue
        if (/accessible=\{false\}/.test(tag)) continue
        if (!/accessibilityRole=/.test(tag) || !/accessibilityLabel=/.test(tag)) {
            faltando.push(tag.split("\n")[0].trim())
        }
    }
    return faltando
}

describe("acessibilidade dos tocáveis (P2-B-14)", () => {
    it("o detector acusa um tocável sem rótulo e aceita um com rótulo", () => {
        expect(tocaveisSemRotulo(`<Pressable onPress={() => a > b}><X /></Pressable>`)).toHaveLength(1)
        expect(
            tocaveisSemRotulo(`<Pressable onPress={f} accessibilityRole="button" accessibilityLabel="Voltar">`),
        ).toHaveLength(0)
    })

    it("todo tocável do app tem accessibilityRole e accessibilityLabel", () => {
        const arquivos = arquivosTsx(path.join(RAIZ, "app")).concat(arquivosTsx(path.join(RAIZ, "components")))
        let tocaveis = 0
        const faltas: string[] = []
        for (const arq of arquivos) {
            const fonte = fs.readFileSync(arq, "utf8")
            tocaveis += [...fonte.matchAll(TAGS)].length
            for (const f of tocaveisSemRotulo(fonte)) faltas.push(`${path.relative(RAIZ, arq)}: ${f}`)
        }
        expect(arquivos.length).toBeGreaterThan(40)
        expect(tocaveis).toBeGreaterThan(30)
        expect(faltas).toEqual([])
    })
})

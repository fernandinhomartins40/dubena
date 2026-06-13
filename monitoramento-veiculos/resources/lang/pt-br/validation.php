<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted'             => 'O campo :attribute deve ser aceito.',
    'active_url'           => ':attribute não é uma URL válida.',
    'after'                => ':attribute deve ser uma data depois de :date.',
    'alpha'                => ':attribute deve conter somente letras.',
    'alpha_dash'           => ':attribute deve conter letras, números e traços.',
    'alpha_num'            => ':attribute deve conter somente letras e números.',
    'array'                => ':attribute deve ser um array.',
    'before'               => ':attribute deve ser uma data antes de :date.',
    'between'              => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'file'    => 'O arquivo :attribute deve estar entre :min e :max kilobytes.',
        'string'  => 'O campo :attribute deve estar entre :min e :max caracteres.',
        'array'   => 'O campo :attribute deve ter entre :min e :max itens.',
    ],
    'boolean'              => ':attribute deve ser verdadeiro ou falso.',
    'confirmed'            => 'A confirmação de :attribute não confere.',
    'date'                 => 'O campo :attribute não é uma data válida.',
    'date_format'          => ':attribute não confere com o formato :format.',
    'different'            => 'Os campos :attribute e :other devem ser diferentes.',
    'digits'               => ':attribute deve ter :digits dígitos.',
    'digits_between'       => ':attribute deve ter entre :min e :max dígitos.',
    'email'                => 'O campo :attribute deve ser um endereço de e-mail válido.',
    'exists'               => 'O :attribute selecionado é inválido.',
    'filled'               => ':attribute é um campo obrigatório.',
    'image'                => ':attribute deve ser uma imagem.',
    'in'                   => ':attribute é inválido.',
    'integer'              => ':attribute deve ser um inteiro.',
    'ip'                   => 'O campo:attribute deve ser um endereço IP válido.',
    'json'                 => ':attribute deve ser um JSON válido.',
    'max'                  => [
        'numeric' => 'O campo :attribute não deve ser maior que :max.',
        'file'    => 'O arquivo :attribute não deve ter mais que :max kilobytes.',
        'string'  => 'O campo :attribute não deve ter mais que :max caracteres.',
        'array'   => 'O campo :attribute não pode ter mais que :max itens.',
    ],
    'mimes'                => 'O arquivo :attribute deve ser do tipo: :values.',
    'min'                  => [
        'numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'file'    => 'O arquivo :attribute deve ter no mínimo :min kilobytes.',
        'string'  => 'O campo :attribute deve ter no mínimo :min caracteres.',
        'array'   => 'O campo :attribute deve ter no mínimo :min itens.',
    ],
    'not_in'               => 'O :attribute selecionado é inválido.',
    'numeric'              => 'O campo :attribute deve ser um número.',
    'regex'                => 'O formato de :attribute é inválido.',
    'required'             => 'O campo :attribute é obrigatório.',
    'required_if'          => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_with'        => 'O campo :attribute é obrigatório quando :values está presente.',
    'required_with_all'    => 'O campo :attribute é obrigatório quando :values estão presentes.',
    'required_without'     => 'O campo :attribute é obrigatório quando :values não está presente.',
    'required_without_all' => 'O campo :attribute é obrigatório quando nenhum destes estão presentes: :values.',
    'same'                 => 'Os campos :attribute e :other devem ser iguais.',
    'size'                 => [
        'numeric' => 'O campo :attribute deve ser :size.',
        'file'    => 'O arquivo :attribute deve ter :size kilobytes.',
        'string'  => 'O campo :attribute deve ter :size caracteres.',
        'array'   => 'O campo :attribute deve conter :size itens.',
    ],
    'string'               => 'O campo :attribute deve ser uma string',
    'timezone'             => 'O campo :attribute deve ser uma timezone válida.',
    'unique'               => 'O campo :attribute já está em uso.',
    'url'                  => 'O formato de :attribute é inválido.',
    // Custom validation
    'cnpj' => "O campo :attribute é inválido. Verifique.",
    'cpf' => "O campo :attribute é inválido. Verifique.",
    'empresa_exists' => "O campo :attribute deve pertencer às empresas permitidas para o usuário.",
    'chave' => "O campo chave deve ter tamanho múltiplo de 3",
    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap attribute place-holders
    | with something more reader friendly such as E-Mail Address instead
    | of "email". This simply helps us make messages a little cleaner.
    |
    */

    'attributes' => ['email' => 'E-mail',
                     'cidade_id' => 'Cidade',
                     'agenciadigito' => 'agência dígito',
                     'postobeneficiario'=> 'posto beneficiário',
                     'descricao' => 'descrição',
                     'datahorafim' => 'Fim',
                     'datahorainicio' => 'Início',
                     'produto_id' => 'Produto',
                     'produtoBrinde_id' => 'brinde',
                     'quantidadepremios' => 'Qde brindes',
                     'quantidadepedidos' => 'Entrege em X compras',
                     'observacoes' => 'Observações',
                     'origemsetor_id' => 'Setor Origem',
                     'destinosetor_id' => 'Setor Destino',
                     'centrocusto_id' => 'Centro Custo',
                     'planoconta_id' => 'Plano Conta',
                     'dataAbertura' => 'Data',
                     'dataFechamento' => 'Data',
                     'tiponf' => 'Tipo',
                     'descricaofiscal' => 'Descricao Fiscal',
                     'origem_icms' => 'Origem ICMS',
                     'cfop' => 'CFOP',
                     'cfopie' => 'CFOP InterEstadual',
                     'aparecetela' => 'Tipo Tela',
                     'rua_id' => 'Endereço',
                     'bairro_id' => 'Bairro',
                     'situacao' => 'Situação',
                     'codigo' => 'Código',
                     'tipopessoa_id' => 'Tipo de Pessoa',
                     'setor_id' => 'Setor',
                     'indicador_ie' => 'Indic. I.E',
                     'segmento_id' => 'Segmento',
                     'colaborador_id' => 'Colaborador',
                     'kmatual' => 'KM Atual',
                     'totallitros' => 'Total Litros',
                     'datacontrato' => 'Data Contrato',
                     'limitecompra' => 'Limite Compra',
                     'diafechamento' => 'Dia Fechamento',
                     'diavencimento' => 'Dia Vencimento',
                     'convenio_id' => 'Empresa Conveniada',
                     'kmalertaantesoleo' => 'Km antes, se Alerta tiver marcado,',
                     'kmtrocaoleo' => 'Km no momento da troca',
                     'oleorendimento' => 'Óleo rendimento',
                     'oleoproximotroca_hd' => 'Km proxima troca',
                     'valorpneu' => 'Valor',
                     'medidapneus' => 'Medida Pneus',
                     'vidautilkm' => 'Vida útil km',
                     'quantidadepneu' => 'Quantidade',
                     'numeronota' => 'Nota Fiscal',
                     'cliente_id' => 'Cliente',
                     'produtoclasse_id' => 'Classe do Produto',
                     'unidademedida_id' => 'Unidade de Medida',
                     'nfgrupofiscal_id' => 'Grupo Fiscal',
                     'nfcest_id' => 'CEST',
                     'nfipi_id' => 'Cód. IPI',
                     'nfetipoitem' => 'Tipo Item',
                     'nfeextipi' => 'Cód. Ex. IPI',
                     'nfecodlst' => 'Cód. Lst',
                     'nfecodgen' => 'Cód Gênero',
                     'produtoretornavel_id' => 'Vasilhame',
                     'quantidadenova' => 'Nova Quantidade',
                     'percentualencargos' => 'Encargos',
                     'nfealiqipi' => 'Valor Aliq da Cide',
                     'nfebcipi' => 'Base IPI',
                     'percentualprovisaodevedores' => 'Provisão Devedores',
                     'percentualremuneracaocapital' => 'Remuneração',
                     'percentualdistribuicaoresul' => 'Distribuição Resultado',
                     'tempoidenchamada' => 'Tempo de Identificação de Chamada',
                     'pedidovalidacartaodias' => 'N° Dias cartãos',
                     'timezone' => 'Timezone',
                     'diastrabalhadosemana' => 'Dias Trabalhados',
                     'monitoramentogrupo_id' => 'Cód Grupo Monitoramento',
                     'quantidade' => 'Quantidade',
                     'valorunitario' => 'Valor Unitario',
                     'condicaopagamento_id' => 'Tipo de Pagamento',
                     'telacontrolakm' => 'Tela Controla Km',
                     //Impostos
                     'nfoperacao_id' => 'Operação',
                     'grupofiscal_id' => 'Grupo Fiscal',
                     'nfcofins_id' => 'Cód. Cofins',
                     'nfcofinsaliq' => 'Aliq Cofins',
                     'nfcofinsbase' => 'Base Cofins',
                     'pfnfcofins_id' => 'Cód Cofins de Pessoa Física',
                     'pfnfcofinsaliq' => 'Aliq Cofins de Pessoa Física',
                     'pfnfcofinsbase' => 'Base Cofins de Pessoa Física',
                     'nficms_id_pj' => 'Cód. Icms',
                     'nficmsaliq' => 'Aliq Icms',
                     'nficmsbase' => 'Base Icms',
                     'pfnficms_id' => 'Cód Icms de Pessoa Física',
                     'pfaliqicmsst' => 'Aliq Icms de Pessoa Física',
                     'pfnficmsbase' => 'Base Icms de Pessoa Física',
                     'nfpis_id' => 'Cód Pis',
                     'nfpisaliq' => 'Aliq Pis',
                     'nfpisbase' => 'Base Pis',
                     'pfnfpis_id' => 'Cód Pis de Pessoa Física',
                     'pfnfpisaliq' => 'Aliq Pis de Pessoa Física',
                     'pfnfpisbase' => 'Base Pis de Pessoa Física',
                     'aliqicmsst' => 'Aliq Icms ST',
                     'modalidadebcicmsstpf' => 'Modalidade BC Icms de Pessoa Física',
                     'modalidadebcicmspf' => 'Modalidade BC Icms ST de Pessoa Física',
                     'modalidadebcicms' => 'Modalidade BC Icms',
                     'modalidadebcicmsst' => 'Modalidade BC Icms ST',
                    //Fim de Impostos
                     'cep' => 'CEP',
                     'numero' => 'Número',
                     'rg' => 'RG',
                     'cpf' => 'CPF',
                     'estoquefechamento_id' => 'Fechamento',
                     'numerocheque' => 'Nº Cheque',
                     'conta_id' => 'Conta',
                     'banco_id' => 'Banco',
                     'pagarreceber' => 'Tipo'
                 ],

];

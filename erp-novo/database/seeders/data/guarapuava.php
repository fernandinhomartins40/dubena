<?php

/**
 * Dados REAIS de Guarapuava/PR para o DemoGuarapuavaSeeder.
 *
 * - Bairros: lista oficial do Plano Diretor 2016/2026 (CONCIDADE Guarapuava),
 *   com coordenada aproximada do centro do bairro (fallback de geocoding/GPS).
 * - Ruas: logradouros reais com CEP verdadeiro, coletados de bases públicas de CEP
 *   (ruacep.com.br / cepbrasil) por bairro. Fontes no comentário de cada bloco.
 *
 * Usado só para massa de DEMONSTRAÇÃO — não é cadastro fiscal de produção.
 */

return [
    'cidade' => ['descricao' => 'Guarapuava', 'uf' => 'PR', 'cod_ibge' => '4109401'],

    // Coordenada aproximada do centro de cada bairro (fallback p/ mapa/GPS).
    'bairros' => [
        'Centro' => ['lat' => -25.3935, 'lng' => -51.4562],
        'Santana' => ['lat' => -25.3870, 'lng' => -51.4490],
        'Trianon' => ['lat' => -25.3790, 'lng' => -51.4640],
        'Vila Carli' => ['lat' => -25.4075, 'lng' => -51.4690],
        'Batel' => ['lat' => -25.4030, 'lng' => -51.4510],
        'Boqueirão' => ['lat' => -25.4100, 'lng' => -51.4380],
        'Bonsucesso' => ['lat' => -25.3760, 'lng' => -51.4720],
        'Primavera' => ['lat' => -25.4180, 'lng' => -51.4600],
        'Cascavel' => ['lat' => -25.4220, 'lng' => -51.4480],
        'Santa Cruz' => ['lat' => -25.3990, 'lng' => -51.4400],
        'Jardim das Américas' => ['lat' => -25.4150, 'lng' => -51.4720],
        'São Cristóvão' => ['lat' => -25.3820, 'lng' => -51.4560],
        'Morro Alto' => ['lat' => -25.4280, 'lng' => -51.4620],
        'Conradinho' => ['lat' => -25.3700, 'lng' => -51.4400],
        'Alto da XV' => ['lat' => -25.3880, 'lng' => -51.4600],
        'Dos Estados' => ['lat' => -25.4000, 'lng' => -51.4750],
        'Industrial' => ['lat' => -25.4300, 'lng' => -51.4300],
        'Vila Bela' => ['lat' => -25.4120, 'lng' => -51.4550],
    ],

    /**
     * Ruas reais por bairro: [descricao, cep]. Fontes:
     *  Centro/Santana/Trianon/Vila Carli — ruacep.com.br/pr/guarapuava/<bairro>/logradouros/
     */
    'ruas' => [
        'Centro' => [
            ['Rua XV de Novembro', '85010-090'],
            ['Rua Afonso Alves de Camargo', '85010-320'],
            ['Rua Alcione Bastos', '85010-160'],
            ['Rua Arlindo Ribeiro', '85010-070'],
            ['Rua Azevedo Portugal', '85010-200'],
            ['Rua Barão do Rio Branco', '85010-040'],
            ['Rua Benjamin Constant', '85010-190'],
            ['Rua Brigadeiro Rocha', '85010-210'],
            ['Rua Capitão Frederico Virmond', '85010-120'],
            ['Rua Capitão Rocha', '85010-270'],
            ['Rua Comendador Norberto', '85010-140'],
            ['Rua Cônego Braga', '85010-050'],
            ['Rua Coronel Lustosa', '85010-060'],
            ['Rua Coronel Saldanha', '85010-130'],
            ['Rua Doutor Laranjeiras', '85010-030'],
            ['Rua Guaíra', '85010-010'],
            ['Avenida Manoel Ribas', '85010-180'],
            ['Rua Marechal Floriano Peixoto', '85010-250'],
        ],
        'Santana' => [
            ['Rua Abraham Haick', '85070-690'],
            ['Rua Afonso Alves de Camargo', '85070-200'],
            ['Rua Afonso Botelho', '85070-165'],
            ['Rua Alberto Kohler', '85070-120'],
            ['Rua Alcindo Cardoso Teixeira', '85070-290'],
            ['Rua Almirante Didio Costa', '85070-230'],
            ['Rua América Central', '85070-555'],
            ['Rua Andrade Neves', '85070-160'],
            ['Rua Antônio Barbosa', '85070-070'],
            ['Avenida Antônio Farah', '85070-360'],
            ['Rua Artur Scheidt', '85070-350'],
            ['Rua Assunção', '85070-470'],
            ['Rua Atenas', '85070-650'],
            ['Rua Barcelona', '85070-520'],
            ['Rua Bom Retiro', '85070-040'],
            ['Rua Cândido Ribas', '85070-250'],
        ],
        'Trianon' => [
            ['Rua Afonso Botelho', '85012-030'],
            ['Rua Andrade Neves', '85012-020'],
            ['Rua Belmiro de Miranda', '85012-230'],
            ['Rua Brigadeiro Rocha', '85012-260'],
            ['Rua Capitão Argílio Ferreira', '85012-220'],
            ['Rua Capitão Rocha', '85012-255'],
            ['Rua Cinco de Outubro', '85012-050'],
            ['Rua da Saudade', '85012-010'],
            ['Rua das Acácias', '85012-130'],
        ],
        'Vila Carli' => [
            ['Rua Alagoas', '85045-630'],
            ['Rua Almirante Barroso', '85040-450'],
            ['Rua Amadeu de Paula Cressenti', '85040-422'],
            ['Rua Antônio Dorigon', '85040-140'],
            ['Rua Aymoré', '85040-030'],
            ['Rua Capitão Rocha', '85040-550'],
            ['Rua Carajás', '85040-240'],
            ['Rua Ceará', '85045-600'],
            ['Rua Conde D\'Eu', '85040-290'],
            ['Rua Coronel Emílio Carneiro Duarte', '85040-060'],
            ['Rua Diamantina', '85040-150'],
            ['Rua Érico Veríssimo', '85040-160'],
            ['Rua Francisco Brochado da Rocha', '85040-070'],
        ],
    ],

    /** Centro de Guarapuava — base p/ posições de GPS da frota. */
    'centro' => ['lat' => -25.3935, 'lng' => -51.4562],

    /** Sobrenomes e nomes comuns da região p/ clientes/colaboradores PF realistas. */
    'nomes' => ['Ana', 'Bruno', 'Carla', 'Diego', 'Eduarda', 'Fábio', 'Gabriela', 'Hugo', 'Isabela', 'João', 'Karen', 'Lucas', 'Marina', 'Nelson', 'Otávio', 'Patrícia', 'Rafael', 'Sabrina', 'Tiago', 'Vanessa'],
    'sobrenomes' => ['Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Lima', 'Carneiro', 'Ferreira', 'Ribeiro', 'Almeida', 'Kohler', 'Scheidt', 'Dorigon', 'Virmond', 'Lustosa', 'Camargo', 'Bastos', 'Haick'],

    /** Razões sociais de empresas locais fictícias (PJ) p/ clientes comerciais. */
    'empresas' => ['Mercado', 'Padaria', 'Restaurante', 'Lanchonete', 'Distribuidora', 'Conveniência', 'Churrascaria', 'Pizzaria', 'Hotel', 'Pousada', 'Borracharia', 'Oficina', 'Açougue', 'Sorveteria'],
];

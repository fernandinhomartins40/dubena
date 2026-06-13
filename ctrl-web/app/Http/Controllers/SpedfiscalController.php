<?php

namespace App\Http\Controllers;

use Session;
use App\Empresa;
use App\Nfemitida;
use App\Spedfiscal;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Processors\Sped\SpedProcessor;

class SpedfiscalController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Spedfiscal $spedfiscal)
    {
        $this->authorize('view', $spedfiscal);
        $spedfiscals = [];
        $empresa = Session::get('empresa_padrao')->razao_social;
        return view('speds.fiscal.spedfiscal_form', compact('spedfiscals', 'empresa'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Spedfiscal $spedfiscal)
    {
        $this->authorize('create', $spedfiscal);

        return view('speds.fiscal.spedfiscal_form');
    }

    // Funções ajax para validar Sped Fiscal
    public function validasped($datainicio, $datafim)
    {
        $nfemitidas = Nfemitida::where([['empresa_id', Session::get('empresa_padrao')->id]])
            ->whereBetween('datahoraemissao', [$datainicio . ' 00:00', $datafim . ' 23:59'])
            ->whereNotIn('nfsituacao_id', [100, 101, 102])
            ->orderBy('nfmodelo', 'asc')
            ->orderBy('nfnumero', 'asc')
            ->get();

        if (!count($nfemitidas)) {
            return "Nenhuma NF para o período selecionado.";
        }

        $retorno = "";
        $antnum = -1;
        $antmod = -1;
        foreach ($nfemitidas as $nfemitida) {
            if ($nfemitida->nfnumero != $antnum + 1) {
                if ($antnum != -1 && $antmod == $nfemitida->nfmodelo) {
                    $retorno .= "Existem NFs faltando entre " . $antnum . " e " . $nfemitida->nfnumero . " no modelo: " . $nfemitida->nfmodelo . "\n";
                }
            }
            $antnum = $nfemitida->nfnumero;
            $antmod = $nfemitida->nfmodelo;
            $retorno .= "NF " . $nfemitida->nfnumero . " tem status = " . $nfemitida->nfSituacao->id . "-" . $nfemitida->nfSituacao->msgerroreceita . "\n";
        }
        return $retorno;
    }

    // Funções ajax para gerar Sped Fiscal
    public function gerarsped(Request $request)
    {
        $data = $request->only('mesano', 'c170', 'tipoescrit');
        try {
            $processor = new SpedProcessor($data, $this->getAllowedRegs());
            return $processor->get();
        } catch (\Exception $e) {
//            dd($e->getTrace());
            return getErrorsException($e);
        }
    }

    private function getAllowedRegs()
    {
        $path = "Fiscal";

        return [
            'basePath' => $path,
            'Bloco0'   => $this->getBloco0(),
            'BlocoC'   => $this->getBlocoC(),
            'BlocoD'   => $this->getBlocoD(),
            'BlocoE'   => $this->getBlocoE(),
            'BlocoG'   => $this->getBlocoG(),
            'BlocoH'   => $this->getBlocoH(),
            'BlocoK'   => $this->getBlocoK(),
            'Bloco1'   => $this->getBloco1(),
            'Bloco9'   => $this->getBloco9(),
        ];
    }

    private function getBloco0()
    {
        return [
            'regs' => [
                'Reg0000',
                'Reg0001',
                'Reg0005',
                'Reg0100',
                'Reg0150' => [
                    'multiple' => true
                ],
                'Reg0190' => [
                    'multiple' => true
                ],
                'Reg0200' => [
                    'multiple' => true
                ],
                'Reg0400' => [
                    'multiple' => true
                ],
                'Reg0990'
            ]
        ];
    }

    private function getBlocoC()
    {
        return [
            'regs' => [
                'RegC001',
                'RegC100' => [
                    'multiple'  => true,
                    'childrens' => [
                        'RegC140',
                        'RegC141',
                        'RegC170' => [
                            'childrens' => 'RegC176'
                        ],
                        'RegC190'
                    ] //childrens RegC100
                ], //RegC100
                'RegC500' => [
                    'multiple'  => true,
                    'childrens' => [
                        'RegC590'
                    ] //childrens RegC500
                ],
                'RegC990'
            ]
        ];
    }

    private function getBlocoD()
    {
        return [
            'regs' => [
                'RegD001',
                'RegD100' => [
                    'multiple'  => true,
                    'childrens' => [
                        'RegD190'
                    ] //childrens RegD100
                ], //RegD100
                'RegD500' => [
                    'multiple'  => true,
                    'childrens' => [
                        'RegD590'
                    ] //childrens RegD500
                ], //RegD500                    
                'RegD990'
            ] //regs D
        ];
    }

    private function getBlocoE()
    {
        $empresa = Empresa::where('id', Session::get('empresa_padrao')->id)->get()->first();
        if ($empresa->spedatividade == 0) {
            $blocoE = [
                'regs' => [
                    'RegE001',
                    'RegE100',
                    'RegE200' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegE210'
                        ]
                    ],
                    'RegE500',
                    'RegE520',
                    'RegE990'
                ]
            ];
        } else {
            $blocoE = [
                'regs' => [
                    'RegE001',
                    'RegE100',
                    'RegE110' => [
                        'childrens' => [
                            'RegE116'
                        ]
                    ],
                    'RegE200' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegE210'
                        ]
                    ],
                    'RegE990'
                ]
            ];
        }
        return $blocoE;
    }

    private function getBlocoG()
    {
        return [
            'regs' => [
                'RegG001',
                'RegG990'
            ]
        ];
    }

    private function getBlocoH()
    {
        return [
            'regs' => [
                'RegH001',
                'RegH005' => [
                    'multiple' => true
                ],
                'RegH010' => [
                    'multiple' => true
                ],
                'RegH990'
            ]
        ];
    }

    private function getBlocoK()
    {
        return [
            'regs' => [
                'RegK001',
                'RegK990'
            ]
        ];
    }

    private function getBloco1()
    {
        return [
            'regs' => [
                'Reg1001',
                'Reg1010',
                'Reg1700' => [
                    'multiple' => true,
                    'childrens' => [
                        'Reg1710'
                    ]
                ],
                'Reg1990'
            ]
        ];
    }

    private function getBloco9()
    {
        return [
            'regs' => [
                'Reg9001',
                'Reg9900' => [
                    'multiple' => true
                ],
                'Reg9990',
                'Reg9999'
            ]//regs 9
        ];
    }

}

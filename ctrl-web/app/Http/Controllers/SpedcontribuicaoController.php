<?php

namespace App\Http\Controllers;

use Session;
use App\Spedcontribuicao;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Processors\Sped\SpedProcessor;

// use App\Processors\SpedContribuicao\Bloco0\Reg0000;

class SpedcontribuicaoController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Spedcontribuicao $spedcontribuicao)
    {
        $this->authorize('view', $spedcontribuicao);
        $spedcontribuicaos = [];
        $empresa = Session::get('empresa_padrao')->razao_social;
        return view('speds.contribuicoes.spedcontribuicao_form', compact('spedcontribuicaos', 'empresa'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Spedcontribuicao $spedcontribuicao)
    {
        $this->authorize('create', $spedcontribuicao);
        $empresa = Session::get('empresa_padrao')->razao_social;
        return view('speds.contribuicoes.spedcontribuicao_form', compact('empresa'));
    }

    public function gerarSped(Request $request)
    {
        $data = $request->only('mesano', 'tipoescrit', 'reciboanterior');
        try {
            $proc = new SpedProcessor($data, $this->getAllowedRegs());
            return $proc->get();
        } catch (\Exception $e) {
            return getErrorsException($e);
        }
    }

    private function getAllowedRegs()
    {
        $path = "Contribuicao";

        return [
            'basePath' => $path,
            'Bloco0'   => [
                'regs' => [
                    'Reg0000',
                    'Reg0001',
                    'Reg0100',
                    'Reg0110',
                    'Reg0111',
                    'Reg0140',
                    'Reg0150' => [
                        'multiple' => true,
                    ],
                    'Reg0190' => [
                        'multiple' => true,
                    ],
                    'Reg0200' => [
                        'multiple' => true,
                    ],
                    'Reg0400' => [
                        'multiple' => true,
                    ],
                    'Reg0500' => [
                        'multiple' => true,
                    ],
                    'Reg0990'
                ], //regs 0
                'has010' => false,
            ], //Bloco0
            'BlocoC'   => [
                'regs' => [
                    'RegC001',
                    'RegC010',
                    'RegC100' => [
                        'multiple' => true,
                        'childrens' => [
                            'RegC170',
                            'RegC175'
                        ] //childrens RegC100
                    ],
                    'RegC380' => [
                        'multiple' => true,
                        'childrens' => [
                            'RegC381',
                            'RegC385'
                        ] //childrens RegC380
                    ],
                    'RegC500' => [
                        'multiple' => true,
                        'childrens' => [
                            'RegC501',
                            'RegC505'
                        ] //childrens RegC500
                    ],
                    'RegC990'
                ], //regs C
                'has010' => true,
            ], //BlocoC
            'BlocoD'   => [
                'regs' => [
                    'RegD001',
                    'RegD010',
                    'RegD500' => [
                        'multiple' => true,
                        'childrens' => [
                            'RegD501',
                            'RegD505'
                        ] //childrens RegD500
                    ],
                    'RegD990'
                ], //regs D
                'has010' => true,
            ], //BlocoD
            'BlocoF'   => [
                'regs' => [
                    'RegF001',
                    'RegF010',
                    'RegF990'
                ], //regs F
                'has010' => true,
            ], //BlocoF
            'BlocoM'   => [
                'regs' => [
                    'RegM001',
                    'RegM100' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegM105'
                        ] //childrens RegM100
                    ],
                    'RegM200',
                    'RegM400' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegM410'
                        ] //childrens RegM400
                    ],
                    'RegM500' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegM505'
                        ] //childrens RegM500
                    ],
                    'RegM600',
                    'RegM800' => [
                        'multiple'  => true,
                        'childrens' => [
                            'RegM810'
                        ]  //childrens RegM800
                    ],
                    'RegM990'
                ] //regs M
            ], //BlocoM
            'Bloco1'   => [
                'regs' => [
                    'Reg1001',
                    'Reg1100' => [
                        'multiple'  => true
                    ],
                    'Reg1500' => [
                        'multiple'  => true
                    ],
                    'Reg1990'
                ] //regs 1
            ], //Bloco1
            'Bloco9'   => [
                'regs' => [
                    'Reg9001',
                    'Reg9900' => [
                        'multiple'  => true
                    ],
                    'Reg9990',
                    'Reg9999'
                ] //regs 9
            ] //Bloco9
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

}

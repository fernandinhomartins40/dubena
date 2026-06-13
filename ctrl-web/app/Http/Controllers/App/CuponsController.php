<?php

namespace App\Http\Controllers\App;

use App\Cupom;
use App\Http\Controllers\AppnotificationController;
use App\Http\Controllers\Controller;
use App\Services\CarbonCustom;
use Carbon\Carbon;
use DB;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Session;

class CuponsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function index(Cupom $app)
    {
        $this->authorize("view", $app);

        $empresa_id = Session::get("empresa_padrao")->id;

        $cupons = Cupom::where("empresa_id", $empresa_id)->orderByDesc('created_at')->get();

        return view("cupons.index", compact("cupons"));
    }

    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function create(Cupom $com)
    {
        $this->authorize('create', $com);

        return view('cupons.form');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function edit(Cupom $com, $id)
    {
        $this->authorize('update', $com);

        $cupom = Cupom::find($id);

        $edit = true;

        return view('cupons.form', compact('cupom', 'edit'));
    }


    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function show(Cupom $com, $id)
    {
        $this->authorize('view', $com);

        $cupom = Cupom::find($id);

        $show = true;

        return view('cupons.form', compact('cupom', 'show'));
    }


    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'codigo' => 'required',
        ]);

        $exists = Cupom::where('codigo', $request->get('codigo'))->exists();

        if ($exists)
            return redirect('cupons/create')->withErrors("O código do cupom já está sendo usado.")->withInput();

        $data = $this->validarERetornarDadosRequest($request);

        $data["empresa_id"] = Session::get('empresa_padrao')->id;

        if (!$this->dataEhValida($data))
            return redirect('cupons/create')->withErrors("A data inicial precisa ser menor que a data final!")->withInput();

        $result = $this->validarNotificar($data, 'cupons/create');

        // Retorna um redirect, caso não passe na validação
        if (!is_bool($result)) {
            return $result;
        }

        Cupom::insert($data);

        $this->notificarSeNecessario($data, $data['valor'], $data['tipo']);

        return \Redirect::route('cupons.index')->withMessageSuccess('Cupom cadastrado com sucesso!');
    }

    /**
     * @param array $data
     * @param float $valor
     * @param $tipo
     */
    public function notificarSeNecessario(array $data, float $valor, $tipo)
    {
        // Se o campo "notificado" existe, é porque o usuário requisitou a notificação
        if (isset($data['notificado'])) {
            $payload = [
                "title" => "Cupom disponível",
                "body" => "Um cupom de "
                    . ($tipo == 0
                        ? requestNumeroDecimalOracle($valor)
                        : requestPercentualOracleSemDigitos($valor))
                    . " está disponível pra você"
            ];

            $controller = new AppnotificationController();

            $controller->enviarNotificacaoCupom($payload);
        }
    }

    /**
     * @param array $data
     * @param string $redirect
     * @return bool|Application|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function validarNotificar(array &$data, string $redirect)
    {
        if (isset($data['notificado'])) unset($data['notificado']);

        if (isset($data['notificar']) && $data['notificar'] && $data['ativo']) {
            $date = Carbon::parse($data['datainicio']);
            if (!Carbon::now()->gt($date)) {
                return redirect($redirect)
                    ->withErrors("Não é possível notificar um cupom se sua data de início for futura!")
                    ->withInput();
            }
            $data['notificado'] = true;
            unset($data['notificar']);
        } else if (isset($data['notificar'])) {
            unset($data['notificar']);
        }
        return true;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Factory|Application|View
     * @throws AuthorizationException
     */
    public function update(Request $request, int $id, Cupom $com)
    {
        $this->authorize('update', $com);

        if (Cupom::where('codigo', $request->get('codigo'))
            ->where('id', '!=', $id)
            ->exists()
        )
            return redirect('cupons/' . $id . '/edit')->withErrors("O código do cupom já está sendo usado.")->withInput();

        $data = $this->validarERetornarDadosRequest($request);

        if (!$this->dataEhValida($data))
            return redirect('cupons/' . $id . '/edit')->withErrors("A data inicial precisa ser menor que a data final!")->withInput();

        $cupom = Cupom::find($id);

        $result = $this->validarNotificar($data, 'cupons/' . $id . '/edit');

        // Retorna um redirect, caso não passe na validação
        if (!is_bool($result)) {
            return $result;
        }

        if (isset($data['notificado']) && $data['notificado'] && $cupom->notificado) {
            unset($data['notificado']);
        }

        $data['codigo'] = $cupom->codigo;

        $cupom->update($data);

        $this->notificarSeNecessario($data, $data['valor'], $data['tipo']);

        return \Redirect::route('cupons.index')->withMessageSuccess('Cupom atualizado com sucesso!');
    }


    /**
     * @param Cupom $com
     * @return array
     * @throws AuthorizationException
     */
    public function gerarCodigo(Cupom $com)
    {
        $this->authorize('create', $com);

        return [
            'codigo' => gerarCodigoAleatorio()
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    private function validarERetornarDadosRequest(Request $request): array
    {

        $this->validate($request, [
            'limiteuso' => 'required|numeric|min:1|max:9999999',
            'datainicio' => 'required',
            'datafim' => 'required',
            'valor' => 'required|numeric|min:0.1',
            'tipo' => 'required|numeric|min:0|max:1',
        ]);

        $data = $request->only(['codigo', 'limiteuso', 'datainicio', 'datafim', 'valor', 'tipo']);

        $data['datainicio'] = insertDataOracle($data['datainicio']);
        $data['datafim'] = insertDataOracle($data['datafim']);
        $data['codigo'] = strtoupper($data['codigo']);
        $data['ativo'] = (bool)$request->get('ativo', 0);
        $data['notificar'] = (bool)$request->get('notificar', 0);

        return $data;
    }

    private function dataEhValida($data): bool
    {
        return CarbonCustom::parse($data['datainicio']) < CarbonCustom::parse($data['datafim']);
    }
}

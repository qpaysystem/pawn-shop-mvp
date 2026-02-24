<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\LmbUserApiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD клиентов + страница клиента с историей сделок. */
class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();
        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($qry) use ($q) {
                $qry->where('full_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('legal_name', 'like', "%{$q}%")
                    ->orWhere('inn', 'like', "%{$q}%");
            });
        }
        if ($request->filled('blacklist')) {
            $query->where('blacklist_flag', true);
        }
        $clients = $query->orderBy('full_name')->paginate(20);

        return view('clients.index', compact('clients'));
    }

    /** Поиск клиента по телефону/ФИО (для формы приёма товара). */
    public function search(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        $clients = Client::where(function ($query) use ($q) {
            $query->where('full_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%");
        })
            ->limit(20)
            ->get(['id', 'full_name', 'last_name', 'first_name', 'patronymic', 'phone', 'email']);

        return response()->json($clients);
    }

    public function create()
    {
        return view('clients.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_type' => ['required', Rule::in([Client::TYPE_INDIVIDUAL, Client::TYPE_LEGAL])],
            'last_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_INDIVIDUAL), 'nullable', 'string', 'max:100'],
            'first_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_INDIVIDUAL), 'nullable', 'string', 'max:100'],
            'patronymic' => 'nullable|string|max:100',
            'legal_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_LEGAL), 'nullable', 'string', 'max:255'],
            'inn' => 'nullable|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'legal_address' => 'nullable|string|max:500',
            'phone' => 'required|string|max:50|unique:clients,phone',
            'email' => 'nullable|email|max:255',
            'passport_data' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'blacklist_flag' => 'boolean',
        ]);
        $data['full_name'] = $this->buildFullName($data);
        $data['blacklist_flag'] = $request->boolean('blacklist_flag');
        Client::create($data);

        return redirect()->route('clients.index')->with('success', 'Клиент создан.');
    }

    public function show(Client $client)
    {
        $client->load(['pawnContracts.item', 'pawnContracts.store', 'commissionContracts.item', 'commissionContracts.store', 'purchaseContracts.item', 'purchaseContracts.store', 'callCenterContacts.store']);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'client_type' => ['required', Rule::in([Client::TYPE_INDIVIDUAL, Client::TYPE_LEGAL])],
            'last_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_INDIVIDUAL), 'nullable', 'string', 'max:100'],
            'first_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_INDIVIDUAL), 'nullable', 'string', 'max:100'],
            'patronymic' => 'nullable|string|max:100',
            'legal_name' => [Rule::requiredIf($request->input('client_type') === Client::TYPE_LEGAL), 'nullable', 'string', 'max:255'],
            'inn' => 'nullable|string|max:12',
            'kpp' => 'nullable|string|max:9',
            'legal_address' => 'nullable|string|max:500',
            'phone' => 'required|string|max:50|unique:clients,phone,' . $client->id,
            'email' => 'nullable|email|max:255',
            'passport_data' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'blacklist_flag' => 'boolean',
        ]);
        $data['full_name'] = $this->buildFullName($data);
        $data['blacklist_flag'] = $request->boolean('blacklist_flag');
        $client->update($data);

        return redirect()->route('clients.show', $client)->with('success', 'Клиент обновлён.');
    }

    private function buildFullName(array $data): string
    {
        if (($data['client_type'] ?? '') === Client::TYPE_LEGAL && ! empty(trim($data['legal_name'] ?? ''))) {
            return trim($data['legal_name']);
        }
        return trim(implode(' ', array_filter([
            $data['last_name'] ?? '',
            $data['first_name'] ?? '',
            $data['patronymic'] ?? '',
        ])));
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Клиент удалён.');
    }

    /**
     * Загрузить данные контрагента из 1С LMB по телефону клиента и сохранить в карточку (поле «Данные из 1С»).
     */
    public function syncLmb(Client $client, LmbUserApiService $lmbApi)
    {
        $phone = $client->phone;
        if (! $phone) {
            return redirect()->route('clients.show', $client)->with('error', 'У клиента не указан телефон.');
        }

        $data = $lmbApi->getUserByPhone($phone);

        if ($data === null) {
            return redirect()->route('clients.show', $client)->with('error', 'Не удалось получить данные из 1С (сервер недоступен или ошибка). Запустите с сервера, с которого доступен API.');
        }

        if (isset($data['raw'])) {
            return redirect()->route('clients.show', $client)->with('error', '1С вернула ответ в неожиданном формате.');
        }

        $client->update(['lmb_data' => $data]);

        return redirect()->route('clients.show', $client)->with('success', 'Данные из 1С загружены и сохранены в карточку.');
    }
}

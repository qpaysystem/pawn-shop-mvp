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
            \Illuminate\Support\Facades\Log::warning('ClientController::syncLmb получил null от LmbUserApiService — на сервере старая версия кода. Нужен git pull и ./deploy.sh.');
            return redirect()->route('clients.show', $client)->with('error', 'Ошибка 1С: устаревший код. На сервере выполните: cd ~/pawn-shop-mvp && git pull origin main && ./deploy.sh. В .env должно быть: LMB_USER_API_URL=http://5.128.186.3/lmb/hs/es (без :5665). Затем: php artisan config:clear.');
        }

        if (isset($data['error'])) {
            return redirect()->route('clients.show', $client)->with('error', $data['error']);
        }

        if (isset($data['raw'])) {
            return redirect()->route('clients.show', $client)->with('error', '1С вернула ответ в неожиданном формате. Проверьте storage/logs/laravel.log (LmbUserApiService).');
        }

        // Убрать BOM и пробелы в ключах (1С может отдавать с лишними символами)
        $data = $this->cleanLmbDataKeys($data);

        // Ответ 1С может быть обёрнут: {"data": {...}} или {"result": {...}}; внутри — user_uid/ID, first_name/FIO
        foreach (['data', 'result', 'response', 'user'] as $wrapper) {
            if (! isset($data[$wrapper]) || ! is_array($data[$wrapper])) {
                continue;
            }
            $inner = $data[$wrapper];
            $hasId = ! empty($inner['user_uid'] ?? $inner['User_Uid'] ?? $inner['ID'] ?? $inner['id'] ?? null);
            if ($hasId) {
                $data = $inner;
                break;
            }
        }
        if (isset($data['User_Uid']) && empty($data['user_uid'] ?? '')) {
            $data['user_uid'] = $data['User_Uid'];
        }
        if (empty($data['user_uid'] ?? '') && ! empty($data['ID'] ?? $data['id'] ?? '')) {
            $data['user_uid'] = (string) ($data['ID'] ?? $data['id']);
        }
        $data = $this->cleanLmbDataKeys($data);

        if (empty($data) || empty($data['user_uid'] ?? '')) {
            $client->update(['lmb_data' => null]);
            return redirect()->route('clients.show', $client)->with('error', 'В 1С по этому телефону контрагент не найден (пустой ответ).');
        }

        \Illuminate\Support\Facades\Log::info('syncLmb: данные от 1С перед нормализацией', [
            'keys' => array_keys($data),
            'sample' => array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 80) : $v, $data),
        ]);
        $data = $this->normalizeLmbUserData($data);
        if (empty($data['user_uid'])) {
            $client->update(['lmb_data' => null]);
            return redirect()->route('clients.show', $client)->with('error', 'В 1С по этому телефону контрагент не найден (нет кода user_uid в ответе).');
        }
        $client->update(['lmb_data' => $data]);

        return redirect()->route('clients.show', $client)->with('success', 'Данные из 1С загружены и сохранены в карточку.');
    }

    /** Убрать BOM и пробелы в ключах массива от 1С. */
    private function cleanLmbDataKeys(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $k));
            $out[$key] = is_array($v) ? $this->cleanLmbDataKeys($v) : $v;
        }
        return $out;
    }

    /**
     * Привести ключи ответа 1С к единому виду (user_uid, first_name, second_name, last_name, phone).
     * Учитываются английские, PascalCase и русские имена полей.
     */
    private function normalizeLmbUserData(array $data): array
    {
        $map = [
            'user_uid' => ['user_uid', 'User_Uid', 'UserUid', 'userUid', 'USER_UID', 'id', 'ID', 'Id', 'Код', 'code', 'guid'],
            'first_name' => ['first_name', 'First_Name', 'FirstName', 'firstname', 'first', 'ФИО', 'FIO', 'fio', 'Имя', 'name', 'full_name'],
            'second_name' => ['second_name', 'Second_Name', 'SecondName', 'secondname', 'second', 'Имя'],
            'last_name' => ['last_name', 'Last_Name', 'LastName', 'lastname', 'last', 'Отчество'],
            'phone' => ['phone', 'Phone', 'PhoneNumber', 'tel', 'Телефон', 'telephone'],
        ];
        $out = [];
        foreach ($map as $ourKey => $variants) {
            $value = $data[$ourKey] ?? null;
            if ($value === null || $value === '') {
                foreach ($variants as $variantKey) {
                    $found = isset($data[$variantKey]) ? $data[$variantKey] : null;
                    if ($found === null) {
                        foreach (array_keys($data) as $k) {
                            if (strcasecmp($k, $variantKey) === 0 && (string) $data[$k] !== '') {
                                $found = $data[$k];
                                break;
                            }
                        }
                    }
                    if ($found !== null && (string) $found !== '') {
                        $value = $found;
                        break;
                    }
                }
            }
            $out[$ourKey] = $value !== null && $value !== '' ? (string) $value : null;
        }
        return $out;
    }
}

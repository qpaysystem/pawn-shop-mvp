<?php

namespace App\Http\Controllers;

use App\Models\CallCenterContact;
use App\Models\Client;
use App\Services\CallRecordingTranscriptionService;
use App\Services\MtsVpbxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Колл-центр: фиксация обращений, аналитика. */
class CallCenterController extends Controller
{
    public function index(Request $request)
    {
        $storeIds = Auth::user()->allowedStoreIds();
        $query = CallCenterContact::with(['client', 'store', 'createdByUser', 'pawnContract', 'purchaseContract', 'commissionContract']);
        $query->where(function ($q) use ($storeIds) {
            $q->whereIn('store_id', $storeIds)->orWhereNull('store_id');
        });

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('call_status')) {
            if ($request->call_status === 'placed') {
                $query->where(function ($q) {
                    $q->where('call_duration_sec', '>', 1)->orWhere(function ($q2) {
                        $q2->where('call_status', 'placed')->whereNull('call_duration_sec');
                    });
                });
            } else {
                $query->where(function ($q) {
                    $q->where('call_status', 'missed')->orWhere('call_duration_sec', '<=', 1);
                });
            }
        }
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('contact_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('contact_date', '<=', $request->date_to);
        }

        $contacts = $query->orderByDesc('contact_date')->paginate(25)->withQueryString();

        return view('call-center.index', compact('contacts'));
    }

    /** Удалить все обращения по телефону, загруженные из MTS (external_id начинается с mts_). */
    public function clearMtsContacts(Request $request): RedirectResponse
    {
        $deleted = CallCenterContact::where('channel', 'phone')
            ->where('external_id', 'like', 'mts_%')
            ->delete();

        return redirect()->route('call-center.index')->with('success', "Удалено обращений MTS: {$deleted}. При следующей выгрузке добавятся только новые звонки.");
    }

    /** Загрузить звонки из MTS VPBX (по умолчанию за последний день). Добавляются только те, которых ещё нет по external_id. */
    public function syncMtsCalls(Request $request): RedirectResponse
    {
        set_time_limit(300);
        $service = app(MtsVpbxService::class);
        if (! $service->isConfigured()) {
            return redirect()->route('call-center.index')->with('error', 'MTS VPBX не настроен (MTS_VPBX_URL, MTS_VPBX_PASSWORD в .env).');
        }

        $days = (int) $request->input('days', 1);
        $days = max(1, min(90, $days));
        $dateFrom = now()->subDays($days)->startOfDay();
        $dateTo = now();

        $calls = $service->fetchCalls($dateFrom, $dateTo);
        $created = 0;
        $skipped = 0;

        foreach ($calls as $call) {
            $existing = CallCenterContact::where('external_id', $call['external_id'])->first();

            if ($existing) {
                // Обновляем статус и ext_tracking_id у уже загруженных звонков (чтобы отображались в списке и можно было загрузить записи)
                $update = [];
                if (isset($call['call_status'])) {
                    $update['call_status'] = $call['call_status'];
                }
                if (array_key_exists('call_duration_sec', $call)) {
                    $update['call_duration_sec'] = $call['call_duration_sec'];
                }
                if (array_key_exists('ext_tracking_id', $call) && $call['ext_tracking_id'] !== null) {
                    $update['ext_tracking_id'] = $call['ext_tracking_id'];
                }
                if ($update !== []) {
                    $existing->update($update);
                }
                $skipped++;
                continue;
            }

            $clientId = null;
            if (! empty($call['contact_phone'])) {
                $phone = $call['contact_phone'];
                $normalized = preg_replace('/\D/', '', $phone);
                $client = Client::where('phone', $phone)
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') = ?", [$normalized])
                    ->first();
                $clientId = $client?->id;
            }

            CallCenterContact::create([
                'external_id' => $call['external_id'],
                'ext_tracking_id' => $call['ext_tracking_id'] ?? null,
                'client_id' => $clientId,
                'channel' => 'phone',
                'direction' => $call['direction'],
                'call_status' => $call['call_status'] ?? null,
                'call_duration_sec' => $call['call_duration_sec'] ?? null,
                'store_id' => null,
                'contact_date' => $call['contact_date'],
                'contact_phone' => $call['contact_phone'],
                'contact_name' => null,
                'notes' => $call['notes'],
                'outcome' => null,
                'created_by' => Auth::id(),
            ]);
            $created++;
        }

        $message = $created > 0 || $skipped > 0
            ? "Загружено звонков MTS: создано {$created}, пропущено дублей {$skipped}."
            : 'Звонков не получено. Если приходит 403: включите доступ к API в личном кабинете MTS VPBX. Команда: php artisan mts:debug-response';

        return redirect()->route('call-center.index')->with($created > 0 ? 'success' : 'error', $message);
    }

    /** Загрузить записи разговоров за последние 7 дней (звонки с ext_tracking_id, у которых ещё нет recording_path). */
    public function syncMtsRecordings(Request $request): RedirectResponse
    {
        set_time_limit(300);
        $service = app(MtsVpbxService::class);
        if (! $service->isConfigured()) {
            return redirect()->route('call-center.index')->with('error', 'MTS VPBX не настроен.');
        }

        $dateFrom = now()->subDays(7)->startOfDay();
        $dateTo = now();
        $contacts = CallCenterContact::where('channel', 'phone')
            ->whereNotNull('ext_tracking_id')
            ->where('ext_tracking_id', '!=', '')
            ->whereNull('recording_path')
            ->whereBetween('contact_date', [$dateFrom, $dateTo])
            ->get();

        if ($contacts->isEmpty()) {
            return redirect()->route('call-center.index')->with('info', 'Нет звонков с ID записи MTS за последние 7 дней. Сначала нажмите «Загрузить звонки с MTS» (за 7 или 30 дней) — тогда подтянутся статусы и ID записей.');
        }

        $downloaded = 0;
        $failed = 0;
        foreach ($contacts as $contact) {
            $path = $service->downloadRecording($contact->ext_tracking_id);
            if ($path !== null) {
                $contact->update(['recording_path' => $path]);
                $downloaded++;
            } else {
                $failed++;
            }
        }

        $message = "Записи разговоров: загружено {$downloaded}" . ($failed > 0 ? ", не удалось загрузить {$failed} (MTS может отдавать 404/429 для старых записей)." : '.');
        return redirect()->route('call-center.index')->with($downloaded > 0 ? 'success' : 'info', $message);
    }

    /** Скачать/воспроизвести запись разговора (файл из storage). ?download=1 — скачать, иначе отдать для воспроизведения. */
    public function recording(CallCenterContact $callCenterContact): \Illuminate\Http\Response|RedirectResponse
    {
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if (empty($callCenterContact->recording_path)) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Запись разговора не загружена.');
        }
        $path = $callCenterContact->recording_path;
        if (! Storage::disk('local')->exists($path)) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Файл записи не найден.');
        }
        $fullPath = Storage::disk('local')->path($path);
        $filename = basename($path);

        if (request()->boolean('download')) {
            return Storage::disk('local')->download($path, $filename, ['Content-Type' => 'audio/mpeg']);
        }
        // Для воспроизведения в <audio> отдаём файл с inline (браузер не скачивает, а играет)
        return response()->file($fullPath, ['Content-Type' => 'audio/mpeg']);
    }

    /** Транскрибировать запись разговора (Whisper + DeepSeek) и сохранить текст в событии. */
    public function transcribeRecording(CallCenterContact $callCenterContact): RedirectResponse
    {
        set_time_limit(180);
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if ($callCenterContact->channel !== 'phone') {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Транскрипция доступна только для звонков.');
        }

        $audioPath = null;
        $tempPath = null;

        if (! empty($callCenterContact->recording_path) && Storage::disk('local')->exists($callCenterContact->recording_path)) {
            $audioPath = Storage::disk('local')->path($callCenterContact->recording_path);
        } elseif (! empty($callCenterContact->ext_tracking_id) || ($callCenterContact->external_id && str_starts_with($callCenterContact->external_id, 'mts_'))) {
            $mts = app(MtsVpbxService::class);
            if (! $mts->isConfigured()) {
                return redirect()->route('call-center.show', $callCenterContact)->with('error', 'MTS VPBX не настроен — нельзя загрузить запись для транскрипции.');
            }
            $trackingId = $callCenterContact->ext_tracking_id ?: substr($callCenterContact->external_id, 4);
            $content = $mts->fetchRecordingContent($trackingId);
            if ($content === null) {
                return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Не удалось загрузить запись с MTS.');
            }
            $tempPath = storage_path('app/temp_rec_' . $callCenterContact->id . '_' . time() . '.mp3');
            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }
            if (file_put_contents($tempPath, $content) === false) {
                return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Не удалось сохранить запись во временный файл.');
            }
            $audioPath = $tempPath;
        }

        if ($audioPath === null || ! is_readable($audioPath)) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Нет доступной записи для транскрипции. Загрузите запись или получите её с MTS.');
        }

        try {
            $service = app(CallRecordingTranscriptionService::class);
            $transcript = $service->transcribeAndFormat($audioPath);
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        if ($transcript === null || trim($transcript) === '') {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Не удалось получить текст (проверьте OPENAI_API_KEY для Whisper и при необходимости DEEPSEEK_API_KEY в .env).');
        }

        $callCenterContact->update(['recording_transcript' => $transcript]);

        return redirect()->route('call-center.show', $callCenterContact)->with('success', 'Расшифровка сохранена.');
    }

    /** Воспроизвести/скачать запись с MTS по запросу (по ext_tracking_id или по ID из external_id, например mts_21289855:1). */
    public function recordingFromMts(CallCenterContact $callCenterContact): \Illuminate\Http\Response|RedirectResponse
    {
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        if ($callCenterContact->channel !== 'phone' || ! $callCenterContact->external_id || ! str_starts_with($callCenterContact->external_id, 'mts_')) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Этот звонок не из MTS.');
        }

        $trackingId = $callCenterContact->ext_tracking_id;
        if (empty($trackingId)) {
            $trackingId = substr($callCenterContact->external_id, 4);
        }
        if (empty($trackingId)) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Нет идентификатора записи для запроса к MTS.');
        }

        $service = app(MtsVpbxService::class);
        if (! $service->isConfigured()) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'MTS VPBX не настроен.');
        }

        $content = $service->fetchRecordingContent($trackingId);
        if ($content === null) {
            return redirect()->route('call-center.show', $callCenterContact)->with('error', 'Запись не получена из MTS (404 или недоступна). Можно открыть историю вызовов в личном кабинете MTS.');
        }

        $filename = 'recording-' . preg_replace('/[^a-zA-Z0-9_\-.:]/', '_', $trackingId) . '.mp3';
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        ]);
    }

    public function create(Request $request)
    {
        $stores = \App\Models\Store::whereIn('id', Auth::user()->allowedStoreIds())->where('is_active', true)->orderBy('name')->get();
        $presetClient = $request->client_id ? Client::find($request->client_id) : null;

        return view('call-center.create', compact('stores', 'presetClient'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'channel' => 'required|in:phone,telegram,whatsapp,vk,visit,other',
            'direction' => 'required|in:incoming,outgoing',
            'store_id' => 'nullable|exists:stores,id',
            'contact_date' => 'required|date',
            'contact_time' => 'nullable|string|max:10',
            'contact_phone' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'outcome' => 'nullable|in:new,callback,visit_scheduled,converted_pawn,converted_purchase,converted_commission,closed',
        ]);

        $storeId = $data['store_id'] ? (int) $data['store_id'] : null;
        if ($storeId && ! in_array($storeId, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $contactDate = $data['contact_date'];
        if (! empty($request->contact_time) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($request->contact_time), $m)) {
            $contactDate .= ' ' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
        } else {
            $contactDate .= ' ' . date('H:i:s');
        }

        CallCenterContact::create([
            'client_id' => $data['client_id'] ? (int) $data['client_id'] : null,
            'channel' => $data['channel'],
            'direction' => $data['direction'],
            'store_id' => $storeId,
            'contact_date' => $contactDate,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('call-center.index')->with('success', 'Обращение зафиксировано.');
    }

    public function show(CallCenterContact $callCenterContact)
    {
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $callCenterContact->load(['client', 'store', 'createdByUser', 'pawnContract', 'purchaseContract', 'commissionContract']);

        return view('call-center.show', compact('callCenterContact'));
    }

    public function edit(CallCenterContact $callCenterContact)
    {
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }
        $storeIds = Auth::user()->allowedStoreIds();
        $stores = \App\Models\Store::whereIn('id', $storeIds)->where('is_active', true)->orderBy('name')->get();
        $pawnContracts = \App\Models\PawnContract::with('client')->whereIn('store_id', $storeIds)->orderByDesc('id')->limit(50)->get();
        $purchaseContracts = \App\Models\PurchaseContract::with('client')->whereIn('store_id', $storeIds)->orderByDesc('id')->limit(50)->get();
        $commissionContracts = \App\Models\CommissionContract::with('client')->whereIn('store_id', $storeIds)->orderByDesc('id')->limit(50)->get();

        return view('call-center.edit', compact('callCenterContact', 'stores', 'pawnContracts', 'purchaseContracts', 'commissionContracts'));
    }

    public function update(Request $request, CallCenterContact $callCenterContact)
    {
        if ($callCenterContact->store_id && ! in_array($callCenterContact->store_id, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $data = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'channel' => 'required|in:phone,telegram,whatsapp,vk,visit,other',
            'direction' => 'required|in:incoming,outgoing',
            'store_id' => 'nullable|exists:stores,id',
            'contact_date' => 'required|date',
            'contact_time' => 'nullable|string|max:10',
            'contact_phone' => 'nullable|string|max:50',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'outcome' => 'nullable|in:new,callback,visit_scheduled,converted_pawn,converted_purchase,converted_commission,closed',
            'pawn_contract_id' => 'nullable|exists:pawn_contracts,id',
            'purchase_contract_id' => 'nullable|exists:purchase_contracts,id',
            'commission_contract_id' => 'nullable|exists:commission_contracts,id',
        ]);

        $storeId = $data['store_id'] ? (int) $data['store_id'] : null;
        if ($storeId && ! in_array($storeId, Auth::user()->allowedStoreIds(), true)) {
            abort(403);
        }

        $contactDate = $data['contact_date'];
        if (! empty($request->contact_time) && preg_match('/^(\d{1,2}):(\d{2})$/', trim($request->contact_time), $m)) {
            $contactDate .= ' ' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
        } else {
            $contactDate .= ' ' . \Carbon\Carbon::parse($callCenterContact->contact_date)->format('H:i:s');
        }

        $callCenterContact->update([
            'client_id' => $data['client_id'] ? (int) $data['client_id'] : null,
            'channel' => $data['channel'],
            'direction' => $data['direction'],
            'store_id' => $storeId,
            'contact_date' => $contactDate,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'pawn_contract_id' => $data['pawn_contract_id'] ?? null,
            'purchase_contract_id' => $data['purchase_contract_id'] ?? null,
            'commission_contract_id' => $data['commission_contract_id'] ?? null,
        ]);

        return redirect()->route('call-center.index')->with('success', 'Обращение обновлено.');
    }

    public function analytics(Request $request)
    {
        $storeIds = Auth::user()->allowedStoreIds();
        $dateFrom = $request->get('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', now()->format('Y-m-d'));

        $baseQuery = CallCenterContact::whereBetween('contact_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->where(function ($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds)->orWhereNull('store_id');
            });

        $totalContacts = (clone $baseQuery)->count();

        $byChannel = (clone $baseQuery)->select('channel', DB::raw('count(*) as cnt'))
            ->groupBy('channel')
            ->pluck('cnt', 'channel')
            ->all();

        $byOutcome = (clone $baseQuery)->select('outcome', DB::raw('count(*) as cnt'))
            ->whereNotNull('outcome')
            ->groupBy('outcome')
            ->pluck('cnt', 'outcome')
            ->all();

        $convertedPawn = (clone $baseQuery)->where('outcome', 'converted_pawn')->count();
        $convertedPurchase = (clone $baseQuery)->where('outcome', 'converted_purchase')->count();
        $convertedCommission = (clone $baseQuery)->where('outcome', 'converted_commission')->count();
        $totalDeals = $convertedPawn + $convertedPurchase + $convertedCommission;

        $conversionRate = $totalContacts > 0 ? round($totalDeals / $totalContacts * 100, 1) : 0;

        $phoneQuery = (clone $baseQuery)->where('channel', 'phone');
        $callsAccepted = (clone $phoneQuery)->where(function ($q) {
            $q->where('call_duration_sec', '>', 1)
                ->orWhere(function ($q2) {
                    $q2->where('call_status', 'placed')->whereNull('call_duration_sec');
                });
        })->count();
        $callsMissed = (clone $phoneQuery)->where(function ($q) {
            $q->where('call_status', 'missed')->orWhere('call_duration_sec', '<=', 1);
        })->count();

        return view('call-center.analytics', compact(
            'totalContacts', 'byChannel', 'byOutcome',
            'convertedPawn', 'convertedPurchase', 'convertedCommission', 'totalDeals',
            'conversionRate', 'dateFrom', 'dateTo', 'callsAccepted', 'callsMissed'
        ));
    }
}

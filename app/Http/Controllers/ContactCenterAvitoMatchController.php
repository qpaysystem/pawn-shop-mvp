<?php

namespace App\Http\Controllers;

use App\Models\ItemStatus;
use App\Models\Store;
use App\Services\ContactCenter\AvitoActiveAdsMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Сопоставление активных объявлений Avito с витриной по файлу выгрузки. */
class ContactCenterAvitoMatchController extends Controller
{
    public function index(Request $request): View
    {
        $stores = Store::query()->where('is_active', true)->orderBy('name')->get();
        $statuses = ItemStatus::query()->orderBy('name')->get();

        $defaultStoreId = $stores
            ->first(fn ($s) => str_contains(mb_strtolower((string) $s->name), 'колхид') && str_contains((string) $s->name, '11'))
            ?->id;

        $defaultStatusId = $statuses
            ->first(fn ($st) => str_contains(mb_strtolower((string) $st->name), 'товар'))
            ?->id ?? $statuses->first()?->id;

        return view('contact-center.avito-match.index', [
            'stores' => $stores,
            'statuses' => $statuses,
            'defaultStoreId' => $defaultStoreId,
            'defaultStatusId' => $defaultStatusId,
            'results' => null,
        ]);
    }

    public function upload(Request $request, AvitoActiveAdsMatcher $matcher): View|RedirectResponse
    {
        $user = Auth::user();
        if (! $user->canAccessContactCenter()) {
            abort(403);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'status_id' => ['required', 'integer', 'exists:item_statuses,id'],
            'source' => ['nullable', 'in:file,api'],
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv,txt'],
        ], [
            'file.mimes' => 'Загрузите xlsx/xls/csv выгрузку из Avito.',
        ]);

        $storeId = (int) $validated['store_id'];
        $statusId = (int) $validated['status_id'];
        if (! in_array($storeId, $user->allowedStoreIds(), true)) {
            abort(403);
        }

        $source = (string) ($validated['source'] ?? 'file');
        if ($source === 'api') {
            $results = $matcher->matchFromApi($storeId, $statusId);
        } else {
            $file = $request->file('file');
            if (! $file) {
                return back()->with('error', 'Файл не получен.');
            }
            $results = $matcher->match($file, $storeId, $statusId);
        }
        if (! ($results['ok'] ?? false)) {
            return redirect()
                ->route('contact-center.avito-match.index')
                ->with('error', $results['error'] ?? 'Не удалось сопоставить файл.');
        }

        $stores = Store::query()->where('is_active', true)->orderBy('name')->get();
        $statuses = ItemStatus::query()->orderBy('name')->get();

        return view('contact-center.avito-match.index', [
            'stores' => $stores,
            'statuses' => $statuses,
            'defaultStoreId' => $storeId,
            'defaultStatusId' => $statusId,
            'results' => $results,
        ]);
    }
}


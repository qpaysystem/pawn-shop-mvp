<?php

namespace App\Http\Controllers;

use App\Models\PawnContract;
use App\Models\CommissionContract;
use Illuminate\Support\Facades\Auth;

/**
 * Профиль пользователя (оценщика): договоры займа и комиссии, где пользователь — приёмщик.
 */
class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $pawnContracts = PawnContract::where('appraiser_id', $user->id)
            ->with(['client', 'item', 'store'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'pawn_page');
        $commissionContracts = CommissionContract::where('appraiser_id', $user->id)
            ->with(['client', 'item', 'store'])
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'commission_page');

        return view('profile.show', compact('user', 'pawnContracts', 'commissionContracts'));
    }
}

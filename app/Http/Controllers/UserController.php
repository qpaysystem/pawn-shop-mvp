<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** CRUD пользователей (super-admin). */
class UserController extends Controller
{
    public function index()
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        $users = User::with('store')->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        $stores = Store::orderBy('name')->get();

        return view('users.create', compact('stores'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:super-admin,manager,appraiser,cashier,storekeeper',
            'store_id' => 'nullable|required_if:role,manager,appraiser,cashier,storekeeper|exists:stores,id',
        ]);
        $data['password'] = Hash::make($data['password']);
        if ($data['role'] === 'super-admin') {
            $data['store_id'] = null;
        } else {
            $data['store_id'] = $data['store_id'] ?? null;
        }
        User::create($data);

        return redirect()->route('users.index')->with('success', 'Пользователь создан.');
    }

    public function edit(User $user)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        $stores = Store::orderBy('name')->get();

        return view('users.edit', compact('user', 'stores'));
    }

    public function update(Request $request, User $user)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|in:super-admin,manager,appraiser,cashier,storekeeper',
            'store_id' => 'nullable|required_if:role,manager,appraiser,cashier,storekeeper|exists:stores,id',
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if ($data['role'] === 'super-admin') {
            $data['store_id'] = null;
        } else {
            $data['store_id'] = $data['store_id'] ?? null;
        }
        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Пользователь обновлён.');
    }

    public function destroy(User $user)
    {
        if (! auth()->user()->isSuperAdmin()) {
            abort(403);
        }
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Нельзя удалить себя.');
        }
        $user->delete();

        return redirect()->route('users.index')->with('success', 'Пользователь удалён.');
    }
}

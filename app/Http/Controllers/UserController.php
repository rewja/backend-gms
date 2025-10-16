<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserController extends Controller
{
    // GA: list all users
    public function index()
    {
        return response()->json(User::all());
    }

    // GA: show specific user
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // GA: create user
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'password' => ['required', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'role' => 'required|in:user,admin_ga,admin_ga_manager,super_admin,procurement',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);

        // Only privileged roles can assign elevated roles
        $currentUser = $request->user();
        $elevatedRoles = ['admin_ga', 'admin_ga_manager', 'super_admin'];
        if (in_array($data['role'] ?? 'user', $elevatedRoles, true)) {
            if (!$currentUser || !in_array($currentUser->role, $elevatedRoles, true)) {
                return response()->json(['message' => 'Insufficient permissions to assign elevated role'], 403);
            }
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);


        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    // GA: update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => ['nullable', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
            'role' => 'sometimes|in:user,admin_ga,admin_ga_manager,super_admin,procurement',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);
        
        // Prevent self role changes and restrict role updates to privileged roles
        if (array_key_exists('role', $data)) {
            $currentUser = $request->user();
            $elevatedRoles = ['admin_ga', 'admin_ga_manager', 'super_admin'];
            if (!$currentUser) {
                unset($data['role']);
            } else {
                if ($currentUser->id === $user->id) {
                    return response()->json(['message' => 'Cannot change own role'], 403);
                }
                if (!in_array($currentUser->role, $elevatedRoles, true)) {
                    return response()->json(['message' => 'Insufficient permissions to change role'], 403);
                }
            }
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);


        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    // GA: delete user
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        
        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // User: get own profile
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // Admin: new users per month/year
    public function stats()
    {
        $driver = \DB::connection()->getDriverName();
        $monthExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y-%m")' : 'strftime("%Y-%m", created_at)';
        $yearExpr = $driver === 'mysql' ? 'DATE_FORMAT(created_at, "%Y")' : 'strftime("%Y", created_at)';

        $monthly = \DB::table('users')
            ->selectRaw("{$monthExpr} as ym, COUNT(*) as total")
            ->groupByRaw($monthExpr)
            ->orderByRaw('ym DESC')
            ->limit(12)
            ->get();

        $yearly = \DB::table('users')
            ->selectRaw("{$yearExpr} as y, COUNT(*) as total")
            ->groupByRaw($yearExpr)
            ->orderByRaw('y DESC')
            ->limit(5)
            ->get();

        return response()->json([
            'monthly' => $monthly,
            'yearly' => $yearly,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
            'password' => 'required|string|min:6',
            'role' => 'required|in:user,admin_ga,admin_ga_manager',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        // Log user creation activity
        ActivityService::logCreate(
            $user,
            $request->user()->id,
            $request
        );

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    // GA: update user
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|in:user,admin_ga,admin_ga_manager',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'category' => 'nullable|in:ob,driver,security,magang_pkl',
        ]);

        // Store old values for logging
        $oldValues = $user->toArray();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        // Log user update activity
        ActivityService::logUpdate(
            $user,
            $request->user()->id,
            $oldValues,
            $request
        );

        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    // GA: delete user
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Get current user from request (we need to access it differently)
        $currentUser = request()->user();
        
        // Log user deletion activity before deleting
        ActivityService::logDelete(
            $user,
            $currentUser->id,
            request()
        );
        
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

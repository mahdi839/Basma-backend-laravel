<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    private function userHasAdminAccess(User $user): bool
    {
        return $user->hasAnyRole(['super-admin', 'admin'])
            || $user->getAllPermissions()->isNotEmpty();
    }

    private function authResponse(User $user, string $message)
    {
        $token = $user->createToken('api token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => $message,
            'token' => $token,
            'token_type' => 'bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }

    private function validateLoginRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
    }

    private function attemptLogin(Request $request): array
    {
        $validateUser = $this->validateLoginRequest($request);

        if ($validateUser->fails()) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors(),
                ], 422),
            ];
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return [
                'response' => response()->json([
                    'status' => false,
                    'message' => "Email or password doesn't exist",
                ], 401),
            ];
        }

        return ['user' => $user];
    }

    public function signUp(Request $request)
    {
        $validateUser = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
            ]
        );

        if ($validateUser->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'errors' => $validateUser->errors(),
            ], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('user');

        return $this->authResponse($user, 'Sign Up successfully');
    }

    public function logIn(Request $request)
    {
        $login = $this->attemptLogin($request);

        if (isset($login['response'])) {
            return $login['response'];
        }

        $user = $login['user'];

        if ($this->userHasAdminAccess($user)) {
            return response()->json([
                'status' => false,
                'message' => 'Please use the admin login page.',
            ], 403);
        }

        return $this->authResponse($user, 'Logged In successfully');
    }

    public function adminLogIn(Request $request)
    {
        $login = $this->attemptLogin($request);

        if (isset($login['response'])) {
            return $login['response'];
        }

        $user = $login['user'];

        if (!$this->userHasAdminAccess($user)) {
            return response()->json([
                'status' => false,
                'message' => 'Access denied. This account cannot access admin.',
            ], 403);
        }

        return $this->authResponse($user, 'Admin logged in successfully');
    }

    public function logOut(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged Out successfully',
        ], 200);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]
        ], 200);
    }
}

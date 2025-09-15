<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function signUp(Request $request)
    {
        $validateUser = Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'email' => 'required|email|unique:users,email',
                'password' => 'required',
            ]
        );

        if ($validateUser->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'validation error',
                'error' => $validateUser->errors()->all(),
            ], 401);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Sign Up successfully',
            'user' => $user,
        ], 200);

    }

    public function logIn(Request $request)
    {

        $validateUser = Validator::make(
            $request->all(),
            [

                'email' => 'required|email',
                'password' => 'required',
            ]
        );
        if ($validateUser->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'authentication error',
                'error' => $validateUser->errors()->all(),
            ], 401);
        }

        if (! $user = User::where('email', $request->email)->first()) {
            return response()->json([
                'status' => false,
                'message' => "email or password doesn't exist",
            ], 401);
        }

        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => "email or password doesn't exist",
            ], 401);
        }

        // Create API token
        $token = $user->createToken('api token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Logged in successfully',
            'token' => $token,
            'token_type' => 'bearer',
        ], 200);

    }

    public function logOut(Request $request)
    {

        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Logged Out successfully',
            'user' => $user,
        ], 200);
    }
}

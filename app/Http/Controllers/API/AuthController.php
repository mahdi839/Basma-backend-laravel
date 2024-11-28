<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function signUp (Request $request){
      $validateUser = Validator::make(
        $request->all(),
              [
                'name'=> 'required',
                'email'=> 'required|email|unique:users,email',
                'password'=> 'required'
              ]
      );

          if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => "validation error",
                'error'=> $validateUser->errors()->all()
            ],401);
          }

          $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
          ]);
          return response()->json([
            'status' => true,
            'message' => "Sign Up successfully",
            'user'=> $user
        ],200);

    }

    public function logIn (Request $request){

        $validateUser = Validator::make(
            $request->all(),
                  [

                    'email'=> 'required|email',
                    'password'=> 'required'
                  ]
          );
          if($validateUser->fails()){
            return response()->json([
                'status' => false,
                'message' => "authentication error",
                'error'=> $validateUser->errors()->all()
            ],401);
          }

          if(Auth::attempt(['email'=>$request->email,'password'=>$request->password])){
            $authUser = Auth::user();
            return response()->json([
                'status' => true,
                'message' => "Logged In successfully",
                'token'=>$authUser->createToken('api token')->plainTextToken,
                'token_type'=> 'bearer'
            ],200);
          }else{
            return response()->json([
                'status' => false,
                'message' => "email or password doesn't exist",
                'error'=> $validateUser->errors()->all()
            ],401);
          }

    }
    public function logOut (Request $request){

        $user = $request->user();
        $user->tokens()->delete();
        return response()->json([
            'status' => true,
            'message' => "Logged Out successfully",
            'user'=> $user
        ],200);
    }
}

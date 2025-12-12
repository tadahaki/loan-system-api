<?php

namespace App\Http\Controllers;

use App\Models\UserBorrow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BorrowController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstName' => 'required|string',
            'lastName' => 'required|string',
            'gender' => 'required|string',
            'age' => 'required|integer',
            'email' => 'required|email|unique:userborrows',
            'address' => 'required|string',
            'username' => 'required|string|unique:userborrows',
            'password' => 'required|string|min:6',
        ]);

        $user = UserBorrow::create([
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'gender' => $request->gender,
            'age' => $request->age,
            'email' => $request->email,
            'address' => $request->address,
            'username' => $request->username,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = UserBorrow::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        return response()->json([
            'message' => 'Login successful',
            'user' => $user
        ]);
    }
}

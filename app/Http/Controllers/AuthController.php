<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Producer;
use App\Models\Buyer;
use App\Models\Admin;
use App\Models\RegisteredProducer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'contact_number' => 'required|string|max:20',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
            'user_type' => 'required|in:producer,buyer,admin',
            'rsbsa_number' => 'nullable|string|max:255', // Only for producers
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if email already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json(['errors' => ['email' => ['The email has already been taken.']]], 422);
        }

        try {
            // Create user
            $user = User::create([
                'email' => $request->email,
                'password_hash' => Hash::make($request->password),
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'contact_number' => $request->contact_number,
                'user_type' => $request->user_type === 'producer' ? 'Farmer' : ucfirst($request->user_type),
            ]);

            // Get the created user with correct user_id
            $user = User::where('email', $request->email)->first();

            $verificationStatus = 'Pending';

            if ($request->user_type === 'producer') {
                $registered = null;
                // Check RSBSA number against registered_producers
                if ($request->rsbsa_number) {
                    // Trim whitespace and search by exact match
                    $rsbsaNumber = trim($request->rsbsa_number);
                    
                    try {
                        $registered = RegisteredProducer::where('rsbsa_number', $rsbsaNumber)->first();
                        
                        if ($registered) {
                            $verificationStatus = 'Verified';
                        } else {
                            // Log the search attempt for debugging
                            \Log::warning('RSBSA lookup failed', [
                                'rsbsa_number' => $rsbsaNumber,
                                'user_email' => $request->email,
                                'search_performed' => true
                            ]);
                            // Delete the user since registration failed
                            $user->delete();
                            return response()->json([
                                'errors' => ['rsbsa_number' => ['RSBSA number not found in registry. Please verify the number or register without it for manual verification.']]
                            ], 422);
                        }
                    } catch (\Exception $e) {
                        \Log::error('RSBSA lookup error', [
                            'error' => $e->getMessage(),
                            'rsbsa_number' => $rsbsaNumber
                        ]);
                        // Delete the user since RSBSA check failed
                        $user->delete();
                        return response()->json([
                            'errors' => ['rsbsa_number' => ['Error validating RSBSA number. Please try again.']]
                        ], 500);
                    }
                }

                Producer::create([
                    'user_id' => $user->user_id,
                    'rsbsa_number' => $request->rsbsa_number ? trim($request->rsbsa_number) : null,
                    'verification_status' => $verificationStatus,
                    'producer_type' => $registered ? $registered->producer_type : $request->producer_type,
                ]);
            } elseif ($request->user_type === 'buyer') {
                Buyer::create([
                    'user_id' => $user->user_id,
                    'organization_name' => $request->first_name . ' ' . $request->last_name,
                    'buyer_type' => 'Institution', // Default
                    'contact_person' => $request->first_name . ' ' . $request->last_name,
                ]);
            } elseif ($request->user_type === 'admin') {
                Admin::create([
                    'user_id' => $user->user_id,
                    'role' => 'Administrator',
                    'permission_level' => 'Full',
                ]);
            }

            // Generate token for auto-login on verified registration
            $token = null;
            $userData = [
                'user_id' => $user->user_id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'user_type' => $user->user_type,
                'verification_status' => $verificationStatus,
            ];

            if ($request->user_type === 'producer') {
                $producer = Producer::where('user_id', $user->user_id)->first();
                if ($producer) {
                    $userData['producer_id'] = $producer->producer_id;
                    $userData['producer_type'] = $producer->producer_type;
                    $userData['rsbsa_number'] = $producer->rsbsa_number;
                }
                // Auto-login if verified
                if ($verificationStatus === 'Verified') {
                    $token = $user->createToken('auth:sanctum')->plainTextToken;
                }
            }

            return response()->json([
                'message' => 'User registered successfully',
                'verification_status' => $verificationStatus,
                'token' => $token,
                'user' => $userData
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Registration error', [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);
            return response()->json([
                'message' => 'Registration failed. ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth:sanctum')->plainTextToken;

        // Get user role details
        $userData = [
            'user_id' => $user->user_id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'user_type' => $user->user_type,
        ];

        // Get role-specific information
        if ($user->user_type === 'Farmer') {
            $producer = Producer::where('user_id', $user->user_id)->first();
            if ($producer) {
                $userData['producer_id'] = $producer->producer_id;
                $userData['verification_status'] = $producer->verification_status;
                $userData['rsbsa_number'] = $producer->rsbsa_number;
                $userData['producer_type'] = $producer->producer_type;
            }
        } elseif ($user->user_type === 'Buyer') {
            $buyer = \App\Models\Buyer::where('user_id', $user->user_id)->first();
            if ($buyer) {
                $userData['buyer_id'] = $buyer->buyer_id;
                $userData['buyer_type'] = $buyer->buyer_type;
            }
        } elseif ($user->user_type === 'Admin') {
            $admin = Admin::where('user_id', $user->user_id)->first();
            if ($admin) {
                $userData['admin_id'] = $admin->admin_id;
                $userData['role'] = $admin->role;
            }
        }

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $userData
        ], 200);
    }
}
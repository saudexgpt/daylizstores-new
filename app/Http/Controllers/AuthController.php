<?php

/**
 * File AuthController.php
 *
 * @author Tuan Duong <bacduong@gmail.com>
 * @package Laravue
 * @version 1.0
 */

namespace App\Http\Controllers;

use App\Laravue\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\UserResource;
use App\Laravue\Models\User;
use App\Mail\ResetPassword;
use App\Models\UserPassword;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Class AuthController
 *
 * @package App\Http\Controllers
 */
class AuthController extends Controller
{
    protected $username;
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('guest')->except('logout');
        $this->username = $this->findUsername();
    }
    public function findUsername()
    {
        $login = request()->input('email');

        $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        request()->merge([$fieldType => $login]);

        // $user = User::where('phone', $login)->first();

        // if ($user) {
        //     $fieldType =  'phone1';

        //     request()->merge([$fieldType => $login]);
        // } else {
        //     $fieldType = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        //     request()->merge([$fieldType => $login]);
        // }


        return $fieldType;
    }
    public function username()
    {
        return $this->username;
    }
    public function login(Request $request)
    {
        $credentials = $request->only($this->username(), 'password');
        if (!Auth::attempt($credentials)) {
            // 'message' is what the shared axios interceptor shows the user; without it they
            // saw axios's generic "Request failed with status code 401".
            return response()->json(
                ['success' => false, 'data' => [], 'error' => 'login_error', 'message' => 'Incorrect email or password.'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $user = $request->user();
        $token = $user->createToken('laravue');
        $user_resource = new UserResource($user);
        // $title = "Log in action";
        // $description = $user->name . ' logged in to the portal';
        // $this->logUserActivity($title, $description);

        return response()->json($user_resource, Response::HTTP_OK)->header('Authorization', $token->plainTextToken);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $title = "Log out action";
        $description = $user->name . ' logged out of the portal';
        $this->logUserActivity($title, $description);
        $request->user()->tokens()->delete();
        return response()->json((new JsonResponse())->success([]), Response::HTTP_OK);
    }

    public function user()
    {
        return new UserResource(Auth::user());
    }
    public function recoverPassword(Request $request)
    {

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $token = randomPassword();
            DB::table('password_resets')->updateOrInsert(
                ['email' => $user->email, 'token' => $token]
            );

            // SendQueuedPasswordResetEmailJob::dispatch($user, $token);
            Mail::to($user)->send(new ResetPassword($user, $token));
            return response()->json(['message' => 'A password reset token has been sent to your email'], 200);
        }

        return response()->json(['message' => 'Email Not Found'], 500);
    }
    public function confirmPasswordResetToken(Request $request)
    {
        $user_token = DB::table('password_resets')->where('token', $request->token)->first();
        if ($user_token) {
            return response()->json(['email' => $user_token->email], 200);
        }
        return response()->json(['message' => 'Invalid Reset Link'], 500);
    }
    public function resetPassword(Request $request)
    {

        $user = User::where('email', $request->email)->first();
        if ($user) {

            $user->password = $request->password;
            $user->password_status = 'custom';
            if ($user->save()) {
                DB::table('password_resets')->where('email', $request->email)->delete();
            }
        }

        return response()->json(['message' => 'Password Reset Successful'], 200);
    }
}

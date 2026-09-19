<?php

/**
 * File UserController.php
 *
 * @author Tuan Duong <bacduong@gmail.com>
 * @package Laravue
 * @version 1.0
 */

namespace App\Http\Controllers;

use App\Driver;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UserResource;
use App\Laravue\JsonResponse;
use App\Laravue\Models\Permission;
use App\Laravue\Models\Role;
use App\Laravue\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Validator;

/**
 * Class UserController
 *
 * @package App\Http\Controllers
 */
class UserController extends Controller
{
    const ITEM_PER_PAGE = 10;

    /**
     * Display a listing of the user resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|ResourceCollection
     */
    public function userNotifications()
    {
        // Only ever the signed-in user's own unread notifications. (This used to
        // return nothing at all, so the admin layout stored `undefined` and crashed.)
        $notifications = Auth::user()->unreadNotifications()->orderBy('created_at', 'DESC')->limit(50)->get();
        return response()->json(compact('notifications'), 200);
    }
    public function index(Request $request)
    {
        $searchParams = $request->all();
        $userQuery = User::query();
        // Bounded: an unbounded client-supplied page size is an easy way to pull the whole table.
        $limit = max(1, min((int) Arr::get($searchParams, 'limit', static::ITEM_PER_PAGE), 100));
        $role = Arr::get($searchParams, 'role', '');
        $keyword = Arr::get($searchParams, 'keyword', '');

        if ($request->role === 'customer') {
            // the customer list shows how many orders each one has placed
            $userQuery->where('role', 'customer')->withCount('orders')->orderBy('id', 'DESC');
        } else {
            $userQuery->where('role', '!=', 'customer');
        }
        if (!empty($keyword)) {
            $userQuery->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('email', 'LIKE', '%' . $keyword . '%');
                $q->orWhere('phone', 'LIKE', '%' . $keyword . '%');
                // $q->orWhere('address', 'LIKE', '%' . $keyword . '%');
                // $q->orWhereIn('id', function ($query) use ($keyword) {
                //     $query->select('user_id')->from('customers');
                // });
            });
        }
        // $userQuery->where('user_type', '!=', 'developer');

        return UserResource::collection($userQuery->paginate($limit));
    }
    public function addDriver(Request $request)
    {
        try {
            $new_user = $this->store($request); //save customer's user details

            $driver = new Driver();
            $driver->user_id = $new_user->id;
            $driver->employee_no = $request->employee_no;
            $driver->license_no = $request->license_no;
            $driver->license_issue_date = date('Y-m-d H:i:s', strtotime($request->license_issue_date));
            $driver->license_expiry_date = date('Y-m-d H:i:s', strtotime($request->license_expiry_date));
            $driver->emergency_contact_details = $request->emergency_contact_details;
            $driver->save();
            $driver->user = $driver->user;

            // log this activity
            $user = $this->getUser();
            $title = "New driver added";
            $description = ucwords($new_user->name) . " was added as new driver by $user->name ($user->email)";
            $roles = ['assistant admin', 'warehouse manager'];
            $this->logUserActivity($title, $description, $roles);
            return response()->json(compact('driver'), 200);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'Check your form for duplicate entries like email'], 500);
        }
    }
    public function updateDriver(Request $request, Driver $driver)
    {
        $user = User::where('email', $request->email)->first();
        $this->update($request, $user);
        $driver->employee_no = $request->employee_no;
        $driver->license_no = $request->license_no;
        $driver->license_issue_date = date('Y-m-d H:i:s', strtotime($request->license_issue_date));
        $driver->license_expiry_date = date('Y-m-d H:i:s', strtotime($request->license_expiry_date));
        $driver->emergency_contact_details = $request->emergency_contact_details;
        $driver->save();
        $driver->user = $driver->user;
        // log this activity
        $title = "Driver information updated";
        $description = ucwords($user->name) . "'s information was updated.";
        $roles = ['assistant admin', 'warehouse manager'];
        $this->logUserActivity($title, $description, $roles);

        return response()->json(compact('driver'), 200);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreUserRequest $request, $type = "staff")
    {
        $params = $request->all();
        $actor = $this->getUser();
        if (!($actor->isAdmin() || $actor->hasPermission(\App\Laravue\Acl::PERMISSION_USER_MANAGE))) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        // Handing out the admin role is admin-only: otherwise anyone holding
        // "manage user" could mint themselves an admin account.
        if ($params['role'] === \App\Laravue\Acl::ROLE_ADMIN && !$actor->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can create another administrator'], 403);
        }

        $user = User::create([
            'name' => $params['name'],
            'email' => $params['email'],
            'phone' => $params['phone'],
            // 'address' => $params['address'],
            // 'user_type' => $type,
            'password' => $params['password'],
        ]);
        $title = "New Registration";
        $description = ucwords($user->name) . "'s was newly registered by $actor->name ($actor->email)";
        $this->logUserActivity($title, $description);

        $role = Role::findByName($params['role']);
        $user->syncRoles($role);

        return new UserResource($user);
    }

    /**
     * Display the specified resource.
     *
     * @param  User $user
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        return new UserResource($user);
    }

    public function assignRole(Request $request, User $user)
    {
        $actor = $this->getUser();
        if (!$actor->isAdmin()) {
            // Used to fall through and answer 200 with an empty body.
            return response()->json(['message' => 'Only an administrator can change roles'], 403);
        }
        $request->validate(['role' => ['required', 'string', 'exists:roles,name']]);
        // An admin demoting themselves could leave the system with no admin.
        if ((int) $actor->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot change your own role'], 422);
        }

        $role = Role::findByName($request->role);
        $user->syncRoles($role);
        $title = "User assigned role";
        $description = ucwords($user->name) . " was assigned the role of " . $request->role . " by $actor->name ($actor->email)";
        $this->logUserActivity($title, $description);
        return new UserResource($user);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param User    $user
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user)
    {
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }
        // if ($user->isAdmin()) {
        //     return response()->json(['error' => 'Admin can not be modified'], 403);
        // }

        $currentUser = Auth::user();
        if (
            !$currentUser->isAdmin()
            && $currentUser->id !== $user->id
            && !$currentUser->hasPermission(\App\Laravue\Acl::PERMISSION_USER_MANAGE)
        ) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        // Someone with only "manage user" must not be able to edit an admin's
        // email and then use "forgot password" to take the account over.
        if (!$currentUser->isAdmin() && $user->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can modify an administrator'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user->name = $request->get('name');
        $user->email = $request->get('email');
        $user->phone = $request->get('phone');
        // The profile form has always offered an address field, but it was never saved.
        $user->address = $request->get('address');
        $user->save();

        $actor = $this->getUser();
        $title = "Profile Update";
        $description = ucwords($user->name) . "'s information was modified by $actor->name ($actor->email)";
        $this->logUserActivity($title, $description);
        return new UserResource($user);
    }
    public function randomCodeGenerator()
    {
        // Temporary password shown once to the admin who requested the reset.
        return Str::random(10);
    }
    public function adminResetUserPassword(Request $request, User $user)
    {
        $currentUser = Auth::user();
        if (
            !$currentUser->isAdmin()
            && !$currentUser->hasPermission(\App\Laravue\Acl::PERMISSION_USER_MANAGE)
        ) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        if (!$currentUser->isAdmin() && $user->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can reset an administrator\'s password'], 403);
        }
        $new_password = $this->randomCodeGenerator();
        $user->password = $new_password;
        $user->password_status = 'default';
        $user->save();
        $user->tokens()->delete(); // the old password's sessions must not survive a reset

        $actor = $currentUser;
        $title = "Password Reset";
        $description = ucwords($user->name) . "'s password was reset by $actor->name ($actor->email)";
        $this->logUserActivity($title, $description);
        return response()->json(['new_password' => $new_password], 200);
    }
    public function updatePassword(Request $request)
    {

        $user = User::find($request->user_id);
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $currentUser = Auth::user();
        if (
            !$currentUser->isAdmin()
            && $currentUser->id !== $user->id
            && !$currentUser->hasPermission(\App\Laravue\Acl::PERMISSION_USER_MANAGE)
        ) {
            return response()->json(['error' => 'Permission denied'], 403);
        }
        if (!$currentUser->isAdmin() && $user->isAdmin() && $currentUser->id !== $user->id) {
            return response()->json(['message' => 'Only an administrator can change an administrator\'s password'], 403);
        }

        // The old password is checked against the account being changed. (This used
        // to call Auth::guard('web')->attempt() with whatever email/password was
        // posted — a login attempt with the side effect of opening a web session.)
        if (!Hash::check((string) $request->password, $user->password)) {
            return response()->json([
                'error' => 'You need to remember your old password',
                'message' => 'The current password is incorrect',
            ], 401);
        }

        $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'max:190'],
            'c_password' => ['same:new_password'],
        ]);
        $user->password = $request->new_password;
        $user->password_status = 'custom';
        $user->save();
        // Sign out every other session of this account (keep the one making this request).
        $keep = (int) $currentUser->id === (int) $user->id && $currentUser->currentAccessToken()
            ? $currentUser->currentAccessToken()->id
            : null;
        $user->tokens()->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();

        $actor = $this->getUser();
        $title = "Password updated";
        $description = ucwords($user->name) . "'s password was updated by $actor->name ($actor->email)";
        $this->logUserActivity($title, $description);
        return new UserResource($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param User    $user
     * @return UserResource|\Illuminate\Http\JsonResponse
     */
    public function updatePermissions(Request $request, User $user)
    {
        if ($user === null) {
            return response()->json(['error' => 'User not found'], 404);
        }

        if ($user->isAdmin()) {
            return response()->json(['error' => 'Admin can not be modified'], 403);
        }

        $permissionIds = $request->get('permissions', []);
        $rolePermissionIds = array_map(
            function ($permission) {
                return $permission['id'];
            },

            $user->getPermissionsViaRoles()->toArray()
        );

        $newPermissionIds = array_diff($permissionIds, $rolePermissionIds);
        $permissions = Permission::allowed()->whereIn('id', $newPermissionIds)->get();
        $user->syncPermissions($permissions);

        // log this action
        $actor = $this->getUser();
        $title = "Granted Permissions";
        $description = "$actor->name ($actor->email) granted permissions to roles.";
        //log this activity
        $this->logUserActivity($title, $description);
        return new UserResource($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  User $user
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        // Both guards used to be no-ops: the first built a response but never
        // returned it, so an admin account (or your own) could be deleted.
        if ($user->isAdmin()) {
            return response()->json(['error' => 'Ehhh! Can not delete admin user', 'message' => 'An administrator account cannot be deleted'], 403);
        }
        if ((int) $user->id === (int) Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }
        // orders.user_id is a real foreign key now (RESTRICT): deleting a customer would
        // orphan their sales history, so it is refused up front with a readable reason
        // rather than surfacing a raw SQL error.
        if ($user->orders()->exists()) {
            return response()->json(['message' => 'This account has placed orders, so it cannot be deleted. Its order history must be kept.'], 422);
        }

        try {
            $actor = $this->getUser();
            $title = "User Deleted";
            $description = ucwords($user->name) . " was deleted by " . $actor->name;
            $this->logUserActivity($title, $description);
            $user->delete();
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 403);
        }

        return response()->json(null, 204);
    }

    /**
     * Get permissions from role
     *
     * @param User $user
     * @return array|\Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function permissions(User $user)
    {
        try {
            return new JsonResponse([
                'user' => PermissionResource::collection($user->getDirectPermissions()),
                'role' => PermissionResource::collection($user->getPermissionsViaRoles()),
            ]);
        } catch (\Exception $ex) {
            return response()->json(['error' => $ex->getMessage()], 403);
        }
    }

    /**
     * @param bool $isNew
     * @return array
     */
    private function getValidationRules($isNew = true)
    {
        return [
            'name' => 'required',
            // 'email' => $isNew ? 'required|email|unique:users' : 'required|email',
            'roles' => [
                'required',
                'array'
            ],
        ];
    }
}

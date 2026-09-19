<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        $currentUser = Auth::user();
        $can_edit = false;
        if ($this->id === $currentUser->id) {
            $can_edit = true;
        }
        if ($currentUser->hasRole('admin') && $this->user_type == 'customer') {
            $can_edit = true;
        }
        if ($currentUser->hasRole('admin')) {
            $can_edit = true;
        }
        // $db_notifications = $this->unreadNotifications()->orderBy('created_at', 'DESC')->get();
        // $notification
        // foreach ($db_notifications as $db_notification) {
        //     # code...
        // }
        $user_role = [$this->role];
        $roles = array_map(
            function ($role) {
                return $role['name'];
            },
            $this->roles->toArray()
        );
        // $permissions = array_map(
        //     function ($permission) {
        //         return $permission['name'];
        //     },
        //     $this->allPermissions()->toArray()
        // );
        $rights = array_merge($roles, $user_role);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'nearest_bustop' => $this->address,
            'notifications' => [],
            'role' => $this->role,
            'roles' => $rights,
            'permissions' => array_map(
                function ($permission) {
                    return $permission['name'];
                },
                $this->getAllPermissions()->toArray()
            ),
            // present on the customer list (withCount), absent elsewhere
            'orders_count' => $this->when(isset($this->orders_count), fn () => (int) $this->orders_count),
            'joined_at' => optional($this->created_at)->toDateString(),
            'avatar' => '/images/no-image.png', //'https://i.pravatar.cc',
            'can_edit' => $can_edit,
            'p_status' => $this->password_status,
        ];
    }
}

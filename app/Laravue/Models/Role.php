<?php

/**
 * File Role.php
 *
 * @author Tuan Duong <bacduong@gmail.com>
 * @package Laravue
 * @version
 */

namespace App\Laravue\Models;

use App\Laravue\Acl;
use Spatie\Permission\Models\Permission;
use Illuminate\Notifications\Notifiable;

/**
 * Class Role
 *
 * @property Permission[] $permissions
 * @property string $name
 * @package App\Laravue\Models
 */
class Role extends \Spatie\Permission\Models\Role
{
    use Notifiable;
    public $guard_name = 'api';

    /**
     * Check whether current role is admin
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->name === Acl::ROLE_ADMIN;
    }
    public function isAssistantAdmin(): bool
    {
        return $this->name === Acl::ROLE_ASSISTANT_ADMIN;
    }
}

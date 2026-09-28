<?php

declare(strict_types=1);

namespace App\Central\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * SaaS administrator — a user in the central database. Authentication continues
 * to run through App\Models\User (which resolves to the central connection in
 * central context); this model is for central-side queries that must always
 * target the central database regardless of tenancy state.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
class CentralUser extends Model
{
    use CentralConnection;

    protected $table = 'users';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = ['password', 'remember_token'];
}

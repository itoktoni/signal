<?php

namespace App\Enums;

use App\Traits\OptionEnum;
use BenSampo\Enum\Enum;

/**
 * @method static static User()
 * @method static static Admin()
 */
final class RoleType extends Enum
{
    use OptionEnum;

    const User = 'user';
    const Admin = 'admin';
}

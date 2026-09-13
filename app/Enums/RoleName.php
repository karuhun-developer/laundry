<?php

namespace App\Enums;

enum RoleName: string
{
    case Superadmin = 'superadmin';
    case Owner = 'owner';
    case BranchAdmin = 'branch_admin';
    case Customer = 'customer';
}

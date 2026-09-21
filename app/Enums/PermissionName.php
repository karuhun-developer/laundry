<?php

namespace App\Enums;

enum PermissionName: string
{
    case ManageOrganizations = 'manage organizations';
    case ManageBranches = 'manage branches';
    case ManageUsers = 'manage users';
    case ManageCatalog = 'manage catalog';
    case ManageOrders = 'manage orders';
    case ManageMessageTemplates = 'manage message templates';
    case UpdateOrderStatus = 'update order status';
    case ViewAudit = 'view audit';
    case ManageCashflow = 'manage cashflow';
}

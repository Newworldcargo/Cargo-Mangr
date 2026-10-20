<?php

$permissions = ['view-all-branch-billing-invoices', 'view-all-branch-billing-quotations'];
foreach (['invoices', 'quotations'] as $type) {
    foreach (['view', 'create', 'edit', 'issue', 'send', 'schedule-reminder'] as $action) $permissions[] = $action . '-billing-' . $type;
}
return ['permissions' => ['sales' => array_merge($permissions, [
    'manage-billing-settings',
    'record-payment-billing-invoices', 'void-billing-invoices', 'record-decision-billing-quotations', 'convert-billing-quotations',
])]];

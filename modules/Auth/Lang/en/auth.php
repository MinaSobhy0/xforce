<?php

return [
    // Module info
    'module_name' => 'Authentication',
    'module_description' => 'User authentication, roles and permissions management',

    // Users
    'user' => 'User',
    'users' => 'Users',
    'user_name' => 'Name',
    'user_email' => 'Email',
    'user_phone' => 'Phone',
    'user_password' => 'Password',
    'user_confirm_password' => 'Confirm Password',
    'user_avatar' => 'Avatar',
    'user_role' => 'Role',
    'user_roles' => 'Roles',
    'user_status' => 'Status',
    'user_last_login' => 'Last Login',
    'user_created' => 'Created',

    // User Statuses
    'status_active' => 'Active',
    'status_inactive' => 'Inactive',
    'status_suspended' => 'Suspended',
    'status_pending' => 'Pending',

    // Roles
    'role' => 'Role',
    'roles' => 'Roles',
    'role_name' => 'Role Name',
    'role_permissions' => 'Permissions',
    'role_users' => 'Users with this role',
    'system_role' => 'System Role',

    // Permissions
    'permission' => 'Permission',
    'permissions' => 'Permissions',
    'permission_name' => 'Permission Name',
    'permission_module' => 'Module',
    'grant_permission' => 'Grant Permission',
    'revoke_permission' => 'Revoke Permission',

    // Profile
    'profile' => 'Profile',
    'my_profile' => 'My Profile',
    'edit_profile' => 'Edit Profile',
    'change_password' => 'Change Password',
    'current_password' => 'Current Password',
    'new_password' => 'New Password',
    'confirm_new_password' => 'Confirm New Password',
    'password_changed' => 'Password changed successfully',
    'profile_updated' => 'Profile updated successfully',

    // Two Factor Authentication
    'two_factor' => 'Two-Factor Authentication',
    'two_factor_setup' => '2FA Setup',
    'enable_2fa' => 'Enable Two-Factor Authentication',
    'disable_2fa' => 'Disable Two-Factor Authentication',
    'scan_qr_code' => 'Scan the QR code with your authenticator app',
    'enter_code' => 'Enter verification code',
    'backup_codes' => 'Backup Codes',
    'backup_codes_warning' => 'Save these codes in a secure place. Each code can only be used once.',
    '2fa_enabled' => 'Two-factor authentication enabled',
    '2fa_disabled' => 'Two-factor authentication disabled',

    // Login / Authentication
    'login' => 'Login',
    'logout' => 'Logout',
    'sign_in' => 'Sign In',
    'sign_out' => 'Sign Out',
    'remember_me' => 'Remember Me',
    'forgot_password' => 'Forgot Password?',
    'reset_password' => 'Reset Password',
    'send_reset_link' => 'Send Password Reset Link',
    'login_failed' => 'Invalid credentials',
    'account_locked' => 'Your account has been locked due to too many failed attempts',
    'session_expired' => 'Your session has expired. Please login again.',

    // Messages
    'user_created' => 'User created successfully',
    'user_updated' => 'User updated successfully',
    'user_deleted' => 'User deleted successfully',
    'role_created' => 'Role created successfully',
    'role_updated' => 'Role updated successfully',
    'role_deleted' => 'Role deleted successfully',
    'cannot_delete_system_role' => 'Cannot delete system roles',
    'cannot_delete_own_account' => 'You cannot delete your own account',

    // Impersonation
    'impersonate' => 'Impersonate',
    'stop_impersonating' => 'Stop Impersonating',
    'impersonating_user' => 'You are impersonating :name',

    // Access Policies
    'access_policy' => 'Access Policy',
    'access_policies' => 'Access Policies',
    'policy_details' => 'Policy Details',
    'policy_name' => 'Policy Name',
    'model_type' => 'Model Type',
    'model_type_help' => 'Full model class name (e.g., Modules\\Patients\\Models\\Patient)',
    'description' => 'Description',
    'apply_to_all_roles' => 'Apply to All Roles',
    'apply_to_all_roles_help' => 'When enabled, this policy applies to all roles regardless of role selection',
    'priority' => 'Priority',
    'priority_help' => 'Lower number = higher priority. Policies are evaluated in priority order.',
    'active' => 'Active',
    'created_at' => 'Created At',
    'duplicate' => 'Duplicate',
    'all_roles' => 'All Roles',

    // Permissions in Access Policies
    'perm_read' => 'Read',
    'perm_create' => 'Create',
    'perm_update' => 'Update',
    'perm_delete' => 'Delete',

    // Domain Filter
    'domain_filter' => 'Domain Filter (Record Rules)',
    'field' => 'Field',
    'operator' => 'Operator',
    'value' => 'Value',
    'value_placeholders' => 'Use {user.id}, {user.branch_id}, {user.tenant_id}, {today}, {now}',
    'add_condition' => 'Add Condition',
    'domain_filter_help' => 'Define conditions to filter records. Users will only see records matching ALL conditions.',

    // Branch Roles
    'branch_role' => 'Branch Role',
    'branch_roles' => 'Branch Roles',
    'assign_branch_role' => 'Assign Role to Branch',
    'primary_branch' => 'Primary Branch',
];

-- Branch-owned personnel record permissions.
-- Operations: personnel operation/deployment history.
-- Training: education management already uses manage_education.
INSERT INTO role_permissions (role_code, permission_code)
SELECT r.code, 'manage_operations'
FROM roles r
WHERE r.code IN ('dag','ddg','cc','soi','soii','soiii')
  AND r.status = 'Active'
ON DUPLICATE KEY UPDATE permission_code = VALUES(permission_code);

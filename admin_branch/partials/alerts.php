<?php
$showSuccess = isset($success) && !empty($success);
$showErrors = isset($errors) && !empty($errors);
$alertErrors = [];
if (isset($errors) && is_array($errors)) {
    $alertErrors = $errors;
}
?>
<?php if ($showSuccess): ?>
    <div class="alert alert-success">Staff member registered successfully!</div>
<?php elseif ($showErrors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($alertErrors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
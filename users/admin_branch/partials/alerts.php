<?php if ($success): ?>
    <div class="alert alert-success">Staff member registered successfully!</div>
<?php elseif ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?>
                <li><?=htmlspecialchars($err)?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
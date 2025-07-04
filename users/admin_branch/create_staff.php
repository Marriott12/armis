<?php
require_once '../init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
if (!securePage($_SERVER['PHP_SELF'])) { die(); }
require_once 'partials/create_staff_config.php';
require_once 'partials/create_staff_handle_post.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<div class="container my-5" style="background: #f8f9fa; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); padding: 30px;">
    <div class="mb-3">
        <a href="../admin_branch.php" class="btn btn-outline-secondary" aria-label="Back to Admin Branch">
            <i class="fa fa-arrow-left"></i> Back to Admin Branch
        </a>
    </div>
    <div class="card shadow-sm">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0"><i class="fa fa-user-plus"></i> Register New Staff Member</h4>
        </div>
        <div class="card-body">
            <?php require 'partials/alerts.php'; ?>
            <?php require 'partials/create_staff_tabs.php'; ?>
            <form id="createStaffForm" method="post" action="<?=htmlspecialchars($_SERVER["PHP_SELF"]);?>" autocomplete="off">
                <input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf_token'])?>">
                <div class="tab-content" id="staffTabContent">
                    <?php require 'partials/tab_personal.php'; ?>
                    <?php require 'partials/tab_service.php'; ?>
                    <?php require 'partials/tab_family.php'; ?>
                    <?php require 'partials/tab_academic.php'; ?>
                    <!--<?php //require 'partials/tab_honours.php'; ?>-->
                    <?php require 'partials/tab_id.php'; ?>
                    <?php require 'partials/tab_residence.php'; ?>
                    <?php require 'partials/tab_language.php'; ?>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-success px-5 py-2"><i class="fa fa-save"></i> Register Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>
<button onclick="scrollToTop()" id="scrollBtn" class="btn btn-secondary" style="display:none; position:fixed; bottom:20px; right:20px; z-index:999;">Top</button>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require 'partials/create_staff_js.php'; ?>
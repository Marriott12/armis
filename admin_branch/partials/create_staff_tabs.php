<?php
// FIX: this file previously checked each tab's error flag independently
// (e.g. `isset($tabErrors['service'])`), so a submission with errors in
// multiple tabs could mark more than one pane "active" at once - Bootstrap
// doesn't hide sibling .tab-pane.show.active elements on initial page load
// without JS running first, so they'd visibly stack. Computing a single
// active tab up front guarantees exactly one button/pane pair is active.
$__tabOrder = ['personal', 'service', 'family', 'academic', 'honours', 'id', 'residence', 'language'];
$activeTabKey = 'personal';
if (!empty($tabErrors)) {
    foreach ($__tabOrder as $__key) {
        if (!empty($tabErrors[$__key])) {
            $activeTabKey = $__key;
            break;
        }
    }
}
?>
<div class="d-flex flex-wrap gap-2 mb-4" id="staffTab" role="tablist">
    <button class="btn btn-outline-primary <?=$activeTabKey === 'personal' ? 'active' : ''?>" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="<?=$activeTabKey === 'personal' ? 'true' : 'false'?>">
        <i class="fa fa-user me-1"></i> Personal Details
        <?php if (!empty($tabErrors['personal'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'service' ? 'active' : ''?>" id="service-tab" data-bs-toggle="tab" data-bs-target="#service" type="button" role="tab" aria-controls="service" aria-selected="<?=$activeTabKey === 'service' ? 'true' : 'false'?>">
        <i class="fa fa-briefcase me-1"></i> Service Details
        <?php if (!empty($tabErrors['service'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'family' ? 'active' : ''?>" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button" role="tab" aria-controls="family" aria-selected="<?=$activeTabKey === 'family' ? 'true' : 'false'?>">
        <i class="fa fa-users me-1"></i> Family Details
        <?php if (!empty($tabErrors['family'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'academic' ? 'active' : ''?>" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab" aria-controls="academic" aria-selected="<?=$activeTabKey === 'academic' ? 'true' : 'false'?>">
        <i class="fa fa-graduation-cap me-1"></i> Academic Details
        <?php if (!empty($tabErrors['academic'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'honours' ? 'active' : ''?>" id="honours-tab" data-bs-toggle="tab" data-bs-target="#honours" type="button" role="tab" aria-controls="honours" aria-selected="<?=$activeTabKey === 'honours' ? 'true' : 'false'?>">
        <i class="fa fa-medal me-1"></i> Honours
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'id' ? 'active' : ''?>" id="id-tab" data-bs-toggle="tab" data-bs-target="#id" type="button" role="tab" aria-controls="id" aria-selected="<?=$activeTabKey === 'id' ? 'true' : 'false'?>">
        <i class="fa fa-id-card me-1"></i> Identification Docs
        <?php if (!empty($tabErrors['id'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'residence' ? 'active' : ''?>" id="residence-tab" data-bs-toggle="tab" data-bs-target="#residence" type="button" role="tab" aria-controls="residence" aria-selected="<?=$activeTabKey === 'residence' ? 'true' : 'false'?>">
        <i class="fa fa-house me-1"></i> Residential Details
    </button>
    <button class="btn btn-outline-primary <?=$activeTabKey === 'language' ? 'active' : ''?>" id="language-tab" data-bs-toggle="tab" data-bs-target="#language" type="button" role="tab" aria-controls="language" aria-selected="<?=$activeTabKey === 'language' ? 'true' : 'false'?>">
        <i class="fa fa-language me-1"></i> Language Details
    </button>
</div>
<?php
// FIX: this file previously also rendered its own <div class="tab-content">
// wrapper and only required tab_personal.php inside it (with the other 7
// tab includes commented out) - a second, unused tab-content shell.
// create_staff.php now owns the single tab-content div and requires all
// 8 tab partials directly in the right order, so this file's only job is
// the nav button bar above.
?>

<div class="d-flex flex-wrap gap-2 mb-4" id="staffTab" role="tablist">
    <button class="btn btn-outline-primary <?=!$tabErrors || isset($tabErrors['personal']) ? 'active' : ''?>" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
        <i class="bi bi-person-fill me-1"></i> Personal Details
        <?php if(isset($tabErrors['personal'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=isset($tabErrors['service']) ? 'active' : ''?>" id="service-tab" data-bs-toggle="tab" data-bs-target="#service" type="button" role="tab">
        <i class="bi bi-briefcase-fill me-1"></i> Service Details
        <?php if(isset($tabErrors['service'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=isset($tabErrors['family']) ? 'active' : ''?>" id="family-tab" data-bs-toggle="tab" data-bs-target="#family" type="button" role="tab">
        <i class="bi bi-people-fill me-1"></i> Family Details
        <?php if(isset($tabErrors['family'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=isset($tabErrors['academic']) ? 'active' : ''?>" id="academic-tab" data-bs-toggle="tab" data-bs-target="#academic" type="button" role="tab">
        <i class="bi bi-mortarboard-fill me-1"></i> Academic Details
        <?php if(isset($tabErrors['academic'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <!--
    <button class="btn btn-outline-primary" id="honours-tab" data-bs-toggle="tab" data-bs-target="#honours" type="button" role="tab">
        <i class="bi bi-award-fill me-1"></i> Honours
    </button>
    -->
    <button class="btn btn-outline-primary <?=isset($tabErrors['id']) ? 'active' : ''?>" id="id-tab" data-bs-toggle="tab" data-bs-target="#id" type="button" role="tab">
        <i class="bi bi-card-text me-1"></i> Identification Docs
        <?php if(isset($tabErrors['id'])): ?><span class="badge bg-danger ms-1">!</span><?php endif; ?>
    </button>
    <button class="btn btn-outline-primary <?=isset($tabErrors['residence']) ? 'active' : ''?>" id="residence-tab" data-bs-toggle="tab" data-bs-target="#residence" type="button" role="tab">
        <i class="bi bi-house-door-fill me-1"></i> Residential Details
    </button>
    <button class="btn btn-outline-primary <?=isset($tabErrors['language']) ? 'active' : ''?>" id="language-tab" data-bs-toggle="tab" data-bs-target="#language" type="button" role="tab">
        <i class="bi bi-translate me-1"></i> Language Details
    </button>
</div>
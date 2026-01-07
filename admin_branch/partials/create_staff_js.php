<script>
(function() {
  // Scroll-to-top
  window.scrollToTop = function() { window.scrollTo({ top: 0, behavior: 'smooth' }); };
  window.onscroll = function() {
    var btn = document.getElementById("scrollBtn");
    if (btn) btn.style.display = (document.documentElement.scrollTop > 100) ? "block" : "none";
  };

  // Wait for DOM and jQuery to be ready
  document.addEventListener('DOMContentLoaded', function() {
    // Province-District dynamic dropdown
    <?php if (isset($provinceDistricts)): ?>
    const provinceDistricts = <?=json_encode($provinceDistricts)?>;
    if (typeof $ !== 'undefined') {
      $('#province').on('change', function() {
      const selected = this.value;
      const districtSelect = $('#district');
      districtSelect.html('<option value="">Select District</option>');
      if (provinceDistricts[selected]) {
          provinceDistricts[selected].forEach(function(d) {
              let sel = '';
              <?php if (isset($_POST['district'])): ?>
              if (d === <?=json_encode($_POST['district'])?>) sel = 'selected';
              <?php endif; ?>
              districtSelect.append('<option value="'+d+'" '+sel+'>'+d+'</option>');
          });
      }
      });
    } // End jQuery check
    <?php endif; ?>

  // --- Dynamic add/remove for all sections ---
  window.addChild = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2 align-items-end';
      div.innerHTML = `
          <div class="col-md-3 mb-2"><input type="text" name="child_name[]" class="form-control form-control-sm" placeholder="Name"></div>
          <div class="col-md-2 mb-2"><input type="date" name="child_dob[]" class="form-control form-control-sm" placeholder="Date of Birth"></div>
          <div class="col-md-2 mb-2"><input type="text" name="child_nrc[]" class="form-control form-control-sm" placeholder="NRC"></div>
          <div class="col-md-3 mb-2"><select name="child_relationship[]" class="form-select form-select-sm"><option value="">Relationship</option><?php foreach ($relationshipOptions as $rel): ?><option value="<?=$rel?>"><?=$rel?></option><?php endforeach; ?></select></div>
          <div class="col-md-2 mb-2"><select name="child_gender[]" class="form-select form-select-sm"><option value="">Gender</option><option>Male</option><option>Female</option></select></div>
          <div class="col-auto mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('childrenList').appendChild(div);
  };

  window.addAcademic = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-3 mb-2"><input type="text" name="academic_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
          <div class="col-md-2 mb-2"><input type="month" name="academic_start[]" class="form-control form-control-sm" placeholder="Start"></div>
          <div class="col-md-2 mb-2"><input type="month" name="academic_end[]" class="form-control form-control-sm" placeholder="End"></div>
          <div class="col-md-3 mb-2"><input type="text" name="academic_qualification[]" class="form-control form-control-sm" placeholder="Qualification"></div>
          <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('academicList').appendChild(div);
  };

  window.addProfTech = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-3 mb-2"><input type="text" name="proftech_profession[]" class="form-control form-control-sm" placeholder="Profession"></div>
          <div class="col-md-3 mb-2"><input type="text" name="proftech_course[]" class="form-control form-control-sm" placeholder="Course/Qualification"></div>
          <div class="col-md-2 mb-2"><input type="text" name="proftech_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
          <div class="col-md-2 mb-2"><input type="text" name="proftech_year[]" class="form-control form-control-sm" placeholder="Year"></div>
          <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('profTechList').appendChild(div);
  };

  window.addMilCourse = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-2 mb-2"><input type="text" name="milcourse_name[]" class="form-control form-control-sm" placeholder="Course Name"></div>
          <div class="col-md-2 mb-2"><input type="text" name="milcourse_institution[]" class="form-control form-control-sm" placeholder="Institution"></div>
          <div class="col-md-2 mb-2"><input type="date" name="milcourse_start[]" class="form-control form-control-sm" placeholder="Start Date"></div>
          <div class="col-md-2 mb-2"><input type="date" name="milcourse_end[]" class="form-control form-control-sm" placeholder="End Date"></div>
          <div class="col-md-2 mb-2"><input type="text" name="milcourse_result[]" class="form-control form-control-sm" placeholder="Result"></div>
          <div class="col-md-1 mb-2"><input type="text" name="milcourse_type[]" class="form-control form-control-sm" placeholder="Type"></div>
          <div class="col-md-1 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('milCourseList').appendChild(div);
  };

  window.addTradeGroup = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-2 mb-2"><input type="text" name="tradegroup_employment[]" class="form-control form-control-sm" placeholder="Employment"></div>
          <div class="col-md-2 mb-2"><input type="text" name="tradegroup_group[]" class="form-control form-control-sm" placeholder="Group"></div>
          <div class="col-md-2 mb-2"><input type="text" name="tradegroup_class[]" class="form-control form-control-sm" placeholder="Class"></div>
          <div class="col-md-2 mb-2"><input type="date" name="tradegroup_date[]" class="form-control form-control-sm" placeholder="Date"></div>
          <div class="col-md-2 mb-2"><input type="text" name="tradegroup_authority[]" class="form-control form-control-sm" placeholder="Authority"></div>
          <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('tradeGroupList').appendChild(div);
  };

  window.addAward = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-5 mb-2"><input type="text" name="award_name[]" class="form-control form-control-sm" placeholder="Name of Award"></div>
          <div class="col-md-3 mb-2"><input type="date" name="award_date[]" class="form-control form-control-sm" placeholder="Date of Award"></div>
          <div class="col-md-4 mb-2"><input type="text" name="award_authority[]" class="form-control form-control-sm" placeholder="Authority"></div>
          <div class="col-md-12 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('awardList').appendChild(div);
  };

  window.addAppointment = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-3 mb-2"><input type="text" name="appointment_name[]" class="form-control form-control-sm" placeholder="Appointment Name"></div>
          <div class="col-md-3 mb-2"><input type="text" name="appointment_unit[]" class="form-control form-control-sm" placeholder="Unit"></div>
          <div class="col-md-2 mb-2"><input type="date" name="appointment_start[]" class="form-control form-control-sm" placeholder="Start Date"></div>
          <div class="col-md-2 mb-2"><input type="date" name="appointment_end[]" class="form-control form-control-sm" placeholder="End Date"></div>
          <div class="col-md-2 mb-2"><input type="text" name="appointment_authority[]" class="form-control form-control-sm" placeholder="Authority"></div>
          <div class="col-md-12 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('appointmentList').appendChild(div);
  };

  window.addPromotion = function() {
      const div = document.createElement('div');
      div.className = 'row mb-2';
      div.innerHTML = `
          <div class="col-md-2 mb-2"><input type="text" name="promotion_rank[]" class="form-control form-control-sm" placeholder="Rank"></div>
          <div class="col-md-2 mb-2"><input type="date" name="promotion_date_from[]" class="form-control form-control-sm" placeholder="Date From"></div>
          <div class="col-md-2 mb-2"><input type="date" name="promotion_date_to[]" class="form-control form-control-sm" placeholder="Date To"></div>
          <div class="col-md-2 mb-2"><input type="text" name="promotion_next_rank[]" class="form-control form-control-sm" placeholder="Next Rank"></div>
          <div class="col-md-2 mb-2"><input type="text" name="promotion_type[]" class="form-control form-control-sm" placeholder="Promotion/Reversion"></div>
          <div class="col-md-1 mb-2"><input type="text" name="promotion_authority[]" class="form-control form-control-sm" placeholder="Authority"></div>
          <div class="col-md-1 mb-2"><button type="button" class="btn btn-danger btn-sm btn-remove-block" title="Remove"><i class="fa fa-times"></i></button></div>
      `;
      document.getElementById('promotionList').appendChild(div);
  };

  window.addLanguage = function(languageName = '') {
      const index = document.querySelectorAll('#languageList .language-block').length;
      const div = document.createElement('div');
      div.className = 'row mb-3 border rounded p-2 language-block position-relative';
      div.innerHTML = `
          <div class="col-md-12 text-end">
            <button type="button" class="btn-close" aria-label="Remove" onclick="removeLanguage(this)" style="position: absolute; top: 5px; right: 10px;"></button>
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm">Language</label>
            <input type="text" name="language_name[]" class="form-control form-control-sm" placeholder="Enter language" value="${languageName}">
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm d-block">Spoken</label>
            <div class="form-check">
              <input type="radio" name="language_spoken_${index}" class="form-check-input" value="Fairly well">
              <label class="form-check-label">Fairly well</label>
            </div>
            <div class="form-check">
              <input type="radio" name="language_spoken_${index}" class="form-check-input" value="Fluently">
              <label class="form-check-label">Fluently</label>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm d-block">Read</label>
            <div class="form-check">
              <input type="radio" name="language_read_${index}" class="form-check-input" value="Slightly">
              <label class="form-check-label">Slightly</label>
            </div>
            <div class="form-check">
              <input type="radio" name="language_read_${index}" class="form-check-input" value="Well">
              <label class="form-check-label">Well</label>
            </div>
          </div>
          <div class="col-md-3 mb-2">
            <label class="form-label form-label-sm d-block">Written</label>
            <div class="form-check">
              <input type="radio" name="language_written_${index}" class="form-check-input" value="Slightly">
              <label class="form-check-label">Slightly</label>
            </div>
            <div class="form-check">
              <input type="radio" name="language_written_${index}" class="form-check-input" value="Well">
              <label class="form-check-label">Well</label>
            </div>
          </div>
      `;
      document.getElementById('languageList').appendChild(div);
  };
  window.removeLanguage = function(button) {
      const block = button.closest('.language-block');
      if (block) block.remove();
  };

  // Remove handler for all dynamic sections (jQuery-dependent)
  if (typeof $ !== 'undefined') {
    function addDynamicRemoveHandler(listId) {
        $('#' + listId).on('click', '.btn-remove-block', function() {
            $(this).closest('.row').remove();
        });
    }
    ['childrenList','academicList','profTechList','milCourseList','tradeGroupList','awardList','appointmentList','promotionList','languageList'].forEach(addDynamicRemoveHandler);
    
    // NRC input restrictions (jQuery)
    $('#nrc_part1').on('input', function() {
      this.value = this.value.replace(/\D/g, '').slice(0,6);
      if (this.value.length === 6) {
        $('#nrc_part2').focus();
      }
    });
    $('#nrc_part2').on('input', function() {
      this.value = this.value.replace(/\D/g, '').slice(0,2);
    });
  }

  // Add one field by default for each dynamic section
  if(document.getElementById('childrenList')) addChild();
  if(document.getElementById('academicList')) addAcademic();
  if(document.getElementById('profTechList')) addProfTech();
  if(document.getElementById('milCourseList')) addMilCourse();
  if(document.getElementById('tradeGroupList')) addTradeGroup();
  if(document.getElementById('awardList')) addAward();
  if(document.getElementById('appointmentList')) addAppointment();
  if(document.getElementById('promotionList')) addPromotion();
  if(document.getElementById('languageList')) addLanguage('English');
  
  // Marital status toggle
  var maritalStatus = document.getElementById('maritalStatus');
  if (maritalStatus) {
    maritalStatus.addEventListener('change', function() {
      var spouseSection = document.getElementById('spouseSection');
      if (spouseSection) spouseSection.style.display = (this.value === 'Married') ? 'block' : 'none';
    });
  }

  // Filter ranks by category using optgroups
  const rankDiv = document.getElementById('rankDiv');
  const rankSelect = document.getElementById('rankSelect');
  const categorySelect = document.getElementById('categorySelect');
  
  function filterRanksByCategory() {
    if (!rankSelect || !categorySelect) return;
    
    const selectedCategory = categorySelect.value.trim();
    const optgroups = rankSelect.querySelectorAll('optgroup');
    const standaloneOptions = rankSelect.querySelectorAll('option:not([value=""]):not(optgroup option)');
    
    // Normalize category for matching - both values to same format
    const normalizeCategory = (cat) => {
      if (!cat) return '';
      cat = cat.trim().toLowerCase();
      if (cat === 'nco' || cat === 'non-commissioned officer' || cat === 'enlisted') return 'non-commissioned officer';
      if (cat === 'civilian employee' || cat === 'ce' || cat === 'civilian') return 'civilian employee';
      if (cat === 'officer') return 'officer';
      if (cat === 'officer cadet') return 'officer cadet';
      if (cat === 'recruit') return 'recruit';
      return cat;
    };
    
    const normalizedSelectedCategory = normalizeCategory(selectedCategory);
    
    // Show/hide optgroups based on category
    optgroups.forEach(optgroup => {
      const optgroupCategory = normalizeCategory(optgroup.dataset.category || optgroup.label);
      const isMatch = normalizedSelectedCategory === '' || optgroupCategory === normalizedSelectedCategory;
      const options = optgroup.querySelectorAll('option');
      
      if (isMatch) {
        optgroup.style.display = '';
        // Enable all options in this optgroup
        options.forEach(opt => {
          opt.disabled = false;
          opt.style.display = '';
        });
      } else {
        optgroup.style.display = 'none';
        // Disable all options in this optgroup
        options.forEach(opt => {
          opt.disabled = true;
          opt.style.display = 'none';
        });
      }
    });
    
    // Handle standalone options (if any exist outside optgroups)
    standaloneOptions.forEach(option => {
      const optionCategory = normalizeCategory(option.dataset.category || '');
      if (normalizedSelectedCategory === '' || optionCategory === normalizedSelectedCategory) {
        option.style.display = 'block';
        option.disabled = false;
      } else {
        option.style.display = 'none';
        option.disabled = true;
      }
    });
    
    // Show/hide rank div based on category selection
    if (rankDiv) {
      rankDiv.style.display = (selectedCategory === '') ? 'none' : '';
    }
  }
  
  if (categorySelect) {
    categorySelect.addEventListener('change', filterRanksByCategory);
    window.addEventListener('DOMContentLoaded', filterRanksByCategory);
  }

  // Tab Persistence
  const allTabs = document.querySelectorAll('[data-bs-toggle="tab"]');
  allTabs.forEach(button => {
    button.addEventListener('shown.bs.tab', function () {
      const tabId = this.getAttribute('id');
      localStorage.setItem('activeTabId', tabId);
    });
  });
  window.addEventListener('DOMContentLoaded', () => {
    const activeTabId = localStorage.getItem('activeTabId');
    if (activeTabId) {
      const triggerTab = document.getElementById(activeTabId);
      if (triggerTab) {
        const tab = new bootstrap.Tab(triggerTab);
        tab.show();
      }
    }
  });

  // Scroll to top on tab click
  var scrollBtn = document.getElementById('scrollBtn');
  if (scrollBtn) {
    window.addEventListener("scroll", () => {
      scrollBtn.style.display = window.scrollY > 300 ? "block" : "none";
    });
    scrollBtn.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }

  // Bootstrap Form Validation
  (() => {
    'use strict';
    document.addEventListener('submit', function (event) {
      const form = event.target;
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, true);
  })();

  // Example AJAX Save Handler for a form (extend for others)
  document.addEventListener('submit', function (e) {
    const form = e.target;
    if (form.matches('.ajax-save')) {
      e.preventDefault();
      if (!form.checkValidity()) return;

      const formData = new FormData(form);
      const jsonData = Object.fromEntries(formData.entries());

      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      button.innerHTML = 'Saving...';

      fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(jsonData)
      })
      .then(res => res.json())
      .then(data => {
        alert('Saved successfully!');
      })
      .catch(err => {
        console.error(err);
        alert('Error saving data.');
      })
      .finally(() => {
        button.disabled = false;
        button.innerHTML = 'Save';
      });
    }
  });

  // ==================== AUTO-FORMATTING FOR NRC AND PHONE NUMBERS ====================
  
  // NRC Auto-formatting: 123456/78/1
  const nrcField = document.getElementById('nrc');
  if (nrcField) {
    nrcField.addEventListener('input', function(e) {
      let value = this.value.replace(/[^0-9]/g, ''); // Remove all non-digits
      
      if (value.length >= 6) {
        // Add first slash after 6 digits
        value = value.substring(0, 6) + '/' + value.substring(6);
      }
      if (value.length >= 9) {
        // Add second slash after 2 more digits
        value = value.substring(0, 9) + '/' + value.substring(9);
      }
      if (value.length >= 11) {
        // Auto-append /1 if not present
        if (!value.endsWith('/1')) {
          value = value.substring(0, 9) + '/1';
        }
      }
      
      this.value = value.substring(0, 11); // Limit to 123456/78/1 format
    });
    
    // On blur, ensure /1 is appended if we have 8 digits
    nrcField.addEventListener('blur', function() {
      let value = this.value.replace(/[^0-9]/g, '');
      if (value.length === 8) {
        this.value = value.substring(0, 6) + '/' + value.substring(6, 8) + '/1';
      }
    });
  }
  
  // Phone Number Auto-formatting: +260976123456
  function formatPhoneNumber(inputField) {
    if (!inputField) return;
    
    inputField.addEventListener('input', function(e) {
      let value = this.value.replace(/[^0-9+]/g, ''); // Keep only digits and +
      let digits = value.replace(/[^0-9]/g, ''); // Get just digits
      
      // Auto-format based on what user types
      if (digits.length > 0) {
        if (digits.startsWith('260') && digits.length <= 12) {
          // Format: 260976123456 -> +260976123456
          this.value = '+' + digits;
        } else if (digits.startsWith('0') && digits.length <= 10) {
          // Keep as is while typing, will format on blur
          this.value = digits;
        } else if (digits.length <= 9 && ['9', '7', '5'].includes(digits[0])) {
          // Keep as is while typing, will format on blur
          this.value = digits;
        } else {
          this.value = value; // Keep whatever they typed
        }
      } else {
        this.value = value;
      }
    });
    
    // On blur, auto-add +260 if needed
    inputField.addEventListener('blur', function() {
      let digits = this.value.replace(/[^0-9]/g, '');
      
      if (digits.length === 9 && ['9', '7', '5'].includes(digits[0])) {
        // Format: 976123456 -> +260976123456
        this.value = '+260' + digits;
      } else if (digits.length === 10 && digits.startsWith('0') && ['9', '7', '5'].includes(digits[1])) {
        // Format: 0976123456 -> +260976123456
        this.value = '+260' + digits.substring(1);
      } else if (digits.length === 12 && digits.startsWith('260')) {
        // Format: 260976123456 -> +260976123456
        this.value = '+' + digits;
      }
    });
  }
  
  // Apply phone formatting to all phone fields
  const phoneFields = [
    document.getElementById('phone'),
    document.querySelector('input[name="nok_tel"]'),
    document.querySelector('input[name="alt_nok_tel"]'),
    document.querySelector('input[name="nokTel"]'),
    document.querySelector('input[name="nokPhone"]'),
    document.querySelector('input[name="altNokTel"]')
  ];
  
  phoneFields.forEach(field => {
    if (field) formatPhoneNumber(field);
  });

  }); // End DOMContentLoaded handler

})(); // End IIFE
</script>
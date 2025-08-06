<div class="tab-pane fade p-3 border rounded <?=isset($tabErrors['id']) ? 'show active' : ''?>" id="id" role="tabpanel">
    <h5 class="mb-3 text-success">
        <i class="fas fa-id-card"></i> Identification Documents
    </h5>
    
    <!-- National Registration Card Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-id-badge"></i> National Registration Card</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="nrc">NRC Number <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" name="nrc_part1" id="nrc_part1" class="form-control" 
                               maxlength="6" placeholder="123456" value="<?=old('nrc_part1')?>" 
                               pattern="[0-9]{6}" title="6 digits required" required>
                        <span class="input-group-text">/</span>
                        <input type="text" name="nrc_part2" id="nrc_part2" class="form-control" 
                               maxlength="2" placeholder="78" value="<?=old('nrc_part2')?>" 
                               pattern="[0-9]{2}" title="2 digits required" required>
                        <span class="input-group-text">/</span>
                        <input type="text" name="nrc_const" id="nrc_const" class="form-control" 
                               value="1" readonly style="max-width: 60px;">
                    </div>
                    <small class="form-text text-muted">Format: XXXXXX/XX/1</small>
                    <div class="invalid-feedback" id="nrc-error"></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="nrc_issue_date">NRC Issue Date</label>
                    <input type="date" name="nrc_issue_date" id="nrc_issue_date" class="form-control" 
                           value="<?=old('nrc_issue_date')?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Passport Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-passport"></i> Passport Information</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="passport_no">Passport Number</label>
                    <input type="text" name="passport_no" id="passport_no" class="form-control" 
                           maxlength="20" placeholder="ZM1234567" value="<?=old('passport_no')?>" 
                           pattern="[A-Z]{2}[0-9]{7,8}" title="Format: ZM1234567">
                    <small class="form-text text-muted">Format: ZM1234567</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="passport_issue_date">Issue Date</label>
                    <input type="date" name="passport_issue_date" id="passport_issue_date" class="form-control" 
                           value="<?=old('passport_issue_date')?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="passport_expiry_date">Expiry Date</label>
                    <input type="date" name="passport_expiry_date" id="passport_expiry_date" class="form-control" 
                           value="<?=old('passport_expiry_date')?>">
                    <div class="form-text" id="passport-expiry-warning"></div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="passport_place_of_issue">Place of Issue</label>
                    <input type="text" name="passport_place_of_issue" id="passport_place_of_issue" 
                           class="form-control" value="<?=old('passport_place_of_issue')?>" 
                           placeholder="Lusaka">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="passport_country">Issuing Country</label>
                    <select name="passport_country" id="passport_country" class="form-select">
                        <option value="ZM" selected>Zambia</option>
                    </select>
                    <small class="form-text text-muted">All passports issued in Zambia</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Military Identification Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-shield-alt"></i> Military Identification</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="military_id_no">Military ID Number</label>
                    <input type="text" name="military_id_no" id="military_id_no" class="form-control" 
                           maxlength="20" value="<?=old('military_id_no')?>" placeholder="MIL123456">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="military_id_issue_date">Issue Date</label>
                    <input type="date" name="military_id_issue_date" id="military_id_issue_date" 
                           class="form-control" value="<?=old('military_id_issue_date')?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="military_id_expiry_date">Expiry Date</label>
                    <input type="date" name="military_id_expiry_date" id="military_id_expiry_date" 
                           class="form-control" value="<?=old('military_id_expiry_date')?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Physical Characteristics -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-user-circle"></i> Physical Characteristics</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="eye_color">Eye Color</label>
                    <select name="eye_color" id="eye_color" class="form-select">
                        <option value="">Select</option>
                        <option value="brown" <?=selected('eye_color', 'brown')?>>Brown</option>
                        <option value="black" <?=selected('eye_color', 'black')?>>Black</option>
                        <option value="blue" <?=selected('eye_color', 'blue')?>>Blue</option>
                        <option value="green" <?=selected('eye_color', 'green')?>>Green</option>
                        <option value="hazel" <?=selected('eye_color', 'hazel')?>>Hazel</option>
                        <option value="gray" <?=selected('eye_color', 'gray')?>>Gray</option>
                        <option value="other" <?=selected('eye_color', 'other')?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="hair_color">Hair Color</label>
                    <select name="hair_color" id="hair_color" class="form-select">
                        <option value="">Select</option>
                        <option value="black" <?=selected('hair_color', 'black')?>>Black</option>
                        <option value="brown" <?=selected('hair_color', 'brown')?>>Brown</option>
                        <option value="blonde" <?=selected('hair_color', 'blonde')?>>Blonde</option>
                        <option value="red" <?=selected('hair_color', 'red')?>>Red</option>
                        <option value="gray" <?=selected('hair_color', 'gray')?>>Gray</option>
                        <option value="white" <?=selected('hair_color', 'white')?>>White</option>
                        <option value="bald" <?=selected('hair_color', 'bald')?>>Bald</option>
                        <option value="other" <?=selected('hair_color', 'other')?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="height">Height (cm)</label>
                    <input type="number" name="height" id="height" class="form-control" 
                           min="100" max="250" value="<?=old('height')?>" placeholder="175">
                    <small class="form-text text-muted">In centimeters</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label" for="weight">Weight (kg)</label>
                    <input type="number" name="weight" id="weight" class="form-control" 
                           min="30" max="200" value="<?=old('weight')?>" placeholder="70">
                    <small class="form-text text-muted">In kilograms</small>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="distinguishing_marks">Distinguishing Marks/Scars</label>
                    <textarea name="distinguishing_marks" id="distinguishing_marks" class="form-control" 
                              rows="3" placeholder="Describe any visible scars, tattoos, birthmarks, etc."><?=old('distinguishing_marks')?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="complexion">Complexion</label>
                    <select name="complexion" id="complexion" class="form-select">
                        <option value="">Select</option>
                        <option value="fair" <?=selected('complexion', 'fair')?>>Fair</option>
                        <option value="medium" <?=selected('complexion', 'medium')?>>Medium</option>
                        <option value="dark" <?=selected('complexion', 'dark')?>>Dark</option>
                        <option value="very_dark" <?=selected('complexion', 'very_dark')?>>Very Dark</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Other Documents Section -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="fas fa-file-alt"></i> Other Documents</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="drivers_licence_no">Driver's License Number</label>
                    <input type="text" name="drivers_licence_no" id="drivers_licence_no" class="form-control" 
                           maxlength="20" value="<?=old('drivers_licence_no')?>" placeholder="DL123456">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="drivers_licence_expiry">License Expiry Date</label>
                    <input type="date" name="drivers_licence_expiry" id="drivers_licence_expiry" 
                           class="form-control" value="<?=old('drivers_licence_expiry')?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="tpin">Tax Identification Number (TPIN)</label>
                    <input type="text" name="tpin" id="tpin" class="form-control" 
                           maxlength="15" value="<?=old('tpin')?>" placeholder="1001234567">
                    <small class="form-text text-muted">10-digit TPIN number</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label" for="social_security_no">Social Security Number</label>
                    <input type="text" name="social_security_no" id="social_security_no" class="form-control" 
                           maxlength="20" value="<?=old('social_security_no')?>" placeholder="SSN123456">
                </div>
            </div>
        </div>
    </div>

    <!-- Document Upload Section -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-cloud-upload-alt"></i> Document Upload</h6>
            <small class="text-muted">Upload supporting documents (Max: 10MB per file)</small>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 mb-3">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Accepted formats:</strong> PDF, JPG, JPEG, PNG, DOC, DOCX, XLS, XLSX
                        <br><strong>Maximum file size:</strong> 10MB per file
                    </div>
                </div>
            </div>

            <!-- Document Upload Form -->
            <div class="document-upload-section">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="document_type">Document Type</label>
                        <select name="document_type" id="document_type" class="form-select">
                            <option value="">Select document type</option>
                            <option value="photo">Passport Photo</option>
                            <option value="nrc_copy">NRC Copy</option>
                            <option value="passport_copy">Passport Copy</option>
                            <option value="birth_cert">Birth Certificate</option>
                            <option value="education_cert">Education Certificate</option>
                            <option value="medical_report">Medical Report</option>
                            <option value="next_of_kin">Next of Kin Details</option>
                            <option value="cv_resume">CV/Resume</option>
                            <option value="reference_letter">Reference Letter</option>
                            <option value="other">Other Documents</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="document_file">Select File</label>
                        <input type="file" name="document_file" id="document_file" class="form-control" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-primary" id="uploadDocumentBtn" disabled>
                            <i class="fas fa-upload"></i> Upload
                        </button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <div class="progress mb-2">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-muted">Uploading...</small>
                </div>

                <!-- Upload Messages -->
                <div id="uploadMessages"></div>
            </div>

            <!-- Uploaded Documents List -->
            <div class="uploaded-documents mt-4" id="uploadedDocuments">
                <h6 class="border-bottom pb-2">
                    <i class="fas fa-paperclip"></i> Uploaded Documents
                    <span class="badge bg-secondary ms-2" id="documentCount">0</span>
                </h6>
                <div class="document-list" id="documentList">
                    <div class="text-muted text-center py-3" id="noDocumentsMessage">
                        <i class="fas fa-folder-open fa-2x mb-2"></i>
                        <br>No documents uploaded yet
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced Identity Tab Validation and Functionality
document.addEventListener('DOMContentLoaded', function() {
    // NRC Format Validation
    const nrcPart1 = document.getElementById('nrc_part1');
    const nrcPart2 = document.getElementById('nrc_part2');
    
    function validateNRC() {
        const part1 = nrcPart1.value;
        const part2 = nrcPart2.value;
        
        if (part1.length === 6 && part2.length === 2) {
            if (!/^\d{6}$/.test(part1) || !/^\d{2}$/.test(part2)) {
                showFieldError('nrc-error', 'NRC must contain only numbers');
                return false;
            }
            clearFieldError('nrc-error');
            return true;
        }
        return false;
    }
    
    nrcPart1.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 6);
        if (this.value.length === 6) {
            nrcPart2.focus();
        }
        validateNRC();
    });
    
    nrcPart2.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').substring(0, 2);
        validateNRC();
    });
    
    // Passport Number Validation
    const passportNo = document.getElementById('passport_no');
    if (passportNo) {
        passportNo.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
            if (this.value && !/^[A-Z]{2}[0-9]{7,8}$/.test(this.value)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    // Document Expiry Warnings
    function checkDocumentExpiry(inputId, warningId, documentName) {
        const input = document.getElementById(inputId);
        const warning = document.getElementById(warningId);
        
        if (input && warning) {
            input.addEventListener('change', function() {
                if (this.value) {
                    const expiryDate = new Date(this.value);
                    const today = new Date();
                    const sixMonthsFromNow = new Date();
                    sixMonthsFromNow.setMonth(today.getMonth() + 6);
                    
                    if (expiryDate < today) {
                        warning.innerHTML = `<small class="text-danger"><i class="fas fa-exclamation-triangle"></i> ${documentName} has expired</small>`;
                    } else if (expiryDate < sixMonthsFromNow) {
                        warning.innerHTML = `<small class="text-warning"><i class="fas fa-clock"></i> ${documentName} expires soon</small>`;
                    } else {
                        warning.innerHTML = `<small class="text-success"><i class="fas fa-check"></i> ${documentName} is valid</small>`;
                    }
                } else {
                    warning.innerHTML = '';
                }
            });
        }
    }
    
    checkDocumentExpiry('passport_expiry_date', 'passport-expiry-warning', 'Passport');
    
    // Height/Weight validation
    const height = document.getElementById('height');
    const weight = document.getElementById('weight');
    
    if (height) {
        height.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 100 || value > 250) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    if (weight) {
        weight.addEventListener('input', function() {
            const value = parseInt(this.value);
            if (value < 30 || value > 200) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    }
    
    // TPIN validation
    const tpin = document.getElementById('tpin');
    if (tpin) {
        tpin.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
            if (this.value.length === 10) {
                this.classList.remove('is-invalid');
            } else if (this.value.length > 0) {
                this.classList.add('is-invalid');
            }
        });
    }
    
    function showFieldError(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.classList.add('text-danger');
        }
    }
    
    function clearFieldError(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = '';
            element.classList.remove('text-danger');
        }
    }

    // Document Upload Functionality
    const documentTypeSelect = document.getElementById('document_type');
    const documentFileInput = document.getElementById('document_file');
    const uploadBtn = document.getElementById('uploadDocumentBtn');
    const uploadProgress = document.getElementById('uploadProgress');
    const uploadMessages = document.getElementById('uploadMessages');
    const documentList = document.getElementById('documentList');
    const documentCount = document.getElementById('documentCount');
    const noDocumentsMessage = document.getElementById('noDocumentsMessage');

    // Enable/disable upload button
    function checkUploadReadiness() {
        const hasType = documentTypeSelect.value !== '';
        const hasFile = documentFileInput.files.length > 0;
        uploadBtn.disabled = !(hasType && hasFile);
    }

    documentTypeSelect.addEventListener('change', checkUploadReadiness);
    documentFileInput.addEventListener('change', function() {
        checkUploadReadiness();
        validateFileUpload(this);
    });

    // File upload validation
    function validateFileUpload(input) {
        const file = input.files[0];
        if (!file) return;

        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        const fileExtension = file.name.split('.').pop().toLowerCase();

        if (file.size > maxSize) {
            showUploadMessage('File size exceeds 10MB limit', 'danger');
            input.value = '';
            checkUploadReadiness();
            return false;
        }

        if (!allowedTypes.includes(fileExtension)) {
            showUploadMessage('Invalid file type. Allowed: ' + allowedTypes.join(', '), 'danger');
            input.value = '';
            checkUploadReadiness();
            return false;
        }

        clearUploadMessages();
        return true;
    }

    // Handle file upload
    uploadBtn.addEventListener('click', function() {
        if (!validateFileUpload(documentFileInput)) return;

        const formData = new FormData();
        formData.append('document_file', documentFileInput.files[0]);
        formData.append('document_type', documentTypeSelect.value);
        formData.append('action', 'upload_document');
        formData.append('staff_id', '0'); // Will be set when staff is created

        // Show progress
        showUploadProgress();

        // Upload file
        fetch('/Armis2/admin_branch/ajax_file_upload.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            hideUploadProgress();
            
            if (data.success) {
                showUploadMessage('File uploaded successfully!', 'success');
                addDocumentToList(data.file);
                
                // Reset form
                documentTypeSelect.value = '';
                documentFileInput.value = '';
                checkUploadReadiness();
            } else {
                showUploadMessage(data.message || 'Upload failed', 'danger');
            }
        })
        .catch(error => {
            hideUploadProgress();
            showUploadMessage('Upload failed: ' + error.message, 'danger');
        });
    });

    // Show upload progress
    function showUploadProgress() {
        uploadProgress.style.display = 'block';
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    }

    // Hide upload progress
    function hideUploadProgress() {
        uploadProgress.style.display = 'none';
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload';
        checkUploadReadiness();
    }

    // Show upload message
    function showUploadMessage(message, type) {
        uploadMessages.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                clearUploadMessages();
            }, 3000);
        }
    }

    // Clear upload messages
    function clearUploadMessages() {
        uploadMessages.innerHTML = '';
    }

    // Add document to list
    function addDocumentToList(file) {
        if (noDocumentsMessage) {
            noDocumentsMessage.style.display = 'none';
        }

        const documentItem = document.createElement('div');
        documentItem.className = 'document-item border rounded p-3 mb-2';
        documentItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="fas fa-file-${getFileIcon(file.type)} fa-2x text-primary me-3"></i>
                    <div>
                        <h6 class="mb-1">${file.original_name}</h6>
                        <small class="text-muted">
                            ${file.document_type} • ${formatFileSize(file.size)} • 
                            ${new Date().toLocaleDateString()}
                        </small>
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            onclick="viewDocument('${file.secure_filename}')">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="removeDocument(this, '${file.id}')">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
            </div>
        `;

        documentList.appendChild(documentItem);
        updateDocumentCount();
    }

    // Get file icon based on type
    function getFileIcon(fileType) {
        const icons = {
            'pdf': 'pdf',
            'doc': 'word',
            'docx': 'word',
            'xls': 'excel',
            'xlsx': 'excel',
            'jpg': 'image',
            'jpeg': 'image',
            'png': 'image'
        };
        return icons[fileType] || 'file';
    }

    // Format file size
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // View document
    function viewDocument(filename) {
        window.open('/Armis2/shared/download_file.php?file=' + encodeURIComponent(filename), '_blank');
    }

    // Remove document
    function removeDocument(button, fileId) {
        if (!confirm('Are you sure you want to remove this document?')) return;

        const documentItem = button.closest('.document-item');
        
        // For now, just remove from UI (will integrate with backend later)
        documentItem.remove();
        updateDocumentCount();
        
        // Show no documents message if list is empty
        if (documentList.children.length === 0) {
            noDocumentsMessage.style.display = 'block';
        }
    }

    // Update document count
    function updateDocumentCount() {
        const count = documentList.querySelectorAll('.document-item').length;
        documentCount.textContent = count;
    }
});
</script>
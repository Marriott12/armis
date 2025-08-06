<?php
/**
 * Advanced Staff Search System for ARMIS
 * Enhanced search with multiple filters and export capabilities
 */

define('ARMIS_ADMIN_BRANCH', true);
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Advanced Staff Search - ARMIS';
$currentPage = 'search';

// Include the shared header
require_once dirname(__DIR__) . '/shared/header.php';
require_once dirname(__DIR__) . '/shared/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0 text-gray-800">
                    <i class="fas fa-search"></i> Advanced Staff Search
                </h1>
                <p class="text-muted">Search and filter staff records with advanced criteria</p>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" id="searchBtn">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" class="btn btn-outline-secondary" id="clearBtn">
                    <i class="fas fa-eraser"></i> Clear
                </button>
                <div class="btn-group">
                    <button type="button" class="btn btn-success dropdown-toggle" 
                            data-bs-toggle="dropdown" id="exportBtn" disabled>
                        <i class="fas fa-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel (.xlsx)
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-format="csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF Report
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Search Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter"></i> Search Filters
                    <button class="btn btn-sm btn-outline-primary float-end" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#advancedFilters">
                        <i class="fas fa-cog"></i> Advanced
                    </button>
                </h5>
            </div>
            <div class="card-body">
                <form id="searchForm">
                    <!-- Basic Search -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="searchQuery">Search Query</label>
                            <input type="text" class="form-control" id="searchQuery" name="query" 
                                   placeholder="Name, service number, email, or ID number...">
                            <small class="form-text text-muted">
                                Search across multiple fields simultaneously
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="searchType">Search Type</label>
                            <select class="form-select" id="searchType" name="search_type">
                                <option value="contains">Contains</option>
                                <option value="exact">Exact Match</option>
                                <option value="starts_with">Starts With</option>
                                <option value="ends_with">Ends With</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="sortBy">Sort By</label>
                            <select class="form-select" id="sortBy" name="sort_by">
                                <option value="lname">Last Name</option>
                                <option value="fname">First Name</option>
                                <option value="svcNo">Service Number</option>
                                <option value="rankID">Rank</option>
                                <option value="enlistmentDate">Enlistment Date</option>
                                <option value="dateOfBirth">Birth Date</option>
                            </select>
                        </div>
                    </div>

                    <!-- Advanced Filters (Collapsible) -->
                    <div class="collapse" id="advancedFilters">
                        <div class="border-top pt-3">
                            <h6 class="text-primary mb-3">
                                <i class="fas fa-sliders-h"></i> Advanced Filters
                            </h6>
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="filterRank">Rank</label>
                                    <select class="form-select" id="filterRank" name="rank_id">
                                        <option value="">All Ranks</option>
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="filterUnit">Unit</label>
                                    <select class="form-select" id="filterUnit" name="unit_id">
                                        <option value="">All Units</option>
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="filterCorps">Corps</label>
                                    <select class="form-select" id="filterCorps" name="corps">
                                        <option value="">All Corps</option>
                                        <!-- Will be populated by JavaScript -->
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="filterStatus">Status</label>
                                    <select class="form-select" id="filterStatus" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="Active">Active</option>
                                        <option value="On Leave">On Leave</option>
                                        <option value="Training">Training</option>
                                        <option value="Deployed">Deployed</option>
                                        <option value="Retired">Retired</option>
                                        <option value="Deceased">Deceased</option>
                                        <option value="Discharged">Discharged</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="staffByRank">Staff at Selected Rank</label>
                                    <select id="staffByRank" class="form-select" name="staff_by_rank" style="width:100%"></select>
                                    <small class="form-text text-muted">Select a staff member at the chosen rank/unit for promotion/demotion.</small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="filterGender">Gender</label>
                                    <select class="form-select" id="filterGender" name="gender">
                                        <option value="">All Genders</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="filterMarital">Marital Status</label>
                                    <select class="form-select" id="filterMarital" name="marital">
                                        <option value="">All</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Divorced">Divorced</option>
                                        <option value="Widowed">Widowed</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="ageRangeMin">Age Range</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="ageRangeMin" 
                                               name="age_min" placeholder="Min" min="18" max="65">
                                        <span class="input-group-text">to</span>
                                        <input type="number" class="form-control" id="ageRangeMax" 
                                               name="age_max" placeholder="Max" min="18" max="65">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="serviceYears">Service Years</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="serviceYearsMin" 
                                               name="service_min" placeholder="Min" min="0">
                                        <span class="input-group-text">to</span>
                                        <input type="number" class="form-control" id="serviceYearsMax" 
                                               name="service_max" placeholder="Max" min="0">
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="dateRange">Enlistment Date Range</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="enlistmentFrom" 
                                               name="enlistment_from">
                                        <span class="input-group-text">to</span>
                                        <input type="date" class="form-control" id="enlistmentTo" 
                                               name="enlistment_to">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="birthDateRange">Birth Date Range</label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="birthFrom" 
                                               name="birth_from">
                                        <span class="input-group-text">to</span>
                                        <input type="date" class="form-control" id="birthTo" 
                                               name="birth_to">
                                    </div>
                                </div>
                            </div>

                            <!-- Quick Filter Buttons -->
                            <div class="row mb-3">
                                <div class="col-12">
                                    <label class="form-label">Quick Filters</label>
                                    <div class="btn-group-toggle" data-toggle="buttons">
                                        <button type="button" class="btn btn-outline-primary btn-sm me-2" 
                                                data-filter="officers">Officers Only</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-2" 
                                                data-filter="ncos">NCOs Only</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-2" 
                                                data-filter="enlisted">Enlisted Only</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" 
                                                data-filter="recent">Recent Enlistments</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" 
                                                data-filter="retirement_eligible">Retirement Eligible</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Results -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> Search Results
                        <span class="badge bg-primary ms-2" id="resultCount">0</span>
                    </h5>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="viewModeGrid">
                        <i class="fas fa-th"></i> Grid
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary active" id="viewModeTable">
                        <i class="fas fa-table"></i> Table
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- Loading Indicator -->
                <div class="text-center p-5" id="loadingIndicator" style="display: none;">
                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                    <p class="mt-2 text-muted">Searching...</p>
                </div>

                <!-- No Results Message -->
                <div class="text-center p-5" id="noResults" style="display: none;">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No results found</h5>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>

                <!-- Results Table -->
                <div class="table-responsive" id="resultsTable" style="display: none;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Service No.</th>
                                <th>Rank</th>
                                <th>Unit</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="resultsTableBody">
                            <!-- Results will be populated here -->
                        </tbody>
                    </table>
                </div>

                <!-- Results Grid -->
                <div class="row p-3" id="resultsGrid" style="display: none;">
                    <!-- Grid cards will be populated here -->
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center p-3 border-top" 
                     id="paginationContainer" style="display: none;">
                    <div>
                        <small class="text-muted">
                            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> 
                            of <span id="totalResults">0</span> results
                        </small>
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination">
                            <!-- Pagination will be populated here -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Select an action to perform on <span id="selectedCount">0</span> selected staff members:</p>
                <div class="list-group">
                    <button type="button" class="list-group-item list-group-item-action" data-action="export">
                        <i class="fas fa-download"></i> Export Selected
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" data-action="promote">
                        <i class="fas fa-arrow-up"></i> Bulk Promotion
                    </button>
                    <button type="button" class="list-group-item list-group-item-action" data-action="transfer">
                        <i class="fas fa-exchange-alt"></i> Bulk Transfer
                    </button>
                    <button type="button" class="list-group-item list-group-item-action text-danger" data-action="status">
                        <i class="fas fa-edit"></i> Change Status
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= ADMIN_BRANCH_URL ?>/js/advanced-search.js"></script>

<?php require_once dirname(__DIR__) . '/shared/footer.php'; ?>

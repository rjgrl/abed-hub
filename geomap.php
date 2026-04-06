<?php
session_start();
require_once 'config/database.php';

requireLogin();

$page_title = 'GeoMap';

// Get all projects with coordinates
$projects = [];

// Get FSPF projects
$stmt = $conn->prepare("
    SELECT 'FSPF' as type, id, project_code, project_title, current_stage, latitude, longitude,
           allocated_amount, municipality, province
    FROM fspf_projects
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");
$stmt->execute();
$fspf_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get IDP projects
$stmt = $conn->prepare("
    SELECT 'IDP' as type, id, project_code, project_title, current_stage, latitude, longitude,
           allocated_amount, municipality, province
    FROM idp_projects
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");
$stmt->execute();
$idp_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get AFME projects
$stmt = $conn->prepare("
    SELECT 'AFME' as type, id, project_code, project_title, current_stage, latitude, longitude,
           allocated_amount, implementing_office AS municipality, source_agency AS province
    FROM afme_projects
    WHERE latitude IS NOT NULL AND longitude IS NOT NULL
");
$stmt->execute();
$afme_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combine all projects
$projects = array_merge($fspf_projects, $idp_projects, $afme_projects);
?>
<?php
require_once __DIR__ . '/components/layout.php';
$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />\n'
    . '<style>#map{height:600px;width:100%;}.project-marker{border-radius:50%;width:20px;height:20px;border:2px solid white;box-shadow:0 0 4px rgba(0,0,0,0.3);} .fspf-marker{background-color:#007bff;} .idp-marker{background-color:#28a745;} .afme-marker{background-color:#ffc107;}</style>';
renderAppLayout($page_title, $extra_head);
?>
        <div class="container-fluid">
        <!-- Map Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0"><i class="fas fa-map-marked-alt me-2"></i><?php echo htmlspecialchars($page_title ?? 'GeoMap'); ?></h4>
                                <small>View all projects with coordinates on an interactive map</small>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark"><?php echo count($projects); ?> Projects Mapped</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Map Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter by Type</label>
                                <select class="form-control" id="typeFilter">
                                    <option value="all">All Types</option>
                                    <option value="FSPF">FSPF Projects</option>
                                    <option value="IDP">IDP Projects</option>
                                    <option value="AFME">AFME Machinery</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filter by Status</label>
                                <select class="form-control" id="statusFilter">
                                    <option value="all">All Status</option>
                                    <option value="Proposal">Proposal</option>
                                    <option value="Pre-Implementation">Pre-Implementation</option>
                                    <option value="Procurement">Procurement</option>
                                    <option value="Implementation">Implementation</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Legend</label>
                                <div class="d-flex gap-3">
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker fspf-marker me-2"></div>
                                        <small>FSPF Projects</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker idp-marker me-2"></div>
                                        <small>IDP Projects</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker afme-marker me-2"></div>
                                        <small>AFME Machinery</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Map Container -->
                        <div id="map"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Mapped Projects</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="projectsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Type</th>
                                        <th>Project Code</th>
                                        <th>Project Title</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                        <th>Budget</th>
                                        <th>Coordinates</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                    <tr data-type="<?php echo $project['type']; ?>" data-status="<?php echo $project['current_stage']; ?>">
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($project['type']) {
                                                    'FSPF' => 'primary',
                                                    'IDP' => 'success',
                                                    'AFME' => 'warning',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo $project['type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['project_code']); ?></td>
                                        <td><?php echo htmlspecialchars($project['project_title']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($project['current_stage']) {
                                                    'Proposal' => 'warning',
                                                    'Pre-Implementation' => 'info',
                                                    'Procurement' => 'secondary',
                                                    'Implementation' => 'success',
                                                    'Completed' => 'success',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo htmlspecialchars($project['current_stage']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($project['municipality'] . ', ' . $project['province']); ?></td>
                                        <td>₱<?php echo number_format($project['allocated_amount'] ?? 0, 2); ?></td>
                                        <td><?php echo $project['latitude'] . ', ' . $project['longitude']; ?></td>
                                        <td>
                                            <a href="<?php 
                                                echo $project['type'] === 'AFME' 
                                                    ? 'afme-machinery-details.php?id=' . $project['id']
                                                    : 'project-details.php?type=' . $project['type'] . '&id=' . $project['id'];
                                            ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No projects with coordinates found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Initialize map centered on Bukidnon, Philippines
            const map = L.map('map').setView([8.1542, 125.1207], 9);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Project data from PHP
            const projects = <?php echo json_encode($projects); ?>;

            // Create markers
            const markers = [];
            projects.forEach(project => {
                if (project.latitude && project.longitude) {
                    const markerClass = project.type.toLowerCase() + '-marker';
                    const markerColor = project.type === 'FSPF' ? '#007bff' : 
                                       project.type === 'IDP' ? '#28a745' : '#ffc107';

                    const marker = L.circleMarker([project.latitude, project.longitude], {
                        color: markerColor,
                        fillColor: markerColor,
                        fillOpacity: 0.8,
                        radius: 8,
                        weight: 2
                    }).addTo(map);

                    // Popup content
                    const popupContent = `
                        <div class="text-center">
                            <h6>${project.project_title}</h6>
                            <p class="mb-1"><strong>Code:</strong> ${project.project_code}</p>
                            <p class="mb-1"><strong>Type:</strong> ${project.type}</p>
                            <p class="mb-1"><strong>Status:</strong> ${project.current_stage}</p>
                            <p class="mb-1"><strong>Budget:</strong> ₱${Number(project.allocated_amount || 0).toLocaleString()}</p>
                            <p class="mb-1"><strong>Location:</strong> ${project.municipality}, ${project.province}</p>
                            <a href="${project.type === 'AFME' ? 'afme-machinery-details.php?id=' + project.id : 'project-details.php?type=' + project.type + '&id=' + project.id}" class="btn btn-sm btn-primary">View Details</a>
                        </div>
                    `;

                    marker.bindPopup(popupContent);
                    markers.push({
                        marker: marker,
                        type: project.type,
                        status: project.current_stage
                    });
                }
            });

            // Filter functionality
            function filterMarkers() {
                const typeFilter = document.getElementById('typeFilter').value;
                const statusFilter = document.getElementById('statusFilter').value;

                markers.forEach(item => {
                    const show = (typeFilter === 'all' || item.type === typeFilter) &&
                               (statusFilter === 'all' || item.status === statusFilter);
                    if (show) {
                        map.addLayer(item.marker);
                    } else {
                        map.removeLayer(item.marker);
                    }
                });

                // Filter table rows
                const rows = document.querySelectorAll('#projectsTable tbody tr');
                rows.forEach(row => {
                    if (row.cells.length > 1) { // Skip "no projects" row
                        const rowType = row.getAttribute('data-type');
                        const rowStatus = row.getAttribute('data-status');
                        const show = (typeFilter === 'all' || rowType === typeFilter) &&
                                   (statusFilter === 'all' || rowStatus === statusFilter);
                        row.style.display = show ? '' : 'none';
                    }
                });
            }

            // Add event listeners to filters
            document.getElementById('typeFilter').addEventListener('change', filterMarkers);
            document.getElementById('statusFilter').addEventListener('change', filterMarkers);

            // Fit map to show all markers
            if (markers.length > 0) {
                const group = new L.featureGroup(markers.map(item => item.marker));
                map.fitBounds(group.getBounds().pad(0.1));
            }
        });
    </script>
<?php renderAppLayoutFooter(); ?>


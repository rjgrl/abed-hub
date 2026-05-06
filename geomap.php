<?php
session_name('ABED_IDM_HUB');
session_start();
require_once 'config/database.php';

requireLogin();

$page_title = 'Geo Map';

// Get all projects with coordinates
$projects = [];

$stmt = $conn->prepare("
    SELECT UPPER(project_type) as type, id, project_code, title AS project_title, current_stage, latitude, longitude,
           allocated_amount, municipality, province
    FROM projects
    WHERE approval_status = 'Approved'
      AND latitude IS NOT NULL AND longitude IS NOT NULL
      AND NOT (latitude = 0 AND longitude = 0)
      AND latitude BETWEEN 4.2 AND 21.7 AND longitude BETWEEN 116.0 AND 127.6
");
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<?php
require_once __DIR__ . '/components/layout.php';
$extra_head = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
renderAppLayout($page_title, $extra_head);
?>
        <div class="container-fluid py-4">
            <div class="row mb-4 align-items-start">
                <div class="col">
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($page_title ?? 'Geo Map'); ?></h1>
                    <p class="text-muted mb-0">Philippines only — markers use coordinates saved when registering Farm Structure and Processing Facilities, Irrigation Development Projects, or Agricultural and Fisheries Machineries and Equipment projects</p>
                </div>
                <div class="col-auto pt-1">
                    <span class="badge rounded-pill text-bg-light border"><?php echo count($projects); ?> projects mapped</span>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                        <!-- Map Filters -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Filter by Type</label>
                                <select class="form-control" id="typeFilter">
                                    <option value="all">All Types</option>
                                    <option value="FSPF">Farm Structure and Processing Facilities Projects</option>
                                    <option value="IDP">Irrigation Development Projects</option>
                                    <option value="AFME">Agricultural and Fisheries Machineries and Equipment Projects</option>
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
                                <label class="form-label">Legend <span class="text-muted fw-normal">(project type)</span></label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker fspf-marker me-2"></div>
                                        <small>Farm Structure and Processing Facilities Projects</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker idp-marker me-2"></div>
                                        <small>Irrigation Development Projects</small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="project-marker afme-marker me-2"></div>
                                        <small>Agricultural and Fisheries Machineries and Equipment Projects</small>
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
                <div class="card border-0 shadow-sm">
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
                                                <?php
                                                echo match($project['type']) {
                                                    'FSPF' => 'Farm Structure and Processing Facilities',
                                                    'IDP' => 'Irrigation Development Projects',
                                                    'AFME' => 'Agricultural and Fisheries Machineries and Equipment',
                                                    default => htmlspecialchars((string) $project['type']),
                                                };
                                                ?>
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
                                                    : 'project-detail-enhanced.php?type=' . $project['type'] . '&id=' . $project['id'];
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
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const PH_BOUNDS = L.latLngBounds([4.2, 116.0], [21.7, 127.6]);
            const PH_CENTER = [12.5, 122.5];

            const map = L.map('map', {
                maxBounds: PH_BOUNDS.pad(0.12),
                minZoom: 5,
                maxZoom: 18,
                maxBoundsViscosity: 0.85,
                attributionControl: false
            }).setView(PH_CENTER, 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: ''
            }).addTo(map);

            // Project data from PHP
            const projects = <?php echo json_encode($projects); ?>;
            const projectTypeLabels = {
                FSPF: 'Farm Structure and Processing Facilities',
                IDP: 'Irrigation Development Projects',
                AFME: 'Agricultural and Fisheries Machineries and Equipment'
            };

            // Create markers
            const markers = [];
            projects.forEach(project => {
                if (project.latitude && project.longitude) {
                    const markerClass = project.type.toLowerCase() + '-marker';
                    const markerColor = project.type === 'FSPF' ? '#5b8def' : 
                                       project.type === 'IDP' ? '#c694f9' : '#f5c57a';

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
                            <p class="mb-1"><strong>Type:</strong> ${projectTypeLabels[project.type] || project.type}</p>
                            <p class="mb-1"><strong>Status:</strong> ${project.current_stage}</p>
                            <p class="mb-1"><strong>Budget:</strong> ₱${Number(project.allocated_amount || 0).toLocaleString()}</p>
                            <p class="mb-1"><strong>Location:</strong> ${project.municipality}, ${project.province}</p>
                            <a href="${project.type === 'AFME' ? 'afme-machinery-details.php?id=' + project.id : 'project-detail-enhanced.php?type=' + project.type + '&id=' + project.id}" class="btn btn-sm btn-primary">View Details</a>
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

            function clampBoundsToPhilippines(bounds) {
                const sw = bounds.getSouthWest();
                const ne = bounds.getNorthEast();
                const swClamped = L.latLng(
                    Math.max(sw.lat, PH_BOUNDS.getSouth()),
                    Math.max(sw.lng, PH_BOUNDS.getWest())
                );
                const neClamped = L.latLng(
                    Math.min(ne.lat, PH_BOUNDS.getNorth()),
                    Math.min(ne.lng, PH_BOUNDS.getEast())
                );
                return L.latLngBounds(swClamped, neClamped);
            }

            // Fit map to markers, always staying within the Philippines
            if (markers.length > 0) {
                const group = new L.featureGroup(markers.map(item => item.marker));
                let b = clampBoundsToPhilippines(group.getBounds().pad(0.12));
                if (b.isValid()) {
                    map.fitBounds(b, { maxZoom: 14, padding: [28, 28] });
                } else {
                    map.fitBounds(PH_BOUNDS, { padding: [20, 20] });
                }
            } else {
                map.fitBounds(PH_BOUNDS, { padding: [12, 12] });
            }
        });
    </script>
<?php renderAppLayoutFooter(); ?>


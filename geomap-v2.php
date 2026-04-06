<?php
require_once __DIR__ . '/components/layout.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/functions/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get filter parameters
$project_type = $_GET['type'] ?? 'all';
$year = $_GET['year'] ?? date('Y');

// Fetch projects with coordinates (those that have latitude/longitude)
$projects_data = [];

$queries = [];

if ($project_type === 'all' || $project_type === 'fspf') {
    $queries[] = "SELECT 'FSPF' as type, id, project_code, project_title, 
                         municipality, latitude, longitude, current_stage as stage,
                         proposed_amount, allocated_amount
                  FROM fspf_projects 
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
}

if ($project_type === 'all' || $project_type === 'idp') {
    $queries[] = "SELECT 'IDP' as type, id, project_code, project_title, 
                         municipality, latitude, longitude, current_stage as stage,
                         proposed_amount, allocated_amount
                  FROM idp_projects 
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
}

if ($project_type === 'all' || $project_type === 'afme') {
    $queries[] = "SELECT 'AFME' as type, id, project_code, project_title, 
                         municipality, latitude, longitude, current_stage as stage,
                         proposed_amount, allocated_amount
                  FROM afme_projects 
                  WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
}

if (!empty($queries)) {
    $query = implode(' UNION ', $queries);
    if ($year) {
        $year = (int)$year;
        $query = "SELECT * FROM ($query) as temp WHERE YEAR(STR_TO_DATE(STR_CONCAT(year, '-01-01'), '%Y-%m-%d')) = $year OR year = ''";
    }
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $projects_data[] = [
                'type' => $row['type'],
                'id' => $row['id'],
                'code' => $row['project_code'],
                'title' => $row['project_title'],
                'location' => $row['municipality'],
                'lat' => floatval($row['latitude']),
                'lng' => floatval($row['longitude']),
                'stage' => $row['stage'],
                'proposed' => floatval($row['proposed_amount'] ?? 0),
                'allocated' => floatval($row['allocated_amount'] ?? 0)
            ];
        }
    }
}

// Get unique years
$years_list = $conn->query("
    SELECT DISTINCT YEAR(created_date) as year FROM fspf_projects WHERE latitude IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(created_date) as year FROM idp_projects WHERE latitude IS NOT NULL
    UNION
    SELECT DISTINCT YEAR(created_date) as year FROM afme_projects WHERE latitude IS NOT NULL
    ORDER BY year DESC
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoMap - ABED IDM Hub</title>
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <style>
        .map-container {
            height: 600px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .legend {
            background: white;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            font-size: 14px;
            line-height: 24px;
        }
        .legend span {
            width: 18px;
            height: 18px;
            float: left;
            margin-right: 8px;
            border-radius: 50%;
        }
        .legend-fspf { background: #0d6efd; }
        .legend-idp { background: #0dcaf0; }
        .legend-afme { background: #ffc107; }
        .info-panel {
            background: white;
            border-radius: 4px;
            padding: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            max-height: 300px;
            overflow-y: auto;
        }
        .project-marker {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            border-radius: 50%;
            font-weight: bold;
            border: 2px solid white;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .project-marker:hover {
            transform: scale(1.2);
        }
        .popup-content {
            font-size: 13px;
        }
        .popup-content strong {
            color: #0d6efd;
        }
        .popup-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            color: white;
            font-size: 11px;
            margin: 2px 2px 2px 0;
        }
    </style>
</head>
<body>
    <?php include 'components/sidebar.php'; ?>
    <?php include 'components/topbar.php'; ?>
    <?php include 'components/navbar.php'; ?>

    <main class="app-main">
        <div class="container-fluid py-4">
            <!-- Page Header -->
            <div class="row align-items-center mb-4">
                <div class="col">
                    <h1 class="h2">
                        <i class="fas fa-map"></i> Geographic Map
                    </h1>
                    <p class="text-muted">Visualize projects on the map by location</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary" onclick="map.setView([12.5, 121.5], 6)">
                        <i class="fas fa-home"></i> Philippines
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Project Type</label>
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $project_type === 'all' ? 'selected' : ''; ?>>All Types</option>
                                <option value="fspf" <?php echo $project_type === 'fspf' ? 'selected' : ''; ?>>FSPF</option>
                                <option value="idp" <?php echo $project_type === 'idp' ? 'selected' : ''; ?>>IDP</option>
                                <option value="afme" <?php echo $project_type === 'afme' ? 'selected' : ''; ?>>AFME</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Year</label>
                            <select name="year" class="form-select" onchange="this.form.submit()">
                                <option value="">All Years</option>
                                <?php
                                while ($row = $years_list->fetch_assoc()) {
                                    $selected = $year == $row['year'] ? 'selected' : '';
                                    echo "<option value='{$row['year']}' $selected>{$row['year']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="clearMapFilter()">
                                <i class="fas fa-redo"></i> Reset View
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Map -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="map-container" id="geomap"></div>
            </div>

            <!-- Projects List -->
            <?php if (!empty($projects_data)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h6 class="mb-0">Projects on Map (<?php echo count($projects_data); ?> total)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php
                        foreach ($projects_data as $project):
                            $color = match($project['type']) {
                                'FSPF' => '#0d6efd',
                                'IDP' => '#0dcaf0',
                                'AFME' => '#ffc107',
                            };
                            $text_color = $project['type'] === 'AFME' ? 'black' : 'white';
                            ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100" style="border-left: 4px solid <?php echo $color; ?>">
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="badge" style="background-color: <?php echo $color; ?>; color: <?php echo $text_color; ?>">
                                                <?php echo $project['type']; ?>
                                            </span>
                                            <span class="badge bg-info"><?php echo $project['stage']; ?></span>
                                        </div>
                                        <h6 class="card-title"><?php echo htmlspecialchars($project['code']); ?></h6>
                                        <p class="card-text small text-muted">
                                            <?php echo substr(htmlspecialchars($project['title']), 0, 80); ?>
                                        </p>
                                        <div class="small mb-2">
                                            <strong><i class="fas fa-map-marker-alt"></i></strong>
                                            <?php echo htmlspecialchars($project['location']); ?><br>
                                            <strong><i class="fas fa-coordinates"></i></strong>
                                            <?php echo number_format($project['lat'], 4); ?>, 
                                            <?php echo number_format($project['lng'], 4); ?>
                                        </div>
                                        <div class="small text-muted mb-3">
                                            <strong>Proposed:</strong> ₱<?php echo number_format($project['proposed'], 0); ?><br>
                                            <strong>Allocated:</strong> ₱<?php echo number_format($project['allocated'], 0); ?>
                                        </div>
                                        <a href="project-details.php?type=<?php echo strtolower($project['type']); ?>&id=<?php echo $project['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary w-100">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle"></i> No projects found with geographic coordinates for the selected filter.
                <br><small>Projects must have latitude/longitude values to display on the map.</small>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize map
        const map = L.map('geomap').setView([12.5, 121.5], 6);

        // Add tile layers
        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        });

        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; Esri',
            maxZoom: 18
        });

        osmLayer.addTo(map);

        // Layer control
        L.control.layers({
            'Street Map': osmLayer,
            'Satellite': satelliteLayer
        }).addTo(map);

        // Add legend
        const legend = L.control({ position: 'bottomright' });
        legend.onAdd = function() {
            const div = L.DomUtil.create('div', 'legend');
            const types = [
                { type: 'FSPF', color: '#0d6efd', label: 'FSPF Projects' },
                { type: 'IDP', color: '#0dcaf0', label: 'IDP Projects' },
                { type: 'AFME', color: '#ffc107', label: 'AFME Projects' }
            ];

            let html = '<strong>Project Types</strong><br>';
            types.forEach(t => {
                html += `<span class="legend-${t.type.toLowerCase()}" style="background: ${t.color}"></span>${t.label}<br>`;
            });

            div.innerHTML = html;
            return div;
        };
        legend.addTo(map);

        // Project markers
        const projectsData = <?php echo json_encode($projects_data); ?>;
        const markerCluster = L.markerClusterGroup();

        projectsData.forEach(project => {
            const color = {
                'FSPF': '#0d6efd',
                'IDP': '#0dcaf0',
                'AFME': '#ffc107'
            }[project.type];

            const textColor = project.type === 'AFME' ? 'black' : 'white';

            const marker = L.marker([project.lat, project.lng], {
                icon: L.divIcon({
                    html: `<div class="project-marker" style="background-color: ${color}; color: ${textColor};">
                           <i class="fas fa-${project.type === 'FSPF' ? 'leaf' : project.type === 'IDP' ? 'water' : 'cog'}"></i>
                           </div>`,
                    iconSize: [32, 32],
                    className: ''
                })
            });

            const popupHtml = `
                <div class="popup-content">
                    <strong>${escapeHtml(project.code)}</strong><br>
                    <small>${escapeHtml(project.title)}</small><br><br>
                    <div>
                        <span class="popup-badge" style="background: ${color}; color: ${textColor};">${project.type}</span>
                        <span class="popup-badge" style="background: #6c757d;">Stage: ${project.stage}</span>
                    </div><br>
                    <strong>Location:</strong> ${escapeHtml(project.location)}<br>
                    <strong>Budget:</strong> ₱${formatNumber(project.proposed)}<br>
                    <strong>Allocated:</strong> ₱${formatNumber(project.allocated)}<br><br>
                    <a href="project-details.php?type=${project.type.toLowerCase()}&id=${project.id}" 
                       class="btn btn-xs btn-primary btn-sm" style="width: 100%;">
                        View Details
                    </a>
                </div>
            `;

            marker.bindPopup(popupHtml, { maxWidth: 300 });
            markerCluster.addLayer(marker);
        });

        map.addLayer(markerCluster);

        // Fit bounds to markers if any
        if (projectsData.length > 0) {
            const group = new L.featureGroup(markerCluster.getLayers());
            map.fitBounds(group.getBounds().pad(0.1), { maxZoom: 8 });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('en-PH').format(num);
        }

        function clearMapFilter() {
            window.location.href = 'geomap.php';
        }
    </script>
</body>
</html>

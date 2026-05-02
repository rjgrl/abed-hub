<?php
session_name('ABED_IDM_HUB');
session_start();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>The Team — ABED IDM Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  </head>
  <body class="public-home team-page">
    <header class="public-header">
      <div class="container">
        <nav class="navbar navbar-expand-lg py-3 align-items-lg-center public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2 gap-sm-3 public-navbar-brand" href="index.php">
            <img src="logos/abed_logo.png" alt="ABED IDM Hub" class="public-brand-logo flex-shrink-0" />
            <span class="public-brand-text d-flex flex-column lh-sm text-start">
              <span class="public-brand-title fw-bold text-white">ABED Integrated Data Management Hub</span>
              <span class="public-brand-subtitle small text-white-50">Agricultural and Biosystems Engineering Division - LGU Malaybalay City</span>
            </span>
          </a>

          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse align-items-lg-center" id="publicNav">
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0">
              <li class="nav-item"><a class="nav-link" href="index.php#overview">Overview</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#projects">Projects</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#uploads">Uploads</a></li>
              <li class="nav-item"><a class="nav-link active" href="team.php">Meet Our Team</a></li>
              <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
            </ul>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 public-nav-utils">
              <?php if (!isset($_SESSION['user_id'])): ?>
              <a href="login.php" class="btn btn-outline-primary btn-sm public-nav-btn">Login</a>
              <a href="signup.php" class="btn btn-primary btn-sm public-nav-btn">Sign Up</a>
              <?php else: ?>
              <a href="dashboard-enhanced.php" class="btn btn-primary btn-sm public-nav-btn">Dashboard</a>
              <?php endif; ?>
              <a href="contact.php" class="nav-link py-0 public-nav-extra-link">Contact</a>
              <button type="button" class="btn btn-light btn-sm px-3 public-nav-btn" aria-label="Current language">EN</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main>
      <section class="team-showcase" aria-labelledby="team-hero-title">
        <div class="container position-relative team-showcase-container">
          <header class="team-showcase-header team-reveal" data-reveal>
            <div class="team-showcase-header-copy">
              <h1 id="team-hero-title" class="team-page-title">Meet Our Team</h1>
              <p class="team-lead text-muted mb-0">
                We are a small, multidisciplinary group focused on clarity, reliability, and inclusive collaboration.
                Together we design, build, and document solutions that support transparent infrastructure and data stewardship for our community.
              </p>
              <a href="#about-project" class="btn btn-outline-primary team-read-more-btn">Read More</a>
            </div>
          </header>

          <div
            class="team-member-grid team-reveal"
            data-reveal
            aria-label="Team member photos and roles"
          >
              <article class="team-card">
                <div class="team-card-image-wrap">
                  <img
                    class="team-card-image"
                    src="logos/pretz.jpg"
                    width="480"
                    height="560"
                    alt="Portrait of Fritz Carl Jan Gamot"
                  />
                </div>
                <div class="team-card-body">
                  <h3 class="team-card-name h5 mb-1">Fritz Carl Jan Gamot</h3>
                  <p class="team-card-role text-muted small mb-0">Team Leader</p>
                </div>
              </article>
              <article class="team-card">
                <div class="team-card-image-wrap">
                  <img
                    class="team-card-image"
                    src="logos/raymund.jpg"
                    width="480"
                    height="560"
                    alt="Portrait of Raymund John Gil Luzon"
                  />
                </div>
                <div class="team-card-body">
                  <h3 class="team-card-name h5 mb-1">Raymund John Gil Luzon</h3>
                  <p class="team-card-role text-muted small mb-0">Co-Team Leader &amp; Programmer</p>
                </div>
              </article>
              <article class="team-card">
                <div class="team-card-image-wrap">
                  <img
                    class="team-card-image"
                    src="logos/glyn.jpg"
                    width="480"
                    height="560"
                    alt="Portrait of Glyn Yohann Pecson"
                  />
                </div>
                <div class="team-card-body">
                  <h3 class="team-card-name h5 mb-1">Glyn Yohann Pecson</h3>
                  <p class="team-card-role text-muted small mb-0">UI/UX Designer</p>
                </div>
              </article>
              <article class="team-card">
                <div class="team-card-image-wrap">
                  <img
                    class="team-card-image"
                    src="logos/binsoy.jpg"
                    width="480"
                    height="560"
                    alt="Portrait of Vincent Capacio"
                  />
                </div>
                <div class="team-card-body">
                  <h3 class="team-card-name h5 mb-1">Vincent Capacio</h3>
                  <p class="team-card-role text-muted small mb-0">Documenter</p>
                </div>
              </article>
          </div>
        </div>
      </section>

      <div class="team-section-divider container team-reveal" data-reveal role="presentation"></div>

      <section class="team-about-project py-5 public-section-surface" id="about-project" aria-labelledby="about-project-title">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-9">
              <h2 id="about-project-title" class="h4 mb-3 team-reveal" data-reveal>About the Project</h2>
              <p class="team-prose text-muted mb-0 team-reveal" data-reveal>
               The ABED IDM Hub - Malaybalay City is a comprehensive management and monitoring system designed to track the lifecycle of 
               various agricultural projects, specifically Farm Structure and Processing Facilities (FSPF), Irrigation Development Projects (IDPs), 
               and Agricultural and Fisheries Machineries and Equipment (AFME). The platform facilitates project oversight through distinct stages, 
               including proposal, validation, procurement, implementation, and final turnover or completion. Key features include interactive dashboards 
               for monitoring physical and financial progress, S-curve generation for tracking accomplishments, and a GeoMap for spatial visualization of projects. Additionally, 
               the system serves as a central repository for essential documentation, such as geotagged photos, validation reports, and Program of Works (POW), ensuring accountability and efficient maintenance audits.  
              </p>
            </div>
          </div>
        </div>
      </section>

      <section class="team-about-us py-5" aria-labelledby="about-us-title">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-9">
              <h2 id="about-us-title" class="h4 mb-4 team-reveal" data-reveal>About Us</h2>
              <div class="row g-4 align-items-start">
                <div class="col-md-4 team-reveal" data-reveal>
                  <div class="team-about-icon mb-2" aria-hidden="true"><i class="fas fa-users"></i></div>
                  <h3 class="h6">Collaboration</h3>
                  <p class="text-muted small mb-0">
                    We pair domain context with engineering discipline—listening first, shipping iteratively, and reviewing work together.
                  </p>
                </div>
                <div class="col-md-4 team-reveal" data-reveal>
                  <div class="team-about-icon mb-2" aria-hidden="true"><i class="fas fa-layer-group"></i></div>
                  <h3 class="h6">Skills</h3>
                  <p class="text-muted small mb-0">
                    Our strengths span coordination, full-stack development, product design, and technical writing—balanced for real-world delivery.
                  </p>
                </div>
                <div class="col-md-4 team-reveal" data-reveal>
                  <div class="team-about-icon mb-2" aria-hidden="true"><i class="fas fa-compass"></i></div>
                  <h3 class="h6">Vision</h3>
                  <p class="text-muted small mb-0">
                    We envision services that are understandable at a glance, auditable when it matters, and welcoming to every stakeholder we serve.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="public-footer py-4 mt-5">
      <div class="container">
        <div class="row">
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">ABED IDM Hub</h6>
            <p class="small text-muted">Infrastructure Development Management System</p>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Quick Links</h6>
            <ul class="list-unstyled small">
              <li><a href="team.php" class="text-muted text-decoration-none">Meet Our Team</a></li>
              <li><a href="contact.php" class="text-muted text-decoration-none">Contact</a></li>
              <li><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Contact</h6>
            <p class="small text-muted">
              Malaybalay City, Bukidnon<br>
              Email: <a href="mailto:placeholder.abed.idm@example.com" class="text-muted">placeholder.abed.idm@example.com</a>
            </p>
          </div>
        </div>
        <hr class="my-3 border-secondary border-opacity-25" />
        <div class="text-center text-muted small">
          <p>&copy; 2026 ABED IDM Hub. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
      (function () {
        var nodes = document.querySelectorAll(".team-reveal[data-reveal]");
        if (!nodes.length || !("IntersectionObserver" in window)) {
          nodes.forEach(function (el) {
            el.classList.add("is-visible");
          });
          return;
        }
        var io = new IntersectionObserver(
          function (entries) {
            entries.forEach(function (entry) {
              if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                io.unobserve(entry.target);
              }
            });
          },
          { root: null, rootMargin: "0px 0px -8% 0px", threshold: 0.08 }
        );
        nodes.forEach(function (el) {
          io.observe(el);
        });
      })();
    </script>
  </body>
</html>

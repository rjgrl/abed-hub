<?php
session_name('ABED_IDM_HUB');
session_start();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contact — ABED IDM Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  </head>
  <body class="public-home contact-page">
    <header class="public-header">
      <div class="container">
        <nav class="navbar navbar-expand-lg py-3 public-navbar" aria-label="Public navigation">
          <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
            <img src="logos/abed_logo.png" alt="ABED IDM Hub" class="public-brand-logo" />
            <span class="fw-bold d-none d-sm-inline">ABED IDM Hub</span>
          </a>

          <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
          </button>

          <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0">
              <li class="nav-item"><a class="nav-link" href="index.php#overview">Overview</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#projects">Projects</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#uploads">Uploads</a></li>
              <li class="nav-item"><a class="nav-link" href="team.php">Meet Our Team</a></li>
              <li class="nav-item"><a class="nav-link active" href="contact.php" aria-current="page">Contact</a></li>
            </ul>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 gap-lg-3 public-nav-utils">
              <?php if (!isset($_SESSION['user_id'])): ?>
              <a href="login.php" class="btn btn-outline-primary btn-sm">Login</a>
              <a href="signup.php" class="btn btn-primary btn-sm">Sign Up</a>
              <?php else: ?>
              <a href="dashboard-enhanced.php" class="btn btn-primary btn-sm">Dashboard</a>
              <?php endif; ?>
              <a href="contact.php" class="nav-link p-0">Contact</a>
              <button type="button" class="btn btn-light btn-sm px-3" aria-label="Current language">EN</button>
            </div>
          </div>
        </nav>
      </div>
    </header>

    <main class="contact-main" id="contact">
      <section class="py-5 public-section-surface">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-8">
              <h1 class="contact-page-title mb-3">Contact</h1>
              <p class="text-muted mb-4">
                For inquiries about the ABED Integrated Data Management Hub, reach us using the details below. This page uses a temporary email address you can replace when your official contact is ready.
              </p>
              <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">Get in touch</h2>
                  <dl class="row mb-0 small">
                    <dt class="col-sm-3 text-muted">Location</dt>
                    <dd class="col-sm-9">Malaybalay City, Bukidnon</dd>
                    <dt class="col-sm-3 text-muted">Email</dt>
                    <dd class="col-sm-9">
                      <a href="mailto:placeholder.abed.idm@example.com">placeholder.abed.idm@example.com</a>
                      <span class="d-block text-muted mt-1">Temporary placeholder — update this address in <code class="small">contact.php</code> when ready.</span>
                    </dd>
                  </dl>
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
  </body>
</html>

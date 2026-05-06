<?php
session_name('ABED_IDM_HUB');
session_start();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Privacy Policy — ABED IDM Hub</title>
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
            <ul class="navbar-nav ms-auto me-lg-2 mb-3 mb-lg-0 public-navbar-main-nav">
              <li class="nav-item"><a class="nav-link" href="index.php#overview">Overview</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#projects">Projects</a></li>
              <li class="nav-item"><a class="nav-link" href="index.php#uploads">Uploads</a></li>
              <li class="nav-item"><a class="nav-link" href="team.php">Meet Our Team</a></li>
              <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
              <li class="nav-item"><a class="nav-link active" href="privacy-policy.php" aria-current="page">Privacy</a></li>
            </ul>
            <?php if (isset($_SESSION['user_id'])): ?>
            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-2 public-nav-utils">
              <a href="dashboard-enhanced.php" class="btn btn-primary btn-sm public-nav-btn">Dashboard</a>
            </div>
            <?php endif; ?>
          </div>
        </nav>
      </div>
    </header>

    <main class="contact-main" id="privacy-policy">
      <section class="py-5 public-section-surface">
        <div class="container">
          <div class="row justify-content-center">
            <div class="col-lg-8">
              <h1 class="contact-page-title mb-3">Privacy Policy</h1>
              <p class="text-muted small mb-4">Last updated: May 2026. This notice describes how the ABED Integrated Data Management Hub (“the Hub”) handles information in line with its role as a local government unit system.</p>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">1. Who this applies to</h2>
                  <p class="small mb-0">This policy applies to visitors of the public website, registered users of the Hub (including employees and designated administrators), and anyone whose project or document information is stored to support infrastructure and agricultural programs for <strong>Malaybalay City</strong>.</p>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">2. Information we collect</h2>
                  <ul class="small mb-0">
                    <li class="mb-2"><strong>Account data:</strong> identifiers you provide at registration or that an administrator assigns (for example name, username, email, office or unit, employee identifier, role, and authentication data such as a password hash).</li>
                    <li class="mb-2"><strong>Operational data:</strong> project records, financial summaries, documents, machinery or beneficiary details, and similar fields entered to run programs (FSPF, IDP, AFME, and related workflows).</li>
                    <li class="mb-2"><strong>Technical data:</strong> server and security logs may include IP address, browser type, timestamps, and actions needed for audit, troubleshooting, and access control.</li>
                  </ul>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">3. How we use information</h2>
                  <p class="small mb-2">We use the information above to:</p>
                  <ul class="small mb-0">
                    <li>Provide and secure the Hub, including authentication, authorization, and support;</li>
                    <li>Plan, monitor, and report on public programs and assets;</li>
                    <li>Meet legal, regulatory, and transparency obligations that apply to the LGU;</li>
                    <li>Maintain integrity of records (for example approvals, audit trails, and backups where configured).</li>
                  </ul>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">4. Sharing and third parties</h2>
                  <p class="small mb-2">We do not sell personal information. Data may be shared only when necessary—for example with other LGU offices or funding partners under applicable rules, or with service providers who host or support the system under confidentiality and security expectations.</p>
                  <p class="small mb-0 text-muted">Some pages load fonts or security widgets from external networks (for example Google Fonts or reCAPTCHA on sign-in). Those providers may receive standard technical data according to their own policies.</p>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">5. Retention and security</h2>
                  <p class="small mb-0">We keep information for as long as needed for program administration, audit, and archiving policies of the City. Reasonable technical and organizational measures are applied to protect data against unauthorized access, loss, or misuse, in proportion to the sensitivity of the system.</p>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">6. Your choices and rights</h2>
                  <p class="small mb-0">Depending on applicable law (including the Philippine Data Privacy Act of 2012), you may have rights to access, correct, or object to certain processing of your personal data. For requests or questions, contact the office using the details on our <a href="contact.php">Contact</a> page.</p>
                </div>
              </div>

              <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                  <h2 class="h5 mb-3">7. Changes</h2>
                  <p class="small mb-0">We may update this policy from time to time. The “Last updated” line at the top will change when material revisions are published on this page.</p>
                </div>
              </div>

              <p class="small text-muted mb-0">
                <a href="index.php" class="text-decoration-none">← Back to home</a>
              </p>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer class="public-footer py-4">
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
              <li><a href="privacy-policy.php" class="text-muted text-decoration-none">Privacy Policy</a></li>
            </ul>
          </div>
          <div class="col-md-4">
            <h6 class="fw-bold mb-3">Contact</h6>
            <p class="small text-muted">
              Malaybalay City, Bukidnon<br>
              Email: <a href="mailto:example.abed.idm@example.com" class="text-muted">example.abed.idm@example.com</a>
            </p>
          </div>
        </div>
        <hr class="my-3 border-secondary border-opacity-25" />
        <div class="text-center text-muted small">
          <p>&copy; 2026 ABED IDM Hub. All rights reserved.</p>
        </div>
      </div>
    </footer>

    <script src="assets/bootstrap/js/bootstrap.bundle.js"></script>
  </body>
</html>

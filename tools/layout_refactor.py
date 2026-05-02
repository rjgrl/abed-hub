import re
from pathlib import Path
files=['dashboard.php','project-details.php','afme-machinery-details.php','geomap.php','reports.php','my-account.php','analytics.php','projects.php']
for fn in files:
    p=Path(fn)
    t=p.read_text(encoding='utf-8')
    t=re.sub(r'\?>\s*<!doctype html>.*?<main class="app-main">','?>\n<?php\nrequire_once __DIR__ . \'/components/layout.php\';\nrenderAppLayout($page_title);\n?>\n',t,flags=re.S)
    t=re.sub(r'<\?php include __DIR__ \. \'/components/(topbar|navbar)\.php\'; \?>\s*','',t)
    t=re.sub(r'</main>.*?</html>','<?php renderAppLayoutFooter(); ?>',t,flags=re.S)
    p.write_text(t,encoding='utf-8')
print('done')

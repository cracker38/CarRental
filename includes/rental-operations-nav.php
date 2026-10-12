<?php
// Included after the application's normal header.
ro_styles();
ro_nav($currentPage ?? 'dashboard');
ro_render_flash();

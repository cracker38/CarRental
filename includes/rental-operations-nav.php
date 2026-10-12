<?php
// Included after the application's normal header (CSS is loaded from header via ro_styles()).
ro_nav($currentPage ?? 'dashboard');
ro_render_flash();

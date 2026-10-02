<?php

require_once __DIR__ . '/LegacyGeeklogAdapter.php';

/**
 * glFusion keeps enough Geeklog ancestry for the core-content migration
 * strategy to be shared. Plugin-specific data (Forum, MediaGallery, etc.)
 * will be added here independently.
 */
class MigratorGlfusionAdapter extends MigratorLegacyGeeklogAdapter
{
}

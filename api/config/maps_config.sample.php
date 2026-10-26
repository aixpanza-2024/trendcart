<?php
/**
 * Google Maps Configuration (sample)
 * -----------------------------------------------
 * Copy this file to api/config/maps_config.php and paste the browser key.
 *
 * This file is listed in .gitignore — it will NOT be committed or pushed
 * to GitHub.
 *
 * In Google Cloud Console enable, for this key:
 *   - Maps JavaScript API   (the map with the draggable pin)
 *   - Places API (New)      (the "search area or landmark" suggestions)
 *   - Geocoding API         (turns "use my current location" into a place name)
 * and restrict the key to your website (Application restrictions ->
 * Websites: https://yourdomain.com/*). A browser key is visible in the page
 * source by design; the website restriction is what stops others using it.
 *
 * Leave the key empty to use the free OpenStreetMap search and map instead.
 * -----------------------------------------------
 */

define('GOOGLE_MAPS_BROWSER_KEY', '');

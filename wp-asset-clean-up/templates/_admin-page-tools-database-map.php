<?php
if (! isset($databaseStorageMap) || ! is_array($databaseStorageMap)) {
    exit;
}

$registry = isset($databaseStorageMap['registry']) && is_array($databaseStorageMap['registry'])
    ? $databaseStorageMap['registry']
    : array();
$currentRows = isset($databaseStorageMap['current_rows']) && is_array($databaseStorageMap['current_rows'])
    ? $databaseStorageMap['current_rows']
    : array();
$summary = isset($databaseStorageMap['summary']) && is_array($databaseStorageMap['summary'])
    ? $databaseStorageMap['summary']
    : array();
$queryErrors = isset($databaseStorageMap['query_errors']) && is_array($databaseStorageMap['query_errors'])
    ? $databaseStorageMap['query_errors']
    : array();
$storageTypes = isset($databaseStorageMap['storage_types']) && is_array($databaseStorageMap['storage_types'])
    ? $databaseStorageMap['storage_types']
    : array();
$generatedAt = isset($databaseStorageMap['generated_at']) ? (string)$databaseStorageMap['generated_at'] : '';

$registeredGroups = isset($summary['registered_groups']) ? (int)$summary['registered_groups'] : count($registry);
$currentRecords = isset($summary['current_records']) ? (int)$summary['current_records'] : 0;
$currentKeys = isset($summary['current_keys']) ? (int)$summary['current_keys'] : count($currentRows);
$databaseBytesFormatted = isset($summary['database_bytes_formatted']) ? $summary['database_bytes_formatted'] : '0 B';
$autoloadedRows = isset($summary['autoloaded_rows']) ? (int)$summary['autoloaded_rows'] : 0;
$autoloadedBytesFormatted = isset($summary['autoloaded_bytes_formatted']) ? $summary['autoloaded_bytes_formatted'] : '0 B';
$unregisteredKeys = isset($summary['unregistered_keys']) ? (int)$summary['unregistered_keys'] : 0;
?>
<section class="wpacu-db-map" data-wpacu-db-map>
    <div class="wpacu-db-map__privacy-note">
        <span class="dashicons dashicons-shield" aria-hidden="true"></span>
        <div>
            <strong><?php esc_html_e('Native WordPress storage, inspected read-only', 'wp-asset-clean-up'); ?></strong>
            <p><?php esc_html_e('Asset CleanUp does not create custom database tables in this version. It uses WordPress options, post meta, term meta, user meta and the Transients API. This page reads key names, aggregate counts, value sizes, autoload flags and expiration metadata; stored rule content, license keys and other values are never displayed.', 'wp-asset-clean-up'); ?></p>
        </div>
    </div>

    <?php if ( ! empty($queryErrors)) { ?>
        <div class="wpacu-db-map__query-warning" role="alert">
            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('The current installation snapshot is incomplete', 'wp-asset-clean-up'); ?></strong>
                <ul>
                    <?php foreach ($queryErrors as $queryError) { ?>
                        <li><?php echo esc_html($queryError); ?></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    <?php } ?>

    <div class="wpacu-db-map__stats" aria-label="<?php esc_attr_e('Database map summary', 'wp-asset-clean-up'); ?>">
        <article class="wpacu-db-map-stat">
            <span><?php esc_html_e('Registered groups', 'wp-asset-clean-up'); ?></span>
            <strong><?php echo esc_html(number_format_i18n($registeredGroups)); ?></strong>
            <small><?php esc_html_e('Documented architecture records', 'wp-asset-clean-up'); ?></small>
        </article>
        <article class="wpacu-db-map-stat">
            <span><?php esc_html_e('Current records', 'wp-asset-clean-up'); ?></span>
            <strong><?php echo esc_html(number_format_i18n($currentRecords)); ?></strong>
            <small><?php echo esc_html(sprintf(_n('%s distinct key', '%s distinct keys', $currentKeys, 'wp-asset-clean-up'), number_format_i18n($currentKeys))); ?></small>
        </article>
        <article class="wpacu-db-map-stat">
            <span><?php esc_html_e('Value footprint', 'wp-asset-clean-up'); ?></span>
            <strong><?php echo esc_html($databaseBytesFormatted); ?></strong>
            <small><?php esc_html_e('Value bytes only; DB overhead excluded', 'wp-asset-clean-up'); ?></small>
        </article>
        <article class="wpacu-db-map-stat">
            <span><?php esc_html_e('Autoloaded rows', 'wp-asset-clean-up'); ?></span>
            <strong><?php echo esc_html(number_format_i18n($autoloadedRows)); ?></strong>
            <small><?php echo esc_html(sprintf(__('%s in options-table value data', 'wp-asset-clean-up'), $autoloadedBytesFormatted)); ?></small>
        </article>
        <article class="wpacu-db-map-stat <?php echo $unregisteredKeys > 0 ? 'wpacu-db-map-stat--review' : 'wpacu-db-map-stat--clean'; ?>">
            <span><?php esc_html_e('Needs review', 'wp-asset-clean-up'); ?></span>
            <strong><?php echo esc_html(number_format_i18n($unregisteredKeys)); ?></strong>
            <small><?php echo $unregisteredKeys > 0 ? esc_html__('Prefix-matched but undocumented keys', 'wp-asset-clean-up') : esc_html__('No undocumented keys detected', 'wp-asset-clean-up'); ?></small>
        </article>
    </div>

    <div class="wpacu-db-map__workspace">
        <div class="wpacu-db-map__toolbar">
            <div class="wpacu-db-map__views" role="tablist" aria-label="<?php esc_attr_e('Database map views', 'wp-asset-clean-up'); ?>">
                <button type="button" id="wpacu-db-map-tab-architecture" class="is-active" role="tab" aria-selected="true" aria-controls="wpacu-db-map-panel-architecture" data-db-map-view="architecture">
                    <span class="dashicons dashicons-editor-code" aria-hidden="true"></span>
                    <?php esc_html_e('Architecture Reference', 'wp-asset-clean-up'); ?>
                </button>
                <button type="button" id="wpacu-db-map-tab-current" role="tab" aria-selected="false" aria-controls="wpacu-db-map-panel-current" data-db-map-view="current">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                    <?php esc_html_e('Current Installation', 'wp-asset-clean-up'); ?>
                </button>
            </div>

            <div class="wpacu-db-map__filters">
                <label class="wpacu-db-map__search" for="wpacu-db-map-search">
                    <span class="screen-reader-text"><?php esc_html_e('Search database map', 'wp-asset-clean-up'); ?></span>
                    <span class="dashicons dashicons-search" aria-hidden="true"></span>
                    <input type="search" id="wpacu-db-map-search" placeholder="<?php esc_attr_e('Search key, component, purpose or source file', 'wp-asset-clean-up'); ?>" autocomplete="off" data-db-map-search>
                </label>

                <label>
                    <span class="screen-reader-text"><?php esc_html_e('Filter by storage type', 'wp-asset-clean-up'); ?></span>
                    <select data-db-map-storage-filter>
                        <option value="all"><?php esc_html_e('All storage types', 'wp-asset-clean-up'); ?></option>
                        <?php foreach ($storageTypes as $storageType => $storageLabel) { ?>
                            <option value="<?php echo esc_attr($storageType); ?>"><?php echo esc_html($storageLabel); ?></option>
                        <?php } ?>
                    </select>
                </label>

                <label data-db-map-context-filter-wrap="architecture">
                    <span class="screen-reader-text"><?php esc_html_e('Filter architecture by edition', 'wp-asset-clean-up'); ?></span>
                    <select data-db-map-context-filter="architecture">
                        <option value="all"><?php esc_html_e('All editions', 'wp-asset-clean-up'); ?></option>
                        <option value="shared"><?php esc_html_e('Lite & Pro', 'wp-asset-clean-up'); ?></option>
                        <option value="pro"><?php esc_html_e('Pro only', 'wp-asset-clean-up'); ?></option>
                    </select>
                </label>

                <label data-db-map-context-filter-wrap="current" hidden>
                    <span class="screen-reader-text"><?php esc_html_e('Filter current data by status', 'wp-asset-clean-up'); ?></span>
                    <select data-db-map-context-filter="current">
                        <option value="all"><?php esc_html_e('All statuses', 'wp-asset-clean-up'); ?></option>
                        <option value="registered"><?php esc_html_e('Registered', 'wp-asset-clean-up'); ?></option>
                        <option value="review"><?php esc_html_e('Needs review', 'wp-asset-clean-up'); ?></option>
                        <option value="expired"><?php esc_html_e('Expired transients', 'wp-asset-clean-up'); ?></option>
                    </select>
                </label>
            </div>
        </div>

        <div class="wpacu-db-map__results-meta">
            <span data-db-map-results-count aria-live="polite"></span>
            <?php if ($generatedAt !== '') { ?>
                <span><?php echo esc_html(sprintf(__('Snapshot generated: %s', 'wp-asset-clean-up'), $generatedAt)); ?></span>
            <?php } ?>
        </div>

        <div id="wpacu-db-map-panel-architecture" class="wpacu-db-map__panel" role="tabpanel" aria-labelledby="wpacu-db-map-tab-architecture" data-db-map-panel="architecture">
            <div class="wpacu-db-map__panel-intro">
                <div>
                    <h3><?php esc_html_e('Declared storage architecture', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('This reference lists storage the current plugin code can create, even when a key is not present on this website yet.', 'wp-asset-clean-up'); ?></p>
                </div>
                <span class="wpacu-db-map-badge wpacu-db-map-badge--neutral"><?php esc_html_e('Code registry', 'wp-asset-clean-up'); ?></span>
            </div>

            <div class="wpacu-db-map__table-wrap">
                <table class="wpacu-db-map-table">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Component & storage key', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Storage', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Scope & format', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Current use', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Transfer', 'wp-asset-clean-up'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registry as $entry) {
                        $entryKeys = array_merge(
                            isset($entry['keys']) && is_array($entry['keys']) ? $entry['keys'] : array(),
                            isset($entry['patterns']) && is_array($entry['patterns']) ? $entry['patterns'] : array()
                        );
                        $sourceFiles = isset($entry['source']) && is_array($entry['source']) ? $entry['source'] : array();
                        $editionKind = strpos((string)$entry['id'], 'pro_') === 0 ? 'pro' : 'shared';
                        $searchText = implode(' ', array_merge(
                            array(
                                isset($entry['component']) ? $entry['component'] : '',
                                isset($entry['purpose']) ? $entry['purpose'] : '',
                                isset($entry['scope']) ? $entry['scope'] : '',
                                isset($entry['format']) ? $entry['format'] : '',
                                isset($entry['lifecycle']) ? $entry['lifecycle'] : '',
                                isset($entry['edition']) ? $entry['edition'] : '',
                                isset($entry['transfer']) ? $entry['transfer'] : '',
                            ),
                            $entryKeys,
                            $sourceFiles
                        ));
                        $currentEntryKeys = isset($entry['current_keys']) ? (int)$entry['current_keys'] : 0;
                        $currentEntryRecords = isset($entry['current_records']) ? (int)$entry['current_records'] : 0;
                        $currentEntrySize = isset($entry['current_bytes_formatted']) ? $entry['current_bytes_formatted'] : '0 B';
                        ?>
                        <tr data-db-map-row data-view="architecture" data-storage="<?php echo esc_attr($entry['storage']); ?>" data-context="<?php echo esc_attr($editionKind); ?>" data-search="<?php echo esc_attr(strtolower(wp_strip_all_tags($searchText))); ?>">
                            <td class="wpacu-db-map-table__primary">
                                <div class="wpacu-db-map-table__title-row">
                                    <strong><?php echo esc_html($entry['component']); ?></strong>
                                    <span class="wpacu-db-map-badge <?php echo $editionKind === 'pro' ? 'wpacu-db-map-badge--pro' : 'wpacu-db-map-badge--shared'; ?>"><?php echo esc_html($entry['edition']); ?></span>
                                    <?php if ( ! empty($entry['sensitive'])) { ?>
                                        <span class="wpacu-db-map-badge wpacu-db-map-badge--protected"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Protected', 'wp-asset-clean-up'); ?></span>
                                    <?php } ?>
                                </div>
                                <div class="wpacu-db-map-key-list">
                                    <?php foreach ($entryKeys as $entryKey) { ?>
                                        <code><?php echo esc_html($entryKey); ?></code>
                                    <?php } ?>
                                </div>
                                <p><?php echo esc_html($entry['purpose']); ?></p>
                                <details class="wpacu-db-map-details">
                                    <summary><?php esc_html_e('Lifecycle and code references', 'wp-asset-clean-up'); ?></summary>
                                    <div>
                                        <dl>
                                            <dt><?php esc_html_e('Lifecycle', 'wp-asset-clean-up'); ?></dt>
                                            <dd><?php echo esc_html($entry['lifecycle']); ?></dd>
                                            <dt><?php esc_html_e('Source files', 'wp-asset-clean-up'); ?></dt>
                                            <dd>
                                                <?php if ( ! empty($sourceFiles)) { ?>
                                                    <div class="wpacu-db-map-source-list">
                                                        <?php foreach ($sourceFiles as $sourceFile) { ?><code><?php echo esc_html($sourceFile); ?></code><?php } ?>
                                                    </div>
                                                <?php } else { ?>
                                                    <?php esc_html_e('No source reference registered.', 'wp-asset-clean-up'); ?>
                                                <?php } ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </details>
                            </td>
                            <td>
                                <span class="wpacu-db-map-storage wpacu-db-map-storage--<?php echo esc_attr($entry['storage']); ?>"><?php echo esc_html(isset($storageTypes[$entry['storage']]) ? $storageTypes[$entry['storage']] : $entry['storage']); ?></span>
                                <code class="wpacu-db-map-location"><?php
                                    if ($entry['storage'] === 'option') {
                                        echo esc_html('$wpdb->options');
                                    } elseif ($entry['storage'] === 'postmeta') {
                                        echo esc_html('$wpdb->postmeta');
                                    } elseif ($entry['storage'] === 'termmeta') {
                                        echo esc_html('$wpdb->termmeta');
                                    } elseif ($entry['storage'] === 'usermeta') {
                                        echo esc_html('$wpdb->usermeta');
                                    } else {
                                        esc_html_e('Transients API', 'wp-asset-clean-up');
                                    }
                                ?></code>
                            </td>
                            <td>
                                <strong><?php echo esc_html($entry['scope']); ?></strong>
                                <small><?php echo esc_html($entry['format']); ?></small>
                            </td>
                            <td>
                                <?php if ($currentEntryKeys > 0) { ?>
                                    <span class="wpacu-db-map-presence wpacu-db-map-presence--present"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e('Present', 'wp-asset-clean-up'); ?></span>
                                    <small><?php echo esc_html(sprintf(__('%1$s keys · %2$s records · %3$s', 'wp-asset-clean-up'), number_format_i18n($currentEntryKeys), number_format_i18n($currentEntryRecords), $currentEntrySize)); ?></small>
                                <?php } else { ?>
                                    <span class="wpacu-db-map-presence"><span class="dashicons dashicons-minus" aria-hidden="true"></span><?php esc_html_e('Not present', 'wp-asset-clean-up'); ?></span>
                                    <small><?php esc_html_e('Created only when the related feature is used.', 'wp-asset-clean-up'); ?></small>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="wpacu-db-map-badge wpacu-db-map-badge--transfer"><?php echo esc_html($entry['transfer']); ?></span>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr class="wpacu-db-map-table__empty" data-db-map-empty-row hidden>
                        <td colspan="5"><span class="dashicons dashicons-search" aria-hidden="true"></span><strong><?php esc_html_e('No architecture records match the current filters.', 'wp-asset-clean-up'); ?></strong></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="wpacu-db-map-panel-current" class="wpacu-db-map__panel" role="tabpanel" aria-labelledby="wpacu-db-map-tab-current" data-db-map-panel="current" hidden>
            <div class="wpacu-db-map__panel-intro">
                <div>
                    <h3><?php esc_html_e('Data found on this installation', 'wp-asset-clean-up'); ?></h3>
                    <p><?php esc_html_e('Rows are aggregated by key. Rule and configuration payloads are not selected or exposed; only aggregate metadata and transient timeout timestamps are used.', 'wp-asset-clean-up'); ?></p>
                </div>
                <span class="wpacu-db-map-badge wpacu-db-map-badge--neutral"><?php esc_html_e('Live snapshot', 'wp-asset-clean-up'); ?></span>
            </div>

            <div class="wpacu-db-map__table-wrap">
                <table class="wpacu-db-map-table wpacu-db-map-table--current">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('Key & component', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Location', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Records', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Value size', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Runtime behavior', 'wp-asset-clean-up'); ?></th>
                        <th><?php esc_html_e('Status', 'wp-asset-clean-up'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($currentRows as $currentRow) {
                        $sourceFiles = isset($currentRow['source']) && is_array($currentRow['source']) ? $currentRow['source'] : array();
                        $statusContext = $currentRow['status'] === 'unregistered' ? 'review' : $currentRow['status'];
                        $searchText = implode(' ', array_merge(
                            array(
                                $currentRow['storage_key'],
                                $currentRow['component'],
                                $currentRow['purpose'],
                                $currentRow['location'],
                                $currentRow['edition'],
                                $currentRow['transfer'],
                                $currentRow['status_label'],
                            ),
                            $sourceFiles
                        ));
                        ?>
                        <tr data-db-map-row data-view="current" data-storage="<?php echo esc_attr($currentRow['storage']); ?>" data-context="<?php echo esc_attr($statusContext); ?>" data-search="<?php echo esc_attr(strtolower(wp_strip_all_tags($searchText))); ?>">
                            <td class="wpacu-db-map-table__primary">
                                <div class="wpacu-db-map-table__title-row">
                                    <code class="wpacu-db-map-current-key"><?php echo esc_html($currentRow['storage_key']); ?></code>
                                    <?php if ( ! empty($currentRow['sensitive'])) { ?>
                                        <span class="wpacu-db-map-badge wpacu-db-map-badge--protected"><span class="dashicons dashicons-lock" aria-hidden="true"></span><?php esc_html_e('Value hidden', 'wp-asset-clean-up'); ?></span>
                                    <?php } ?>
                                </div>
                                <strong class="wpacu-db-map-current-component"><?php echo esc_html($currentRow['component']); ?></strong>
                                <details class="wpacu-db-map-details">
                                    <summary><?php esc_html_e('Purpose and code references', 'wp-asset-clean-up'); ?></summary>
                                    <div>
                                        <p><?php echo esc_html($currentRow['purpose']); ?></p>
                                        <dl>
                                            <dt><?php esc_html_e('Edition', 'wp-asset-clean-up'); ?></dt>
                                            <dd><?php echo esc_html($currentRow['edition']); ?></dd>
                                            <dt><?php esc_html_e('Import/export', 'wp-asset-clean-up'); ?></dt>
                                            <dd><?php echo esc_html($currentRow['transfer']); ?></dd>
                                            <dt><?php esc_html_e('Source files', 'wp-asset-clean-up'); ?></dt>
                                            <dd>
                                                <?php if ( ! empty($sourceFiles)) { ?>
                                                    <div class="wpacu-db-map-source-list">
                                                        <?php foreach ($sourceFiles as $sourceFile) { ?><code><?php echo esc_html($sourceFile); ?></code><?php } ?>
                                                    </div>
                                                <?php } else { ?>
                                                    <?php esc_html_e('No source reference is registered for this key.', 'wp-asset-clean-up'); ?>
                                                <?php } ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </details>
                            </td>
                            <td>
                                <span class="wpacu-db-map-storage wpacu-db-map-storage--<?php echo esc_attr($currentRow['storage']); ?>"><?php echo esc_html($currentRow['storage_label']); ?></span>
                                <code class="wpacu-db-map-location"><?php echo esc_html($currentRow['location']); ?></code>
                            </td>
                            <td><strong class="wpacu-db-map-number"><?php echo esc_html(number_format_i18n($currentRow['records'])); ?></strong></td>
                            <td><strong class="wpacu-db-map-number"><?php echo esc_html($currentRow['bytes_formatted']); ?></strong></td>
                            <td>
                                <?php if ($currentRow['storage'] === 'option') { ?>
                                    <span class="wpacu-db-map-runtime-label"><?php esc_html_e('Autoload', 'wp-asset-clean-up'); ?></span>
                                    <strong class="<?php echo ! empty($currentRow['autoloaded']) ? 'is-autoloaded' : 'is-not-autoloaded'; ?>"><?php echo esc_html($currentRow['autoload_label']); ?></strong>
                                <?php } elseif ($currentRow['storage'] === 'transient') { ?>
                                    <span class="wpacu-db-map-runtime-label"><?php esc_html_e('Expiration', 'wp-asset-clean-up'); ?></span>
                                    <strong><?php echo esc_html($currentRow['expiration_label']); ?></strong>
                                    <span class="wpacu-db-map-runtime-label wpacu-db-map-runtime-label--secondary"><?php esc_html_e('Options-table autoload', 'wp-asset-clean-up'); ?></span>
                                    <strong class="<?php echo ! empty($currentRow['autoloaded']) ? 'is-autoloaded' : 'is-not-autoloaded'; ?>"><?php echo esc_html($currentRow['autoload_label']); ?></strong>
                                <?php } else { ?>
                                    <span class="wpacu-db-map-runtime-label"><?php esc_html_e('Object lifecycle', 'wp-asset-clean-up'); ?></span>
                                    <strong><?php esc_html_e('Linked to WordPress objects', 'wp-asset-clean-up'); ?></strong>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="wpacu-db-map-status wpacu-db-map-status--<?php echo esc_attr($statusContext); ?>">
                                    <span class="dashicons <?php
                                        if ($statusContext === 'registered') {
                                            echo 'dashicons-yes-alt';
                                        } elseif ($statusContext === 'expired') {
                                            echo 'dashicons-clock';
                                        } else {
                                            echo 'dashicons-warning';
                                        }
                                    ?>" aria-hidden="true"></span>
                                    <?php echo esc_html($currentRow['status_label']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr class="wpacu-db-map-table__empty" data-db-map-empty-row <?php echo empty($currentRows) ? '' : 'hidden'; ?>>
                        <td colspan="6"><span class="dashicons dashicons-database" aria-hidden="true"></span><strong><?php echo empty($currentRows) ? esc_html__('No Asset CleanUp database records were found.', 'wp-asset-clean-up') : esc_html__('No current installation records match the current filters.', 'wp-asset-clean-up'); ?></strong></td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="wpacu-db-map__footnote">
        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
        <p>
            <?php esc_html_e('Size figures are approximate and include only stored value bytes. Database row overhead, key-name bytes, table indexes and filesystem cache files are excluded. When a persistent object cache is active, transient values held outside the database may not appear in the current-installation snapshot.', 'wp-asset-clean-up'); ?>
            <?php if (function_exists('is_multisite') && is_multisite()) { ?>
                <?php esc_html_e('On multisite, the user meta table is shared network-wide, so its totals can include matching Asset CleanUp records associated with users on another site in the network.', 'wp-asset-clean-up'); ?>
            <?php } ?>
        </p>
    </div>
</section>
<script>
(function () {
    var root = document.querySelector('[data-wpacu-db-map]');
    if (!root) return;

    var viewButtons = root.querySelectorAll('[data-db-map-view]');
    var panels = root.querySelectorAll('[data-db-map-panel]');
    var searchInput = root.querySelector('[data-db-map-search]');
    var storageFilter = root.querySelector('[data-db-map-storage-filter]');
    var countOutput = root.querySelector('[data-db-map-results-count]');
    var activeView = 'architecture';

    function getContextFilter(view) {
        return root.querySelector('[data-db-map-context-filter="' + view + '"]');
    }

    function normalize(value) {
        return String(value || '').toLowerCase().replace(/^\s+|\s+$/g, '');
    }

    function updateRows() {
        var panel = root.querySelector('[data-db-map-panel="' + activeView + '"]');
        if (!panel) return;

        var search = normalize(searchInput ? searchInput.value : '');
        var storage = storageFilter ? storageFilter.value : 'all';
        var contextFilter = getContextFilter(activeView);
        var context = contextFilter ? contextFilter.value : 'all';
        var rows = panel.querySelectorAll('[data-db-map-row]');
        var visible = 0;

        Array.prototype.forEach.call(rows, function (row) {
            var matchesSearch = search === '' || normalize(row.getAttribute('data-search')).indexOf(search) !== -1;
            var matchesStorage = storage === 'all' || row.getAttribute('data-storage') === storage;
            var matchesContext = context === 'all' || row.getAttribute('data-context') === context;
            var show = matchesSearch && matchesStorage && matchesContext;
            row.hidden = !show;
            if (show) visible++;
        });

        var emptyRow = panel.querySelector('[data-db-map-empty-row]');
        if (emptyRow) emptyRow.hidden = visible !== 0;

        if (countOutput) {
            var total = rows.length;
            countOutput.textContent = '<?php echo esc_js(__('Showing', 'wp-asset-clean-up')); ?> ' + visible + ' <?php echo esc_js(__('of', 'wp-asset-clean-up')); ?> ' + total + ' <?php echo esc_js(__('records', 'wp-asset-clean-up')); ?>';
        }
    }

    function activateView(view) {
        activeView = view === 'current' ? 'current' : 'architecture';

        Array.prototype.forEach.call(viewButtons, function (button) {
            var active = button.getAttribute('data-db-map-view') === activeView;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
        });

        Array.prototype.forEach.call(panels, function (panel) {
            panel.hidden = panel.getAttribute('data-db-map-panel') !== activeView;
        });

        var contextWraps = root.querySelectorAll('[data-db-map-context-filter-wrap]');
        Array.prototype.forEach.call(contextWraps, function (wrap) {
            wrap.hidden = wrap.getAttribute('data-db-map-context-filter-wrap') !== activeView;
        });

        updateRows();
    }

    Array.prototype.forEach.call(viewButtons, function (button) {
        button.addEventListener('click', function () {
            activateView(button.getAttribute('data-db-map-view'));
        });
    });

    if (searchInput) searchInput.addEventListener('input', updateRows);
    if (storageFilter) storageFilter.addEventListener('change', updateRows);

    var contextFilters = root.querySelectorAll('[data-db-map-context-filter]');
    Array.prototype.forEach.call(contextFilters, function (filter) {
        filter.addEventListener('change', updateRows);
    });

    activateView('architecture');
}());
</script>

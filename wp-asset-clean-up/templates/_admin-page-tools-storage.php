<?php
if (! isset($storageStats, $currentStorageDirFull, $currentStorageDirIsWritable)) {
	exit;
}

$storageBasePath = trailingslashit(ABSPATH);
$refreshStorageUrl = admin_url('admin.php?page=wpassetcleanup_tools&wpacu_for=storage&wpacu_storage_area=generated_files');
?>
<section class="wpacu-storage" data-wpacu-storage>
	<header class="wpacu-storage__header">
		<div>
			<h2><?php esc_html_e('Storage overview', 'wp-asset-clean-up'); ?></h2>
			<p><?php esc_html_e('Cached files created when CSS or JavaScript assets are optimized.', 'wp-asset-clean-up'); ?> <a target="_blank" rel="noopener" href="https://www.assetcleanup.com/docs/?p=526"><?php esc_html_e('Read more', 'wp-asset-clean-up'); ?></a></p>
		</div>
		<a class="button wpacu-storage__refresh" href="<?php echo esc_url($refreshStorageUrl); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e('Refresh', 'wp-asset-clean-up'); ?></a>
	</header>

	<div class="wpacu-storage__stats">
		<article class="wpacu-storage-stat wpacu-storage-stat--status"><span><?php esc_html_e('Storage status', 'wp-asset-clean-up'); ?></span><strong class="<?php echo $currentStorageDirIsWritable ? 'is-writable' : 'is-not-writable'; ?>"><span class="dashicons <?php echo $currentStorageDirIsWritable ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span><?php echo $currentStorageDirIsWritable ? esc_html__('Writable', 'wp-asset-clean-up') : esc_html__('Not writable', 'wp-asset-clean-up'); ?></strong></article>
		<article class="wpacu-storage-stat"><span><?php esc_html_e('Total files', 'wp-asset-clean-up'); ?></span><strong><?php echo isset($storageStats['total_files']) ? (int)$storageStats['total_files'] : 0; ?></strong></article>
		<article class="wpacu-storage-stat"><span><?php esc_html_e('CSS/JS assets', 'wp-asset-clean-up'); ?></span><strong><?php echo isset($storageStats['total_files_assets']) ? (int)$storageStats['total_files_assets'] : 0; ?></strong></article>
		<article class="wpacu-storage-stat"><span><?php esc_html_e('Total storage', 'wp-asset-clean-up'); ?></span><strong><?php echo isset($storageStats['total_size']) ? wp_kses_post($storageStats['total_size']) : '0 B'; ?></strong></article>
		<article class="wpacu-storage-stat"><span><?php esc_html_e('CSS/JS size', 'wp-asset-clean-up'); ?></span><strong><?php echo isset($storageStats['total_size_assets']) ? wp_kses_post($storageStats['total_size_assets']) : '0 B'; ?></strong></article>
	</div>

	<div class="wpacu-storage-path-card">
		<div class="wpacu-storage-path-card__label"><span><?php esc_html_e('Current storage directory', 'wp-asset-clean-up'); ?></span><small><?php esc_html_e('Full server path', 'wp-asset-clean-up'); ?></small></div>
		<code><?php echo esc_html($currentStorageDirFull); ?></code>
		<button type="button" class="button wpacu-storage-copy" data-copy-value="<?php echo esc_attr($currentStorageDirFull); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span data-copy-label><?php esc_html_e('Copy', 'wp-asset-clean-up'); ?></span></button>
	</div>

	<?php if (isset($storageStats['total_files']) && $storageStats['total_files'] === 0) { ?>
		<div class="wpacu-storage__empty"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><div><strong><?php esc_html_e('No cached files yet', 'wp-asset-clean-up'); ?></strong><p><?php esc_html_e('Optimized CSS and JavaScript files will be added automatically as visitors browse the website.', 'wp-asset-clean-up'); ?></p></div></div>
	<?php } elseif ( ! empty($storageStats['dirs_files_sizes']) ) { ?>
		<div class="wpacu-storage-directories">
			<div class="wpacu-storage-directories__head">
				<div><h3><?php esc_html_e('Storage directories', 'wp-asset-clean-up'); ?></h3><p><?php esc_html_e('The common base path is shown once; directory rows use shorter relative paths.', 'wp-asset-clean-up'); ?></p></div>
				<div class="wpacu-storage-filters" role="group" aria-label="<?php esc_attr_e('Filter storage directories', 'wp-asset-clean-up'); ?>">
					<button type="button" class="is-active" data-storage-filter="all"><?php esc_html_e('All', 'wp-asset-clean-up'); ?></button>
					<button type="button" data-storage-filter="assets"><?php esc_html_e('CSS & JS', 'wp-asset-clean-up'); ?></button>
					<button type="button" data-storage-filter="supporting"><?php esc_html_e('Supporting files', 'wp-asset-clean-up'); ?></button>
				</div>
			</div>
			<div class="wpacu-storage-base-path"><span><?php esc_html_e('Base path', 'wp-asset-clean-up'); ?></span><code><?php echo esc_html($storageBasePath); ?></code><button type="button" class="wpacu-storage-copy" data-copy-value="<?php echo esc_attr($storageBasePath); ?>" aria-label="<?php esc_attr_e('Copy base path', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></div>
			<div class="wpacu-storage-table-wrap">
				<table class="wpacu-storage-table">
					<thead><tr><th><?php esc_html_e('Directory', 'wp-asset-clean-up'); ?></th><th><?php esc_html_e('Contents', 'wp-asset-clean-up'); ?></th><th aria-sort="none"><button type="button" class="wpacu-storage-sort" data-storage-sort="size"><span><?php esc_html_e('Size', 'wp-asset-clean-up'); ?></span><span class="dashicons dashicons-sort" aria-hidden="true"></span></button></th><th><span class="screen-reader-text"><?php esc_html_e('Actions', 'wp-asset-clean-up'); ?></span></th></tr></thead>
					<tbody>
					<?php foreach ($storageStats['dirs_files_sizes'] as $localDirPath => $localDirFileSizes) {
						$localDirPath = trim($localDirPath);
						$hasCssJs = in_array($localDirPath, $storageStats['dirs_css_js'], true);
						$relativeDirPath = strpos($localDirPath, $storageBasePath) === 0 ? substr($localDirPath, strlen($storageBasePath)) : $localDirPath;
						$relativeDirPath = $relativeDirPath !== '' ? $relativeDirPath : './';
						$normalizedDirPath = untrailingslashit(wp_normalize_path($localDirPath));
						$assetContentsLabel = 'CSS/JS';
						$directorySizeBytes = array_sum($localDirFileSizes);

						if (substr($normalizedDirPath, -strlen('/css/item')) === '/css/item') {
							$assetContentsLabel = 'CSS';
						} elseif (substr($normalizedDirPath, -strlen('/js/item')) === '/js/item') {
							$assetContentsLabel = 'JS';
						}
						?>
						<tr data-storage-kind="<?php echo $hasCssJs ? 'assets' : 'supporting'; ?>" data-storage-size="<?php echo esc_attr($directorySizeBytes); ?>">
							<td><code title="<?php echo esc_attr($localDirPath); ?>"><?php echo esc_html($relativeDirPath); ?></code></td>
							<td><?php if ($hasCssJs) { ?><span class="wpacu-storage-badge wpacu-storage-badge--assets"><span class="dashicons dashicons-yes" aria-hidden="true"></span><?php echo esc_html($assetContentsLabel); ?></span><?php } else { ?><span class="wpacu-storage-badge"><?php esc_html_e('Supporting', 'wp-asset-clean-up'); ?></span><?php } ?></td>
							<td><strong><?php echo esc_html(\WpAssetCleanUp\Admin\MiscAdmin::formatBytes($directorySizeBytes, 2, '', false)); ?></strong></td>
							<td><button type="button" class="wpacu-storage-copy" data-copy-value="<?php echo esc_attr($localDirPath); ?>" aria-label="<?php esc_attr_e('Copy full directory path', 'wp-asset-clean-up'); ?>"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	<?php } ?>

	<details class="wpacu-storage-advanced">
		<summary><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><span><strong><?php esc_html_e('Custom storage location', 'wp-asset-clean-up'); ?></strong><small><?php esc_html_e('For hosting platforms with restricted writable directories', 'wp-asset-clean-up'); ?></small></span></summary>
		<div class="wpacu-storage-advanced__content">
			<p><?php echo sprintf(__('On certain hosting platforms such as Pantheon, writable directories are limited. You can change the storage path to %s.', 'wp-asset-clean-up'), '<code><strong>/uploads/asset-cleanup/</strong></code>'); ?></p>
			<p><?php echo sprintf(__('Add the following constant to %s above the line %s.', 'wp-asset-clean-up'), '<em>wp-config.php</em>', '<code><em>/* That\'s all, stop editing! Happy blogging. */</em></code>'); ?></p>
			<div class="wpacu-storage-code"><code>define('WPACU_CACHE_DIR', '/uploads/asset-cleanup/');</code><button type="button" class="button wpacu-storage-copy" data-copy-value="define('WPACU_CACHE_DIR', '/uploads/asset-cleanup/');"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span data-copy-label><?php esc_html_e('Copy', 'wp-asset-clean-up'); ?></span></button></div>
			<p class="description"><?php echo sprintf(__('The relative path is appended to the WordPress content directory: %s', 'wp-asset-clean-up'), '<code>'.esc_html(trailingslashit(WP_CONTENT_DIR)).'</code>'); ?></p>
		</div>
	</details>
	<div class="wpacu-storage-toast" role="status" aria-live="polite" aria-atomic="true"></div>
</section>
<script>
(function () {
	var storage = document.querySelector('[data-wpacu-storage]');
	if (!storage) return;
	var toast = storage.querySelector('.wpacu-storage-toast');
	function showCopied(button) {
		var label = button.querySelector('[data-copy-label]');
		if (label) { var original = label.textContent; label.textContent = '<?php echo esc_js(__('Copied', 'wp-asset-clean-up')); ?>'; window.setTimeout(function () { label.textContent = original; }, 1400); }
		toast.textContent = '<?php echo esc_js(__('Full path copied to clipboard.', 'wp-asset-clean-up')); ?>'; toast.classList.add('is-visible'); window.setTimeout(function () { toast.classList.remove('is-visible'); }, 1600);
	}
	storage.addEventListener('click', function (event) {
		var sortButton = event.target.closest('[data-storage-sort="size"]');
		if (sortButton) {
			var table = sortButton.closest('table');
			var tbody = table.querySelector('tbody');
			var header = sortButton.closest('th');
			var direction = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
			var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-storage-size]'));
			rows.sort(function (firstRow, secondRow) {
				var difference = Number(firstRow.getAttribute('data-storage-size')) - Number(secondRow.getAttribute('data-storage-size'));
				return direction === 'ascending' ? difference : -difference;
			});
			rows.forEach(function (row) { tbody.appendChild(row); });
			header.setAttribute('aria-sort', direction);
			sortButton.querySelector('.dashicons').className = 'dashicons ' + (direction === 'ascending' ? 'dashicons-arrow-up-alt2' : 'dashicons-arrow-down-alt2');
			return;
		}
		var copyButton = event.target.closest('[data-copy-value]');
		if (copyButton) {
			var value = copyButton.getAttribute('data-copy-value');
			if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(value).then(function () { showCopied(copyButton); }); }
			else { var input = document.createElement('textarea'); input.value = value; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove(); showCopied(copyButton); }
			return;
		}
		var filterButton = event.target.closest('[data-storage-filter]');
		if (!filterButton) return;
		var filter = filterButton.getAttribute('data-storage-filter');
		storage.querySelectorAll('[data-storage-filter]').forEach(function (button) { button.classList.toggle('is-active', button === filterButton); });
		storage.querySelectorAll('[data-storage-kind]').forEach(function (row) { row.hidden = filter !== 'all' && row.getAttribute('data-storage-kind') !== filter; });
	});
}());
</script>

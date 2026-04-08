<?php

require_once dirname(__DIR__) . '/session_config.php';

require '../database.php';

require '../config.php';

if (!isset($_SESSION['admin'])) {

	header('Location: ../login.php');

	exit;

}
$userRole = $_SESSION['admin']['position'] ?? 'Editor';

$isAdmin = ($_SESSION['admin']['role'] ?? 'admin') === 'admin';

if ($isAdmin)

	$userRole = 'Admin';
	
$hasBlogAccess = $isAdmin;

if (!$hasBlogAccess) {

	header('Location: ../dashboard.php');

	exit;

}

$filterCategory = trim($_GET['category'] ?? 'all');

$sortBy = trim($_GET['sort'] ?? 'newest');

$categoriesA = $db->blog->distinct('categories') ?: [];

$categoriesB = $db->blog->distinct('category') ?: [];

$allCategories = array_values(array_unique(array_merge(is_array($categoriesA) ? $categoriesA : [], is_array($categoriesB) ? $categoriesB : [])));

$query = ['status' => 'archived'];

if ($userRole === 'Author') {

	$query['author_email'] = $_SESSION['admin']['email'] ?? 'unknown';

}

if ($filterCategory !== '' && $filterCategory !== 'all') {

	if ($filterCategory === 'uncategorized') {

		$query['categories'] = ['$size' => 0];

	} else {

		$query['$or'] = [['categories' => $filterCategory], ['category' => $filterCategory]];

	}

}

$sortStage = [];

if ($sortBy === 'popular') {

	$sortStage = ['views' => -1, 'created_at' => -1];

} elseif ($sortBy === 'oldest') {

	$sortStage = ['created_at' => 1];

} else {

	$sortStage = ['created_at' => -1];

}

$pipeline = [

	['$match' => $query],

	['$sort' => $sortStage]

];

$cursor = $db->blog->aggregate($pipeline);

$posts = iterator_to_array($cursor);

?>

<!DOCTYPE html>

<html>
	
<head>

	<meta charset="utf-8">

	<meta name="viewport" content="width=device-width, initial-scale=1">

	<title><?= $lang['archived_posts_title'] ?></title>

    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl) ?>" type="image/x-icon">

	    <link rel="stylesheet" href="../assets/css/dashboard.css?v=<?= time() ?>">

    <script src="../assets/js/global-search.js?v=<?= time() ?>"></script>
	
	<style>

		.modal-overlay {

			display: none;

			position: fixed;

			top: 0;

			left: 0;

			width: 100vw;

			height: 100vh;

			background: rgba(30, 41, 59, 0.25);

			backdrop-filter: blur(2px);

			z-index: 9999;

			align-items: center;

			justify-content: center;

		}
		
		.modal-card-flow {

			width: 400px;

			background: #262525;

			color: #ffffff;

			border-radius: 16px;

			animation: scaleIn .22s cubic-bezier(.4, 0, .2, 1);

			font-family: system-ui, sans-serif;

			padding: 0;

		}
		
		.modal-header {

			padding: 22px 28px 12px 28px;

			border-bottom: 1px solid #f3f4f6;

			display: flex;

			justify-content: space-between;

			align-items: center;

		}
		
		.modal-title {

			font-size: 1.15rem;

			font-weight: 700;

			color: #fff;

		}
		
		.modal-close {

			background: none;

			border: none;

			padding: 6px;

			cursor: pointer;

			border-radius: 6px;

			transition: background 0.15s;

		}
		
		.modal-close:hover {

			background: #f1f5f9;

		}
		
		.modal-close svg {

			width: 20px;

			height: 20px;

			color: #64748b;

		}
		
		.modal-body {

			padding: 18px 28px 10px 28px;

			font-size: 15px;

			line-height: 1.6;

			color: #fff;

			text-align: left;

		}
		
		.modal-checkbox {

			margin-top: 16px;

			display: flex;

			gap: 10px;

			font-size: 14px;

			align-items: center;

			color: #fff;

		}
		
		.modal-checkbox input[type="checkbox"] {

			width: 16px;

			height: 16px;

			accent-color: #6366f1;

		}
		
		.modal-footer {

			padding: 16px 28px 22px 28px;

			display: flex;

			justify-content: flex-end;

			gap: 12px;

		}
		
		.btn-cancel {

			padding: 8px 20px;

			background: #353535;

			border: 1px solid #444;

			border-radius: 8px;

			color: #fff;

			cursor: pointer;

			font-size: 14px;

			font-weight: 500;

			transition: background 0.15s, border 0.15s;

		}
		
		.btn-cancel:hover {

			background: #444;

			border-color: #666;

		}
		
		.btn-delete {

			padding: 8px 20px;

			background: #ef4444;

			border-radius: 8px;

			color: #fff;

			cursor: pointer;

			font-size: 14px;

			font-weight: 600;

			border: none;

			box-shadow: 0 2px 8px rgba(239, 68, 68, 0.08);

			transition: background 0.15s;

		}
		
		.btn-delete:hover {

			background: #b91c1c;

		}
		
		.edit-btn:hover {

			background: none !important;

		}
		
		.thumb {

			width: 100%;

			height: 120px;

			display: flex;

			align-items: center;

			justify-content: center;

			overflow: hidden;

			border-radius: 10px;

			background: #e5e7eb;

			margin-bottom: 12px;

		}
		
		.thumb img {

			width: 100%;

			height: 100%;

			object-fit: cover;

			border-radius: 10px;

		}
		
		.status-btn {

			display: inline-flex;

			align-items: center;

			gap: 7px;

			padding: 4px 14px;

			border-radius: 10px;

			font-size: 13px;

			font-weight: bold;

			border: none;

			cursor: pointer;

			background: #23272b;

			color: #fff;

			box-shadow: 0 0 0 1px #444;

			transition: background 0.2s;

		}
		
		.status-dropdown {

			position: relative;

			display: inline-block;

		}
		
		.status-dropdown-toggle {

			display: inline-flex;

			align-items: center;

			gap: 7px;

			padding: 4px 12px;

			border-radius: 8px;

			font-size: 13px;

			font-weight: bold;

			border: none;

			cursor: pointer;

			background: #23272b;

			color: #fff;

			box-shadow: 0 0 0 1px #444;

			transition: background 0.2s;

		}
		
		.status-dropdown-menu {

			display: none;

			position: absolute;

			top: 110%;

			left: 0;

			min-width: 120px;

			background: #23272b;

			border-radius: 8px;

			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);

			z-index: 10001;

			padding: 4px 0;

		}
		
		.status-dropdown-menu.show {

			display: block;

		}
		
		.status-dropdown-item {

			display: flex;

			align-items: center;

			gap: 7px;

			padding: 6px 16px;

			font-size: 13px;

			color: #fff;

			cursor: pointer;

			border: none;

			background: none;

			transition: background 0.15s;

		}
		
		.status-dropdown-item:hover {

			background: #353535;

		}
		
		.status-dot {

			width: 10px;

			height: 10px;

			border-radius: 50%;

			display: inline-block;

		}
		
		.status-dot.published {

			background: #34d399;

		}
		
		.status-dot.draft {

			background: #ef4444;

		}
		
		.status-dot.archived {

			background: #6366f1;

		}

		@keyframes scaleIn {

			from {

				transform: scale(0.9);

				opacity: 0;

			}

			to {

				transform: scale(1);

				opacity: 1;

			}

		}

	</style>

</head>

<body>

    <!-- Preloader -->

    <div class="preloader" id="preloader">

        <div class="preloader-spinner"></div>

    </div>
	
	<!-- Delete Modal (moved from top) -->

	<div id="deleteModal" class="modal-overlay" style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; padding:28px; background:rgba(0,0,0,0.6);">

		<div class="modal-card-flow" style="background:#1f1f1f;color:#fff;border-radius:12px; width:420px; max-width:96%; box-shadow:0 18px 40px rgba(2,6,23,0.7); overflow:hidden;">

			<div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid rgba(255,255,255,0.06);">

				<h3 class="modal-title" style="color:#fff;font-weight:700;display:flex;align-items:center;gap:12px;margin:0;font-size:1.05rem;">

					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#ef4444"

						style="width:20px;height:20px;vertical-align:middle;margin-top:2px;">

						<path

							d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />

						<path fill-rule="evenodd"

							d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z"

							clip-rule="evenodd" />

					</svg>

					<?= $lang['delete_post_title'] ?>

				</h3>

				<button class="modal-close" onclick="closeDeleteModal()"

					style="background:none;border:none;padding:8px;cursor:pointer;border-radius:6px;transition:background 0.15s;color:rgba(255,255,255,0.9);">

					<svg xmlns="http://www.w3.org/2000/svg" class="icon" fill="none" viewBox="0 0 24 24" stroke="#fff"

						style="width:18px;height:18px;">

						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"

							d="M6 18L18 6M6 6l12 12" />

					</svg>

				</button>

			</div>

			<div class="modal-body" style="font-size:15px;line-height:1.6;color:#fff;text-align:left;padding:18px 20px;">

				<p style="color: white; margin:0 0 10px 0; font-weight:600;"><?= $lang['delete_post_confirm_message'] ?></p>

				<p style="color:#9ca3af; font-size:13px; margin:0 0 8px 0;"><?= $lang['delete_post_warning'] ?></p>
				
				<p id="confirmInstruction" style="color:#cbd5e1; font-size:13px; margin:0 0 6px 0;"><?= $lang['delete_post_confirm_instruction'] ?> "<span id="confirmInstructionTitle" style="font-weight:600;color:#fff;"></span>"</p>

				<div style="margin-top:8px; display:flex; flex-direction:column; gap:8px;">

					<input id="confirmTitleInput" type="text" placeholder="<?= $lang['enter_post_title_placeholder'] ?>" aria-label="<?= $lang['enter_post_title_placeholder'] ?>" style="padding:8px 10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:#fff;outline:none;" />

					<div id="confirmTitleHelp" style="color:#9ca3af;font-size:12px; display:none;"><?= $lang['title_does_not_match'] ?></div>

				</div>

			</div>

			<div class="modal-footer" style="padding:16px 20px 20px 20px;display:flex;justify-content:flex-end;gap:12px;">

				<button class="btn-cancel" onclick="closeModal()"

                    style="padding:8px 20px;background:#e5e7eb;border:1px solid #d1d5db;border-radius:8px;color:#222;cursor:pointer;font-size:14px;font-weight:500;transition:background 0.15s, border 0.15s;"><?= $lang['action_cancel'] ?></button>

                <button id="confirmDeleteBtn" class="btn-delete" onclick="proceedDelete()" disabled

                    style="padding:8px 20px;background:#ef4444;border-radius:8px;color:#fff;cursor:not-allowed;font-size:14px;font-weight:700;border:none;box-shadow:0 6px 18px rgba(239,68,68,0.18);transition:background 0.15s;opacity:0.6;"><?= $lang['action_delete_perm'] ?></button>

			</div>

		</div>

	</div>

	<script>

		let deletePostId = null;

		function confirmDelete(postId, postTitle) {

			deletePostId = postId;

			const titleDisplay = document.getElementById('deletePostTitleDisplay');

			const input = document.getElementById('confirmTitleInput');

			const help = document.getElementById('confirmTitleHelp');

			const confirmBtn = document.getElementById('confirmDeleteBtn');

			if (titleDisplay) titleDisplay.textContent = postTitle || '';

			const deleteSentenceTitle = document.getElementById('deleteSentenceTitle');

			const confirmInstructionTitle = document.getElementById('confirmInstructionTitle');

			if (deleteSentenceTitle) deleteSentenceTitle.textContent = postTitle || '';

			if (confirmInstructionTitle) confirmInstructionTitle.textContent = postTitle || '';

			if (input) {

				input.value = '';

				input.setAttribute('data-target-title', postTitle || '');

				input.focus();

			}

			if (help) help.style.display = 'none';

			if (confirmBtn) {

				confirmBtn.disabled = true;

				confirmBtn.style.cursor = 'not-allowed';

				confirmBtn.style.opacity = '0.6';

			}

			document.getElementById('deleteModal').style.display = 'flex';
			
			if (input) {

				input.oninput = function () {

					const target = this.getAttribute('data-target-title') || '';

					const ok = this.value.trim() === target.trim();

					if (confirmBtn) {

						confirmBtn.disabled = !ok;

						confirmBtn.style.cursor = ok ? 'pointer' : 'not-allowed';

						confirmBtn.style.opacity = ok ? '1' : '0.6';

					}

					if (help) help.style.display = ok ? 'none' : (this.value.trim().length ? 'block' : 'none');

				};

				input.onkeydown = function (e) {

					if (e.key === 'Enter' && !document.getElementById('confirmDeleteBtn').disabled) {

						proceedDelete();

					}

				};

			}

		}

		function closeDeleteModal() {

			document.getElementById('deleteModal').style.display = 'none';

			deletePostId = null;

		}

		function proceedDelete() {

			if (deletePostId) {

				fetch('delete_forever.php', {

					method: 'POST',

					headers: {

						'Content-Type': 'application/x-www-form-urlencoded',

						'X-Requested-With': 'XMLHttpRequest'

					},

					body: 'id=' + encodeURIComponent(deletePostId)

				})

					.then(r => r.json())

					.then(data => {

						closeDeleteModal();

						document.querySelectorAll('.toast-card, .toast-backdrop').forEach(e => e.remove());

						if (data.success) {

							if (typeof showNotification === 'function') {

								showNotification({ type_class: 'success', message: data.message, title: data.title });

							}

							setTimeout(() => location.reload(), 2000);

						} else {

							if (typeof showNotification === 'function') {

								showNotification({ type_class: 'error', message: data.message || '<?= $lang['failed_delete_post'] ?>' });

							}

						}

					})

					.catch(() => {

						closeDeleteModal();

						document.querySelectorAll('.toast-card, .toast-backdrop').forEach(e => e.remove());

						if (typeof showNotification === 'function') {

							showNotification({ type_class: 'error', message: '<?= $lang['failed_delete_post'] ?>' });

						}

					});

			}

		}

		document.addEventListener('keydown', function (e) {

			if (e.key === 'Escape') {

				closeDeleteModal();

			}

		});

		var deleteModal = document.getElementById('deleteModal');

		if (deleteModal) {

			deleteModal.addEventListener('click', function (e) {

				if (e.target === this) {

					closeDeleteModal();

				}

			});

		}

	</script>

	<div class="layout">
		<?php $sidebarVariant = 'blog';

		$activeMenu = 'archived';

		include __DIR__ . '/../partials/sidebar.php'; ?>

		<main class="content">
			<!-- Blur Background Theme -->
			<div class="blur-bg-theme bottom-right"></div>
			<div class="blur-bg-theme top-left"></div>

            <?php

			$pageTitle = $lang['archived_posts_title'];

			include __DIR__ . '/../partials/topbar.php';

			?>

			<div style="margin-bottom:24px; display:flex; justify-content:space-between; align-items:center;">

				<div style="display:flex; align-items:center; gap:12px;">

                    <span style="color: #888; font-size: 14px;"><?= count($posts) ?> <?= $lang['archived_posts_found'] ?? 'posts found' ?></span>

                </div>

				<div style="display:flex; gap:12px; align-items:center; position:relative;">

					<div style="display:flex; align-items:center; gap:12px; margin-left: auto;">

                    <button id="filtersToggleBtn" style="display:flex; align-items:center; gap:8px; padding:8px 16px; background:#232323; color:#fff; border:1px solid #333; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer; z-index:20;">

						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">

							<path d="M18.75 12.75h1.5a.75.75 0 0 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM12 6a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 6ZM12 18a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 12 18ZM3.75 6.75h1.5a.75.75 0 1 0 0-1.5h-1.5a.75.75 0 0 0 0 1.5ZM5.25 18.75h-1.5a.75.75 0 0 1 0-1.5h1.5a.75.75 0 0 1 0 1.5ZM3 12a.75.75 0 0 1 .75-.75h7.5a.75.75 0 0 1 0 1.5h-7.5A.75.75 0 0 1 3 12ZM9 3.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5ZM12.75 12a2.25 2.25 0 1 1 4.5 0 2.25 2.25 0 0 1-4.5 0ZM9 15.75a2.25 2.25 0 1 0 0 4.5 2.25 2.25 0 0 0 0-4.5Z" />

						</svg>

						<?= $lang['filter_toggle'] ?>

					</button>

					</div>
					
					<!-- Filters Dropdown Panel -->

					<div id="filtersDropdown" class="filters-dropdown" style="display:none; position:fixed; background:#1a1a1a; border:1px solid #333; border-radius:8px; padding:16px; min-width:280px; z-index:9999; box-shadow:0 10px 40px rgba(0,0,0,0.5);">

						<div style="font-size:15px; font-weight:600; color:#fff; margin-bottom:12px;"><?= $lang['filter_dialog_title'] ?></div>

						<div style="margin-bottom:16px;">

							<label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_sort'] ?></label>

							<select id="filter-sort-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">

								<option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>><?= $lang['sort_newest'] ?? 'Newest first' ?></option>

								<option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>><?= $lang['sort_oldest'] ?? 'Oldest first' ?></option>

								<option value="popular" <?= $sortBy === 'popular' ? 'selected' : '' ?>><?= $lang['sort_popular'] ?? 'Most Popular' ?></option>

							</select>

						</div>
						
						<div style="margin-bottom:16px;">

							<label style="display:block; font-size:13px; color:#9ca3af; margin-bottom:8px; font-weight:500;"><?= $lang['filter_label_category'] ?></label>

							<select id="filter-category-dropdown" class="filter-select" style="width:100%; padding:8px 12px; border:1px solid #333; border-radius:6px; background:#232323; color:#fff; font-size:14px; cursor:pointer;">

								<?php foreach ($allCategories as $cat): ?>

									<option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === (string) $cat ? 'selected' : '' ?>><?= htmlspecialchars((string) $cat) ?></option>

								<?php endforeach; ?>

							</select>

						</div>
						
						<div style="display:flex; gap:8px;">

							<button id="apply-filters" style="flex:1; padding:8px; background:linear-gradient(135deg, #7675ec 0%, #d225d7 100%); color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer;"><?= $lang['action_apply'] ?></button>

							<button id="clear-filters" style="padding:8px 16px; background:#333; color:#fff; border:none; border-radius:6px; font-size:14px; cursor:pointer;"><?= $lang['action_clear'] ?></button>

						</div>

					</div>

				</div>

			</div>
			
			<div class="grid-container" id="tour-archive-grid">

				<?php foreach ($posts as $index => $post): ?>

					<?php

					$commentCountForPost = $db->comments->countDocuments(['post_id' => $post->_id]);

					$contentBlockCountForPost = 0;

					$contentHtml = (string) ($post->content_de ?? '');

					if (trim($contentHtml) !== '') {

						preg_match_all('/<(p|h[1-6]|blockquote|img|ul|ol|hr|figure|pre|iframe)(\s|>)/i', $contentHtml, $m);

						$contentBlockCountForPost = isset($m[0]) ? count($m[0]) : 0;

					}

					?>

					<div class="card" data-post-id="<?= (string) $post->_id ?>" style="display: flex; flex-direction: column;">

						<div class="thumb">

							<?php if (!empty($post->image)): ?>

								<img src="../image.php?id=<?= $post->image ?>" alt="Thumbnail" loading="lazy">

							<?php else: ?>

								<span style="color: #9ca3af; font-size: 15px;"><?= $lang['no_image'] ?></span>

							<?php endif; ?>

						</div>

						<div class="card-body" style="display: flex; flex-direction: column; flex: 1;">

							<div

								style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px; gap: 8px; flex-wrap: nowrap;">

								<div style="min-width: 0; flex: 1;">

                                    <?php

									$titleWords = explode(' ', trim($post->title_de ?? ''));

									$truncatedTitle = implode(' ', array_slice($titleWords, 0, 2));

									if (count($titleWords) > 2) {

										$truncatedTitle .= ' ...';

									}

									?>

									<h3 style="margin: 0;"><?= $truncatedTitle ?></h3>

								</div>

								<div class="status-dropdown" style="position: relative; display: inline-block;">

									<div class="status-dropdown">

										<button class="status-dropdown-toggle" <?php if ($index === 0) echo 'id="tour-restore-dropdown"'; ?> onclick="toggleStatusDropdown(this)">

											<span class="status-dot archived"></span>

											<?= $lang['status_archived'] ?>

											<svg style="margin-left:4px;width:14px;height:14px;vertical-align:middle;"

												xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"

												stroke="currentColor">

												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"

													d="M19 9l-7 7-7-7" />

											</svg>

										</button>

										<div class="status-dropdown-menu">

											<div class="status-dropdown-item" onclick="restorePost('<?= $post->_id ?>')">

												<span class="status-dot published"></span> <?= $lang['action_restore'] ?>

											</div>

											<div class="status-dropdown-item"

												onclick="confirmDelete('<?= $post->_id ?>', '<?= addslashes($post->title_de ?? '') ?>', <?= (int) $commentCountForPost ?>, <?= (int) $contentBlockCountForPost ?>)">

												<span class="status-dot draft"></span> <?= $lang['action_delete_forever'] ?>

											</div>

										</div>

									</div>

								</div>

                                <script>

									function toggleStatusDropdown(btn) {

										const menu = btn.nextElementSibling;

										document.querySelectorAll('.status-dropdown-menu.show').forEach(m => {

											if (m !== menu) m.classList.remove('show');

										});

										menu.classList.toggle('show');

									}

									document.addEventListener('click', function (e) {

										if (!e.target.closest('.status-dropdown')) {

											document.querySelectorAll('.status-dropdown-menu.show').forEach(m => m.classList.remove('show'));

										}

									});

                                    if (typeof window.restorePost === 'undefined') {

                                        window.restorePost = function(postId) {

                                            fetch('update_status.php', {

                                                method: 'POST',

                                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },

                                                body: 'id=' + encodeURIComponent(postId) + '&status=draft'

                                            })

                                            .then(r => r.json())

                                            .then(data => {

                                                if (data.success) { 

                                                    showNotification({ type_class: 'success', message: '<?= $lang['post_restored_draft'] ?>' }); 

                                                    setTimeout(() => location.reload(), 2000); 

                                                }

												else { showNotification({ type_class: 'error', message: '<?= $lang['failed_restore_post'] ?>', title: '<?= addslashes($lang['toast_title'] ?? 'Information') ?>' }); }

											}).catch(() => showNotification({ type_class: 'error', message: '<?= $lang['failed_restore_post'] ?>', title: '<?= addslashes($lang['toast_title'] ?? 'Information') ?>' }));

                                        };

                                    }

                                </script>

							</div>
							
                            <div style="margin-top: auto;">

                                <p style="color: #ccc; font-size: 13px; margin-top: 8px;">

                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"

                                        style="width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: 4px;">

                                        <path

                                            d="M12.75 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM7.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM8.25 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM9.75 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM10.5 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM12 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM12.75 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM14.25 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 17.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 15.75a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5ZM15 12.75a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0ZM16.5 13.5a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" />

                                        <path fill-rule="evenodd"

                                            d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z"

                                            clip-rule="evenodd" />

                                    </svg>

                                    <?php

									$dt = $post->created_at->toDateTime();

									$dt->setTimezone(new DateTimeZone('Europe/Amsterdam'));

									if (class_exists('IntlDateFormatter')) {

										$locale = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'de_DE' : 'en_US';

										$pattern = (isset($defaultLanguage) && $defaultLanguage === 'de') ? 'dd MMMM, yyyy HH:mm' : 'MMMM dd, yyyy HH:mm';

										$fmt = new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT, 'Europe/Amsterdam', IntlDateFormatter::GREGORIAN, $pattern);

										echo $fmt->format($dt);

									} else {

										if (isset($defaultLanguage) && $defaultLanguage === 'de') {

											$months_full = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

											$mIdx = max(1, (int) $dt->format('n')) - 1;

											$monthLabel = $months_full[$mIdx] ?? $dt->format('F');

											echo $dt->format('d') . ' ' . $monthLabel . ', ' . $dt->format('Y H:i');

										} else {

											$months_full = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

											$mIdx = max(1, (int) $dt->format('n')) - 1;

											$monthLabel = $months_full[$mIdx] ?? $dt->format('F');

											echo $monthLabel . ' ' . $dt->format('d, Y H:i');

										}

									}

									?>

                                </p>

                            </div>

						</div>

					</div>

				<?php endforeach; ?>

			</div>

		</main>

	</div>

	<script>

	document.addEventListener('DOMContentLoaded', function () {

		(function(){

			const categorySelect = document.getElementById('filter-category-dropdown');

			if (categorySelect) {

				categorySelect.innerHTML = '';

				const currentCategory = new URLSearchParams(window.location.search).get('category') || '';

				const categories = <?= json_encode($allCategories) ?>;

				categories.forEach(cat => {

					const opt = document.createElement('option');

					opt.value = cat;

					opt.textContent = cat;

					if (cat === currentCategory) opt.selected = true;

					categorySelect.appendChild(opt);

				});

			}
			
			const btn = document.getElementById('filtersToggleBtn');

			const filtersDropdown = document.getElementById('filtersDropdown');

			const applyBtn = document.getElementById('apply-filters');

			const clearBtn = document.getElementById('clear-filters');
			
			if (applyBtn) {

				applyBtn.addEventListener('click', function() {

					const categoryFilter = document.getElementById('filter-category-dropdown').value;

					const sortFilter = document.getElementById('filter-sort-dropdown').value;

					const url = new URL(window.location.href);

					url.searchParams.delete('page');

					if (categoryFilter) url.searchParams.set('category', categoryFilter);

					if (sortFilter) url.searchParams.set('sort', sortFilter);

					window.location.href = url.toString();

				});

			}

			if (clearBtn) {

				clearBtn.addEventListener('click', function() {

					const url = new URL(window.location.href);

					url.searchParams.delete('category');

					url.searchParams.delete('sort');

					url.searchParams.delete('page');

					window.location.href = url.toString();

				});

			}
			
			if (btn && filtersDropdown) {

				btn.addEventListener('click', (e) => {

					e.stopPropagation();

					if (filtersDropdown.style.display === 'block') {

						filtersDropdown.style.display = 'none';

						btn.style.borderColor = '#333';

					} else {

						const rect = btn.getBoundingClientRect();

						filtersDropdown.style.top = (rect.bottom + 8) + 'px';

						filtersDropdown.style.left = 'auto';

						filtersDropdown.style.right = (window.innerWidth - rect.right) + 'px';

						filtersDropdown.style.display = 'block';

						btn.style.borderColor = '#777';

					}

				});

				document.addEventListener('click', (e) => {

					if (!btn.contains(e.target) && !filtersDropdown.contains(e.target)) {

						filtersDropdown.style.display = 'none';

						btn.style.borderColor = '#333';

					}

				});

			}

		})();
		
		document.querySelectorAll('.toast-backdrop').forEach(el => el.remove());
		
		document.querySelectorAll('.status-dropdown-toggle').forEach(btn => {

			btn.onclick = null;

			btn.addEventListener('click', function (e) {

				e.stopPropagation();

				const dropdown = btn.closest('.status-dropdown');

				if (!dropdown) return;

				const menu = dropdown.querySelector('.status-dropdown-menu');

				if (!menu) return;

				document.querySelectorAll('.status-dropdown-menu.show').forEach(m => { if (m !== menu) m.classList.remove('show'); });

				menu.classList.toggle('show');

			});

		});

		document.addEventListener('click', function () {

			document.querySelectorAll('.status-dropdown-menu.show').forEach(m => m.classList.remove('show'));

		});

	});

</script>

    <script>

        const preloaderStart = Date.now();

        window.addEventListener('load', function() {

            const preloader = document.getElementById('preloader');

            if (preloader) {

                const elapsed = Date.now() - preloaderStart;

                const minDisplayTime = 500;

                const remainingTime = Math.max(0, minDisplayTime - elapsed);

                

                setTimeout(() => {

                    preloader.classList.add('fade-out');

                    setTimeout(() => {

                        preloader.style.display = 'none';

                    }, 300);

                }, remainingTime);

            }

        });

    </script>

</body>

</html>
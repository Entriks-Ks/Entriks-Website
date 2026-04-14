<?php
require_once __DIR__ . '/session_config.php';
require_once __DIR__ . '/database.php';

if (($_SESSION['admin']['role'] ?? 'admin') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$activeMenu = 'team_management';
require_once __DIR__ . '/config.php';

$team = $db->admins->find([], ['sort' => ['created_at' => -1]])->toArray();

?>
<!DOCTYPE html>
<html lang="<?= $defaultLanguage ?>">
<head>
            <style>
                /* Delete Modal Styles (copied from comments.php) */
                .modal-overlay.active {
                    display: flex;
                }
                .modal-card-flow {
                    width: 440px;
                    background: #1a1a1a;
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    border-radius: 16px;
                    padding: 0;
                    text-align: left;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                    transform: scale(0.95) translateY(20px);
                    opacity: 0;
                    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
                    overflow: hidden;
                    color: #fff;
                }
                .modal-overlay.active .modal-card-flow {
                    transform: scale(1) translateY(0);
                    opacity: 1;
                }
                .modal-header {
                    padding: 20px 24px;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: rgba(255, 255, 255, 0.02);
                }
                .modal-title {
                    font-size: 16px;
                    font-weight: 600;
                    color: white;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .modal-close {
                    background: none;
                    border: none;
                    padding: 4px;
                    cursor: pointer;
                    color: #9ca3af;
                    transition: color 0.2s;
                    display: flex;
                    align-items: center;
                }
                .modal-body {
                    padding: 24px;
                    font-size: 15px;
                    color: #fff;
                }
                .modal-footer {
                    padding: 20px 24px;
                    border-top: 1px solid rgba(255,255,255,0.05);
                    display: flex;
                    justify-content: flex-end;
                    gap: 16px;
                    background: rgba(255,255,255,0.02);
                }
                .btn-cancel {
                    background: none;
                    border: 1px solid #444;
                    color: #aaa;
                    padding: 8px 18px;
                    border-radius: 8px;
                    font-size: 14px;
                    cursor: pointer;
                    transition: background 0.2s, color 0.2s;
                }
                .btn-cancel:hover {
                    background: #222;
                    color: #fff;
                }
                .btn-delete.danger {
                    background: linear-gradient(135deg, #ef4444 0%, #d225d7 100%);
                    border: none;
                    color: #fff;
                    padding: 8px 18px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    box-shadow: 0 2px 8px rgba(239,68,68,0.15);
                    transition: background 0.2s, box-shadow 0.2s;
                }
                .btn-delete.danger:hover {
                    background: linear-gradient(135deg, #d225d7 0%, #ef4444 100%);
                    box-shadow: 0 4px 16px rgba(239,68,68,0.25);
                }
            </style>
        <script>
            window.lang = {
                team_role_admin: "<?= addslashes($lang['team_role_admin'] ?? 'Admin') ?>",
                team_role_content_manager: "<?= addslashes($lang['team_role_content_manager'] ?? 'Content Manager') ?>",
                team_role_editor: "<?= addslashes($lang['team_role_editor'] ?? 'Editor') ?>",
                team_role_author: "<?= addslashes($lang['team_role_author'] ?? 'Author') ?>"
            };
        </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $lang['team_title'] ?></title>
    <link rel="shortcut icon" href="<?= htmlspecialchars($siteFaviconUrl ?? 'assets/img/favicon.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= time() ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .team-container {
            margin: 0 auto;
        }

        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 40px;
        }

        .team-header h1 {
            color: #fff;
            font-size: 2.2rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            margin: 0;
        }

        .team-header p {
            color: #a0a0a0;
            font-size: 0.95rem;
            margin-top: 6px;
            margin-bottom: 0;
        }

        .team-header { display: none; }
        .team-container { padding-top: 10px; }
        
        .filter-action-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-action-btn:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2); }
        
        .invite-btn {
            background: linear-gradient(135deg, #7675ec 0%, #d225d7 100%);
            padding: 10px 20px;
            border-radius: 12px;
            border: none;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(118,117,236,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .invite-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(118,117,236,0.4); }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            justify-content: start;
        }

        .member-card {
            position: relative;
            padding: 24px;
            border-radius: 20px;
            background: rgba(30, 30, 34, 0.6);
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
        }

        .member-avatar {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(120,80,255,0.15), rgba(120,80,255,0.05));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7675EC;
            box-shadow: 0 0 18px rgba(120,80,255,0.15);
            flex-shrink: 0;
        }

        .member-info {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 22px;
        }

        .member-details h3 {
            color: #fff;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .member-details p {
            color: #aaa;
            margin-top: 5px;
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        /* --- ROLE + STATUS --- */

        .member-meta {
            border-top: 1px solid rgba(255,255,255,0.08);
            padding-top: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ROLE BADGES */
        .role-badge {
            padding: 6px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
            backdrop-filter: blur(4px);
        }

        .role-admin {
            color: #b983ff;
            border-color: rgba(185,131,255,0.4);
            background: rgba(185,131,255,0.12);
        }

        .role-content-manager {
            color: #3ecfff;
            border-color: rgba(62,207,255,0.4);
            background: rgba(62,207,255,0.12);
        }

        .role-editor {
            color: #7675EC;
            border-color: rgba(118,117,236,0.4);
            background: rgba(118,117,236,0.12);
        }

        .role-author {
            color: #ffbe55;
            border-color: rgba(255,190,85,0.4);
            background: rgba(255,190,85,0.12);
        }

        /* Status */
        .status-badge {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge svg circle {
            animation: pulse 2s infinite ease-in-out;
        }

        @keyframes pulse {
            0%,100% { opacity: 0.4; }
            50% { opacity: 1; }
        }

        .status-posts   { color: #7675EC; }
        .status-pending { color: #ffbe55; }

        /* --- MEMBER ACTION MENU --- */

        .member-actions {
            position: absolute;
            top: 18px;
            right: 18px;
            padding: 8px;
            border-radius: 10px;
            background: rgba(255,255,255,0.06);
            color: #d6d6d6;
            cursor: pointer;
            transition: 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .member-actions:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
        }

        /* Dropdown */
        .member-menu {
            position: absolute;
            top: 48px;
            right: 0;
            background: rgba(18,18,18,0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.06);
            padding: 10px;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            display: none;
            z-index: 10;
            min-width: 160px;
        }

        .member-menu.active { display: block; }

        .menu-item-edit,
        .menu-item-delete,
        .menu-item-resend {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.88rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .menu-item-edit   { color: #7675EC; }
        .menu-item-resend { color: #6ba8ff; }
        .menu-item-delete { color: #ff7070; }

        .menu-item-edit:hover   { background: rgba(120,80,255,0.15); }
        .menu-item-resend:hover { background: rgba(80,140,255,0.15); }
        .menu-item-delete:hover { background: rgba(255,80,80,0.15); }

        /* --- MODAL --- */
        @media (max-width: 600px) {
            .modal-content {
                padding: 16px;
                max-width: 98vw;
                border-radius: 12px;
            }
            .modal-perms-grid {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            .modal-actions {
                flex-direction: column;
                gap: 12px;
                margin-top: 18px;
            }
            .perm-display {
                flex-direction: column;
                gap: 6px;
            }
            .modal-close {
                top: 12px;
                right: 12px;
                font-size: 22px;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            backdrop-filter: blur(6px);
        }

        .modal-content {
            background: rgba(18,18,18,0.92);
            border-radius: 20px;
            padding: 32px;
            width: 100%;
            max-width: 470px;
            border: 1px solid rgba(255,255,255,0.08);
            backdrop-filter: blur(15px);
            box-shadow: 0 0 50px rgba(0,0,0,0.6);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        .modal-content h3 {
            color: #fff;
            margin-bottom: 8px;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .modal-content p.modal-desc {
            color: rgba(255,255,255,0.5);
            font-size: 0.88rem;
            margin-bottom: 24px;
        }

        .modal-close {
            position: absolute;
            top: 24px;
            right: 24px;
            background: none;
            border: none;
            color: rgba(255,255,255,0.4);
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #fff;
        }

        .modal-field label {
            display: block;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
            margin-top: 16px;
            font-weight: 500;
        }

        .modal-field input,
        .modal-field select {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .modal-field input:focus,
        .modal-field select:focus {
            outline: none;
            border-color: rgba(120,80,255,0.5);
        }

        .modal-field select option {
            background: #1a1a1a;
            color: #fff;
        }

        .modal-field input[type="text"]:disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        .perm-display {
            margin-top: 4px;
        }

        .perm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .perm-badge {
            display: block;
            width: 100%;
            margin: 0;
            padding: 14px 16px;
            border-radius: 8px;
            background: #2a1a4d;
            color: #cfcfff;
            font-size: 12px;
            text-align: left;
            border: 1.2px solid #8a6cff;
            box-shadow: 0 1px 4px #0002;
            vertical-align: top;
        }

        .perm-badge.perm-access {
            border-color: #8a6cff;
            background: #2a1a4d;
            color: #cfcfff;
        }

        .perm-badge.perm-noaccess {
            opacity: 0.5;
            border-color: #444;
            background: #222;
            color: #aaa;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 24px;
        }

        .modal-btn-submit {
            padding: 10px 22px;
            background: linear-gradient(135deg, #6d5dfc, #855aff);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 0 15px rgba(120,80,255,0.3);
            transition: 0.2s ease;
        }

        .modal-btn-submit:hover {
            box-shadow: 0 0 22px rgba(120,80,255,0.5);
            transform: translateY(-1px);
        }

        .modal-btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

    </style>

</head>
<body>
    <div class="preloader" id="preloader">
        <div class="preloader-spinner"></div>
    </div>
    
    <div class="layout">
        <?php
        $sidebarVariant = 'dashboard';
        $activeMenu = 'team_management';
        include __DIR__ . '/partials/sidebar.php';
        ?>
        
        <main class="content">
            <!-- Blur Background Theme -->
            <div class="blur-bg-theme bottom-right"></div>

            <?php
            $pageTitle = $lang['team_title'];
            $showWelcomeMessage = false;
            include __DIR__ . '/partials/topbar.php';
            ?>
            
            <div class="team-container" id="team-list">
                <!-- Action Bar -->
                <div style="margin-bottom:32px; display:flex; justify-content: flex-end; align-items:center;">
                    <div style="display:flex; gap:12px; align-items:center;">
                        <button class="invite-btn" onclick="openInviteModal()">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            <?= $lang['team_invite_btn'] ?>
                        </button>
                    </div>
                </div>

                <div class="team-grid">
                    <?php foreach ($team as $member): ?>
                        <?php if (($member['email'] ?? '') === 'admin@entriks.com') continue; ?>
                        <div class="member-card">
                            <div class="member-actions" onclick="toggleMenu(event, '<?= (string) $member['_id'] ?>')">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                </svg>
                                <div id="menu-<?= (string) $member['_id'] ?>" class="member-menu">
                                    <?php if (($member['status'] ?? 'active') === 'pending'): ?>
                                    <div class="menu-item-resend" onclick="resendInvitation('<?= (string) $member['_id'] ?>')">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 012.22 0L21 8M5 19h14a2 2 0 012-2V7a2 2 0 01-2-2H5a2 2 0 01-2 2v10a2 2 0 012 2z" />
                                        </svg>
                                        <?= $lang['team_resend_btn'] ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="menu-item-edit" 
                                         data-member="<?= htmlspecialchars(json_encode([
        'id' => (string) $member['_id'],
        'display_name' => $member['display_name'] ?? '',
        'email' => $member['email'],
        'position' => $member['position'] ?? '',
        'permissions' => isset($member['permissions']) ? (is_array($member['permissions']) ? $member['permissions'] : (array) $member['permissions']) : [],
        'post_count' => $db->blog->countDocuments(['author_email' => $member['email']])
    ])) ?>"
                                         onclick="openEditModal(this)">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        <?= $lang['team_edit_btn'] ?>
                                    </div>
                                    <?php if (($member['email'] ?? '') !== 'admin@entriks.com'): ?>
                                    <div class="menu-item-delete" onclick="event.stopPropagation(); deleteMember('<?= (string) $member['_id'] ?>')">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        <?= $lang['team_delete_btn'] ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="member-info">
                                <div class="member-avatar">
                                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div class="member-details">
                                    <h3><?= htmlspecialchars($member['display_name'] ?? $lang['team_label_pending']) ?></h3>
                                    <p><?= htmlspecialchars($member['email']) ?></p>
                                </div>
                            </div>
                            <div class="member-meta">
                                <span class="role-badge role-<?= str_replace(' ', '-', strtolower($member['position'] ?? 'editor')) ?> <?= (($member['role'] ?? '') === 'admin' || strtolower($member['position'] ?? '') === 'admin') ? 'role-admin' : '' ?>">
                                    <?php
                                    $pos = strtolower($member['position'] ?? 'Editor');
                                    if (($member['role'] ?? '') === 'admin' || $pos === 'admin') {
                                        $roleLabel = $lang['role_label_admin'] ?? 'ADMIN';
                                    } elseif ($pos === 'content manager') {
                                        $roleLabel = $lang['role_label_content_manager'] ?? 'CONTENT MANAGER';
                                    } elseif ($pos === 'author') {
                                        $roleLabel = $lang['role_label_author'] ?? 'AUTHOR';
                                    } else {
                                        $roleLabel = $lang['role_label_editor'] ?? 'EDITOR';
                                    }
                                    echo strtoupper($roleLabel);
                                    ?>
                                </span>
                                <?php if (($member['status'] ?? 'active') === 'pending'): ?>
                                    <span class="status-badge status-pending">
                                        <svg width="8" height="8" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        <?= $lang['team_status_pending'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-posts">
                                        <?= $lang['team_posts'] ?> <?= $db->blog->countDocuments(['author_email' => $member['email']]) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="toast-container"></div>

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

        function openInviteModal() {
            const modalHtml = `
                <div id="inviteModal" class="modal-overlay">
                    <div class="modal-content">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <h3 style="margin-bottom:0;display:flex;align-items:center;gap:8px;">
                                    <?= $lang['team_modal_title'] ?>
                                    <span class="hint-icon" onclick="showInviteHint()" style="color:#7675ec;cursor:pointer;display:inline-flex;align-items:center;">
                                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="color:#7675ec;display:inline-block;vertical-align:middle;pointer-events:none;">
                                            <path d="M13 10V2L5 14H11V22L19 10H13Z" />
                                        </svg>
                                    </span>
                                </h3>
                            </div>
                            <button type="button" class="modal-close" onclick="closeInviteModal()" style="margin-left:16px;font-size:1.5em;line-height:1;">&times;</button>
                        </div>
                        <form id="inviteForm">
                            <div class="modal-field">
                                <label><?= $lang['team_label_display_name'] ?? 'Anzeigename' ?></label>
                                <input type="text" name="display_name" required placeholder="John Doe" style="width:100%;padding:12px;background:#111;border:1px solid #333;border-radius:8px;color:white;font-size:14px;">
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_email'] ?></label>
                                <input type="email" name="email" required placeholder="editor@example.com" style="width:100%;padding:12px;background:#111;border:1px solid #333;border-radius:8px;color:white;font-size:14px;">
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_position'] ?></label>
                                <select name="position" style="width:100%; padding:12px; background:#111; border:1px solid #333; border-radius:8px; color:white; font-size:14px;" onchange="updatePermDisplay(this)">
                                    <option value="Admin"><?= $lang['team_role_admin'] ?></option>
                                    <option value="Author" selected>Author</option>
                                </select>
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_permissions'] ?></label>
                                <div class="perm-display" id="invitePermDisplay"></div>
                            </div>
                            <div class="modal-actions" style="display:flex;justify-content:center;margin-top:24px;">
                                <button type="submit" id="submitInvite" class="modal-btn-submit">
                                    <?= $lang['action_send_invitation'] ?? 'Send Invitation' ?>
                                </button>
                            </div>
                        </form>
                        <div id="inviteHint" class="hint-popup" style="display:none;position:absolute;top:70px;left:50%;transform:translateX(-50%);background:#222;color:#fff;padding:18px 24px;border-radius:12px;box-shadow:0 2px 24px #0005;font-size:13px;z-index:1000;min-width:320px;max-width:520px;">
                            <?= $lang['team_modal_desc'] ?>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            updatePermDisplay(document.querySelector('#inviteModal select[name="position"]'));
            window.showInviteHint = function() {
                const hint = document.getElementById('inviteHint');
                if (hint.style.display === 'none') {
                    hint.style.display = 'block';
                } else {
                    hint.style.display = 'none';
                }
            };
            document.addEventListener('click', function(e) {
                if (!e.target.classList.contains('hint-icon') && document.getElementById('inviteHint')) {
                    document.getElementById('inviteHint').style.display = 'none';
                }
            });
            document.getElementById('inviteForm').onsubmit = function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitInvite');
                btn.disabled = true;
                btn.innerText = '<?= $lang['team_invite_btn'] ?>';

                const formData = new FormData(this);
                const params = new URLSearchParams();
                const permMapping = {
                    'Admin': 'blog,comments,analytics',
                    'Editor': '',
                    'Author': 'blog'
                };
                for (const pair of formData.entries()) {
                    if (pair[0] !== 'perms[]') {
                        params.append(pair[0], pair[1]);
                    }
                }
                params.set('permissions', permMapping[params.get('position')] || '');

                fetch('invite_editor.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Invitation sent', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message, 'error');
                        btn.disabled = false;
                        btn.innerText = 'Send Invitation';
                    }
                })
                .catch(err => {
                    showToast('Something went wrong', 'error');
                    btn.disabled = false;
                    btn.innerText = 'Send Invitation';
                });
            };
        }

        function closeInviteModal() {
            const modal = document.getElementById('inviteModal');
            if (modal) modal.remove();
        }

        function toggleMenu(event, id) {
            event.stopPropagation();
            const menus = document.querySelectorAll('.member-menu');
            menus.forEach(m => {
                if (m.id !== 'menu-' + id) m.classList.remove('active');
            });
            document.getElementById('menu-' + id).classList.toggle('active');
        }

        document.addEventListener('click', () => {
            const menus = document.querySelectorAll('.member-menu');
            menus.forEach(m => m.classList.remove('active'));
        });

        async function deleteMember(id) {
            const confirmed = await showConfirm(<?= json_encode($lang['team_confirm_delete']) ?>);
            if (!confirmed) return;

            fetch('delete_member.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                showToast('Failed to delete member', 'error');
            });
        }

        function resendInvitation(id) {
            const btn = document.querySelector(`#menu-${id} .menu-item-resend`);
            if (btn) btn.style.pointerEvents = 'none';

            fetch('resend_invitation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(id)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                    if (btn) btn.style.pointerEvents = 'auto';
                }
            })
            .catch(err => {
                showToast('Failed to resend invitation', 'error');
                if (btn) btn.style.pointerEvents = 'auto';
            });
        }

        function openEditModal(el) {
            const member = JSON.parse(el.getAttribute('data-member'));
            const perms = member.permissions || [];
            const modalHtml = `
                <div id="editModal" class="modal-overlay">
                    <div class="modal-content">
                        <h3><?= $lang['team_modal_edit_title'] ?></h3>
                        <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
                        <form id="editForm" style="margin-top:20px;">
                            <input type="hidden" name="id" value="${member.id}">
                            <div class="modal-field">
                                <label><?= $lang['th_name'] ?></label>
                                <input type="text" name="display_name" value="${member.display_name}" readonly style="width:100%;padding:12px;background:#111;border:1px solid #333;border-radius:8px;color:white;font-size:14px;">
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_email'] ?></label>
                                <input type="email" name="email" value="${member.email}" required style="width:100%;padding:12px;background:#111;border:1px solid #333;border-radius:8px;color:white;font-size:14px;">
                                <div id="emailVerifyStatus" style="margin-top:8px;font-size:13px;color:#b983ff;"></div>
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_position'] ?></label>
                                <select name="position" onchange="updatePermDisplay(this)" style="width:100%;padding:12px;background:#111;border:1px solid #333;border-radius:8px;color:white;font-size:14px;">
                                    <option value="Admin" ${member.position === 'Admin' ? 'selected' : ''}>${lang.team_role_admin ?? 'Admin'}</option>
                                    <option value="Author" ${member.position === 'Author' || !member.position ? 'selected' : ''}>${lang.team_role_author ?? 'Author'}</option>
                                </select>
                            </div>
                            <div class="modal-field">
                                <label><?= $lang['team_label_permissions'] ?></label>
                                <div class="perm-display" id="editPermDisplay"></div>
                            </div>
                            <div class="modal-actions">
                                <button type="submit" id="submitEdit" class="modal-btn-submit"><?= $lang['action_save_changes'] ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            updatePermDisplay(document.querySelector('#editModal select[name="position"]'));

            // Show email verification status only if email was changed
            const emailVerifyStatus = document.getElementById('emailVerifyStatus');
            const emailVerified = member.email_verified ?? false;
            const emailVerificationExpires = member.email_verification_expires ?? null;
            const emailVerificationToken = member.email_verification_token ?? null;
            let statusText = '';
            let restrictActions = false;
            if (emailVerificationToken || emailVerificationExpires) {
                if (emailVerified) {
                    statusText = '<span style="color:#3ecfff;">✔ Email verified</span>';
                } else if (emailVerificationExpires) {
                    const now = new Date();
                    const expires = new Date(emailVerificationExpires);
                    const msLeft = expires - now;
                    const hoursLeft = Math.max(0, Math.floor(msLeft / (1000 * 60 * 60)));
                    if (hoursLeft > 0) {
                        statusText = `<span style="color:#ffbe55;">Email change pending verification<br>${hoursLeft} hours left to verify</span>`;
                        restrictActions = true;
                    } else {
                        statusText = `<span style="color:#ff7070;">Verification expired. Please resend verification email.</span>`;
                        restrictActions = true;
                    }
                } else {
                    statusText = '<span style="color:#ffbe55;">Email not verified</span>';
                    restrictActions = true;
                }
            }
            emailVerifyStatus.innerHTML = statusText;
            // Restrict actions if not verified
            if (restrictActions) {
                const form = document.getElementById('editForm');
                const submitBtn = document.getElementById('submitEdit');
                submitBtn.disabled = true;
                submitBtn.innerText = 'Verify email to save changes';
                form.querySelectorAll('input,select').forEach(input => {
                    if (input.name !== 'email') input.disabled = true;
                });
            }

            document.getElementById('editForm').onsubmit = function(e) {
                e.preventDefault();
                const btn = document.getElementById('submitEdit');
                btn.disabled = true;
                btn.innerText = 'Saving...';

                const formData = new FormData(this);
                const params = new URLSearchParams();
                const permMapping = {
                    'Admin': 'blog,comments,analytics',
                    'Editor': '',
                    'Author': 'blog'
                };
                for (const pair of formData.entries()) {
                    if (pair[0] !== 'perms[]') {
                        params.append(pair[0], pair[1]);
                    }
                }
                params.set('permissions', permMapping[params.get('position')] || '');
                if (!params.has('permissions')) {
                    params.set('permissions', '');
                }

                fetch('update_member.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString()
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                        btn.disabled = false;
                        btn.innerText = 'Save Changes';
                    }
                })
                .catch(err => {
                    showToast('Something went wrong', 'error');
                    btn.disabled = false;
                    btn.innerText = 'Save Changes';
                });
            };
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            if (modal) modal.remove();
        }

        function showConfirm(message) {
            return new Promise((resolve) => {
                const modalHtml = `
                <div id="confirmModal" class="modal-overlay active">
                    <div class="modal-card-flow">
                        <div class="modal-header">
                            <h3 class="modal-title">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                    <path d="M3 6h18M9 6v-2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" />
                                    <path d="M19 6l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 6" />
                                    <path d="M10 11v6" />
                                    <path d="M14 11v6" />
                                </svg>
                                <span class="modal-title-text">Delete Member?</span>
                            </h3>
                            <button class="modal-close" id="closeConfirmModalBtn">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete this member? This action cannot be undone.
                        </div>
                        <div class="modal-footer">
                            <button class="btn-cancel" id="cancelDeleteBtn">Cancel</button>
                            <button class="btn-delete danger" id="confirmDeleteBtn">Delete</button>
                        </div>
                    </div>
                </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                const modal = document.getElementById('confirmModal');
                const closeBtn = document.getElementById('closeConfirmModalBtn');
                const cancelBtn = document.getElementById('cancelDeleteBtn');
                const confirmBtn = document.getElementById('confirmDeleteBtn');
                function closeModal() {
                    if (modal) modal.remove();
                }
                closeBtn.addEventListener('click', () => { closeModal(); resolve(false); });
                cancelBtn.addEventListener('click', () => { closeModal(); resolve(false); });
                confirmBtn.addEventListener('click', () => { closeModal(); resolve(true); });
            });
        }
    </script>
    <script>
        const PERM_LABELS = {
            blog: { label: '<?= $lang['team_permission_blog'] ?? 'Blog Manager' ?>', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>' },
            comments: { label: 'Comments', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>' },
            analytics: { label: '<?= $lang['team_permission_analytics'] ?? 'Analytics' ?>', icon: '<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>' }
        };
        const PERM_MAP = {
            'Admin': ['blog','comments','analytics'],
            'Editor': [],
            'Author': ['blog']
        };
        function updatePermDisplay(select) {
            const modal = select.closest('.modal-content');
            const container = modal.querySelector('.perm-display');
            if (!container) return;
            const perms = PERM_MAP[select.value] || [];
            const permLabels = {
                blog: { label: <?= json_encode($lang['team_permission_blog']) ?>, desc: '<?= $lang['team_permission_blog_desc'] ?? 'Publish articles' ?>', icon: PERM_LABELS.blog.icon },
                comments: { label: <?= json_encode($lang['team_permission_comments'] ?? 'Comments') ?>, desc: '<?= $lang['team_permission_comments_desc'] ?? 'Moderate comments' ?>', icon: PERM_LABELS.comments.icon },
                analytics: { label: <?= json_encode($lang['team_permission_analytics']) ?>, desc: '<?= $lang['team_permission_analytics_desc'] ?? 'View analytics' ?>', icon: PERM_LABELS.analytics.icon }
            };
            const noAccessText = <?= json_encode($lang['team_permission_no_access'] ?? 'No access') ?>;
            let badgeHtml = '';
            Object.keys(permLabels).forEach(p => {
                const info = permLabels[p] || { label: p, desc: '', icon: '' };
                const hasAccess = perms.includes(p);
                badgeHtml += `<div class="perm-badge${hasAccess ? ' perm-access' : ' perm-noaccess'}"><span class="perm-row">${info.icon}<span class="perm-title">${info.label}</span></span><div class="perm-desc">${hasAccess ? info.desc : noAccessText}</div></div>`;
            });
            container.innerHTML = `<div class="perm-grid">${badgeHtml}</div>`;
            if (!document.getElementById('permBadgeStyles')) {
                const style = document.createElement('style');
                style.id = 'permBadgeStyles';
                style.innerHTML = `.perm-row { display:flex; align-items:center; gap:8px; margin-bottom:2px; }
                .perm-title { font-weight:600; font-size:13px; }
                .perm-desc { font-size:11px; margin-top:8px; color:#cfcfff; opacity:0.85; }
                .perm-noaccess .perm-desc { color:#aaa; opacity:0.7; }`;
                document.head.appendChild(style);
            }
        }
    </script>

    <script src="assets/js/global-search.js"></script>
</body>
</html>
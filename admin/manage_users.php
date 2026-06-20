<?php
/**
 * manage_users.php (admin)
 * User account CRUD dashboard.
 * Supports insertions, edits, deletions, and password/PIN resets for student and teacher profiles.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Verify admin permissions
require_role('admin');

$admin_name = $_SESSION['full_name'];
$admin_id = $_SESSION['user_id'];

$alert_success = "";
$alert_danger = "";

// -------------------------------------------------------------------------
// CRUD POST Handler
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // 1. CREATE USER
    if ($action === 'create') {
        $username = trim(filter_input(INPUT_POST, 'username', FILTER_DEFAULT));
        $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
        $role = filter_input(INPUT_POST, 'role', FILTER_DEFAULT);
        $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_DEFAULT));
        $class_section = trim(filter_input(INPUT_POST, 'class_section', FILTER_DEFAULT));
        $avatar_url = filter_input(INPUT_POST, 'avatar_url', FILTER_DEFAULT);
        
        if (empty($username) || empty($password) || !in_array($role, ['student', 'teacher', 'admin']) || empty($full_name)) {
            $alert_danger = "Required fields (Username, Password/PIN, Role, Name) are missing.";
        } else {
            try {
                // Check if username is already taken
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetchColumn() > 0) {
                    $alert_danger = "Username or Email \"$username\" is already registered.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, password_hash, role, full_name, class_section, avatar_url)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $username, 
                        $hashed_password, 
                        $role, 
                        $full_name, 
                        !empty($class_section) ? $class_section : null,
                        !empty($avatar_url) ? $avatar_url : null
                    ]);
                    
                    $new_id = $pdo->lastInsertId();
                    
                    // If creating a student, initialize gamification stats automatically
                    if ($role === 'student') {
                        $gam_stmt = $pdo->prepare("INSERT INTO gamification_stats (student_id, total_points, login_streak) VALUES (?, 0, 0)");
                        $gam_stmt->execute([$new_id]);
                    }
                    
                    $alert_success = "User account \"$full_name\" created successfully! 🎉";
                }
            } catch (PDOException $e) {
                $alert_danger = "Database insertion error: " . $e->getMessage();
            }
        }
    }
    
    // 2. UPDATE USER DETAILS
    elseif ($action === 'edit') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $username = trim(filter_input(INPUT_POST, 'username', FILTER_DEFAULT));
        $role = filter_input(INPUT_POST, 'role', FILTER_DEFAULT);
        $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_DEFAULT));
        $class_section = trim(filter_input(INPUT_POST, 'class_section', FILTER_DEFAULT));
        $avatar_url = filter_input(INPUT_POST, 'avatar_url', FILTER_DEFAULT);
        
        if (!$user_id || empty($username) || !in_array($role, ['student', 'teacher', 'admin']) || empty($full_name)) {
            $alert_danger = "Required details are missing.";
        } else {
            try {
                // Check username conflict excluding current user
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
                $check_stmt->execute([$username, $user_id]);
                if ($check_stmt->fetchColumn() > 0) {
                    $alert_danger = "Username or Email \"$username\" is in use by another account.";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, role = ?, full_name = ?, class_section = ?, avatar_url = ? 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $username, 
                        $role, 
                        $full_name, 
                        !empty($class_section) ? $class_section : null,
                        !empty($avatar_url) ? $avatar_url : null,
                        $user_id
                    ]);
                    
                    $alert_success = "User profile \"$full_name\" updated successfully.";
                }
            } catch (PDOException $e) {
                $alert_danger = "Database update error: " . $e->getMessage();
            }
        }
    }
    
    // 3. RESET PASSWORD/PIN
    elseif ($action === 'reset_pass') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $password = filter_input(INPUT_POST, 'password', FILTER_DEFAULT);
        
        if (!$user_id || empty($password)) {
            $alert_danger = "Required fields for password reset are missing.";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $alert_success = "Password/PIN reset successfully! 🔐";
            } catch (PDOException $e) {
                $alert_danger = "Database reset error: " . $e->getMessage();
            }
        }
    }
    
    // 4. DELETE USER
    elseif ($action === 'delete') {
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        
        if (!$user_id) {
            $alert_danger = "User ID invalid.";
        } elseif ($user_id == $admin_id) {
            $alert_danger = "For security reasons, you cannot delete your own admin account.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $alert_success = "Account deleted successfully from the platform.";
            } catch (PDOException $e) {
                $alert_danger = "Failed to delete user. The account may have associated records: " . $e->getMessage();
            }
        }
    }
}

// -------------------------------------------------------------------------
// Load Users List
// -------------------------------------------------------------------------
$users_list = [];
try {
    $stmt = $pdo->query("SELECT user_id, username, role, full_name, class_section, avatar_url FROM users ORDER BY role DESC, full_name ASC");
    $users_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $alert_danger = "Failed to connect and read user catalog records.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts Manager - Interactive E-Learning System</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .table-toolbar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            background-color: white;
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-slate-200);
        }
        .search-box {
            flex-grow: 1;
            min-width: 250px;
        }
        .filter-box {
            width: 180px;
        }
    </style>
</head>
<body>

    <!-- Admin Left Sidebar Menu -->
    <div class="admin-sidebar">
        <div class="sidebar-header">
            ⚙️ System Admin
        </div>
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="dashboard.php" class="sidebar-nav-link">📊 System Metrics</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_users.php" class="sidebar-nav-link active">👥 User Accounts</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="manage_curriculum.php" class="sidebar-nav-link">📖 Master Curriculum</a>
            </li>
            <li class="sidebar-nav-item">
                <a href="system_reports.php" class="sidebar-nav-link">📋 System Reports</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            Logged in as:<br>
            <strong><?php echo htmlspecialchars($admin_name); ?></strong>
        </div>
    </div>

    <!-- Main Viewport -->
    <div class="admin-main-viewport">
        
        <!-- Header Bar -->
        <div class="admin-header-bar">
            <div class="admin-header-title">User Accounts Control Panel</div>
            <div class="admin-header-user">
                <span>Welcome, <strong><?php echo htmlspecialchars($admin_name); ?></strong> (Admin)</span>
                <a href="../auth/logout.php" class="admin-logout-btn">Secure Exit</a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="admin-main-content">
            
            <?php if (!empty($alert_success)): ?>
                <div class="alert-success" style="margin-bottom: 20px;"><?php echo htmlspecialchars($alert_success); ?></div>
            <?php endif; ?>
            <?php if (!empty($alert_danger)): ?>
                <div class="error-banner" style="margin-bottom: 20px;"><?php echo htmlspecialchars($alert_danger); ?></div>
            <?php endif; ?>

            <!-- Table Filter Toolbar -->
            <div class="table-toolbar">
                <div class="search-box">
                    <input type="text" id="user_search" class="admin-form-control" placeholder="🔍 Search users by name or username...">
                </div>
                <div class="filter-box">
                    <select id="user_role_filter" class="admin-form-control">
                        <option value="">All Roles 🏷️</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <div>
                    <button class="btn-admin btn-admin-primary" onclick="openCreateModal()">
                        ➕ Create New User
                    </button>
                </div>
            </div>

            <!-- Users Directory Table Card -->
            <div class="admin-panel-card">
                <div class="admin-panel-title">User Registry</div>
                
                <div style="overflow-x: auto;">
                    <table class="admin-dense-table" id="users_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Role</th>
                                <th>Name</th>
                                <th>Username / Email</th>
                                <th>Class Section</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $usr): ?>
                                <tr class="user-row" 
                                    data-name="<?php echo htmlspecialchars(strtolower($usr['full_name'])); ?>"
                                    data-username="<?php echo htmlspecialchars(strtolower($usr['username'])); ?>"
                                    data-role="<?php echo htmlspecialchars($usr['role']); ?>">
                                    <td>#<?php echo $usr['user_id']; ?></td>
                                    <td>
                                        <?php if ($usr['role'] === 'admin'): ?>
                                            <span class="btn-admin" style="background-color: #fee2e2; color: #991b1b; border: none; cursor: default; font-size: 0.75rem;">Admin</span>
                                        <?php elseif ($usr['role'] === 'teacher'): ?>
                                            <span class="btn-admin" style="background-color: #eff6ff; color: #1d4ed8; border: none; cursor: default; font-size: 0.75rem;">Teacher</span>
                                        <?php else: ?>
                                            <span class="btn-admin" style="background-color: #ecfdf5; color: #047857; border: none; cursor: default; font-size: 0.75rem;">Student</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <?php if ($usr['role'] === 'student'): 
                                                $avatar = $usr['avatar_url'] ?: 'monkey';
                                                $emoji = '🐵';
                                                if ($avatar === 'bunny') $emoji = '🐰';
                                                elseif ($avatar === 'panda') $emoji = '🐼';
                                                elseif ($avatar === 'fox') $emoji = '🦊';
                                            ?>
                                                <span style="font-size: 1.2rem;"><?php echo $emoji; ?></span>
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($usr['full_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($usr['username']); ?></code></td>
                                    <td><?php echo htmlspecialchars($usr['class_section'] ?: '-'); ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <button class="btn-admin" style="padding: 2px 8px; font-size: 0.75rem;" 
                                                    onclick="openEditModal(<?php echo $usr['user_id']; ?>, '<?php echo addslashes($usr['full_name']); ?>', '<?php echo addslashes($usr['username']); ?>', '<?php echo $usr['role']; ?>', '<?php echo addslashes($usr['class_section'] ?: ''); ?>', '<?php echo $usr['avatar_url'] ?: ''; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button class="btn-admin" style="padding: 2px 8px; font-size: 0.75rem;" 
                                                    onclick="openResetModal(<?php echo $usr['user_id']; ?>, '<?php echo addslashes($usr['full_name']); ?>')">
                                                🔐 Reset Pass
                                            </button>
                                            <form method="POST" action="manage_users.php" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo $usr['user_id']; ?>">
                                                <button type="submit" class="btn-admin btn-admin-danger" style="padding: 2px 8px; font-size: 0.75rem; border: none;">
                                                    ❌ Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <!-- =========================================================================
       MODALS SECTION
       ========================================================================= -->

    <!-- Create User Modal -->
    <div id="modal_create" class="admin-modal-overlay">
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <span>➕ Register New User Profile</span>
                <button type="button" class="admin-modal-close" onclick="closeAllModals()">×</button>
            </div>
            <form method="POST" action="manage_users.php">
                <input type="hidden" name="action" value="create">
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Full Name</label>
                        <input type="text" name="full_name" class="admin-form-control" placeholder="Timmy Taylor / Mr. Davis" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">User Login Name / Email</label>
                        <input type="text" name="username" class="admin-form-control" placeholder="timmy / teacher@school.com" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">Initial Password or 4-digit PIN</label>
                        <input type="text" name="password" class="admin-form-control" placeholder="e.g. 1234 or teacher123" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">System Access Role</label>
                        <select name="role" class="admin-form-control" onchange="toggleModalFields(this.value, 'create')" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <!-- Student specific fields -->
                    <div id="create_student_fields">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Class Section</label>
                            <input type="text" name="class_section" class="admin-form-control" placeholder="Standard 1A">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Avatar Animal</label>
                            <select name="avatar_url" class="admin-form-control">
                                <option value="monkey">🐵 Monkey</option>
                                <option value="bunny">🐰 Bunny</option>
                                <option value="panda">🐼 Panda</option>
                                <option value="fox">🦊 Fox</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin" onclick="closeAllModals()">Cancel</button>
                    <button type="submit" class="btn-admin btn-admin-primary">Register Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="modal_edit" class="admin-modal-overlay">
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <span>✏️ Edit User Profile Details</span>
                <button type="button" class="admin-modal-close" onclick="closeAllModals()">×</button>
            </div>
            <form method="POST" action="manage_users.php">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id" value="">
                
                <div class="admin-modal-body">
                    <div class="admin-form-group">
                        <label class="admin-form-label">Full Name</label>
                        <input type="text" id="edit_full_name" name="full_name" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">User Login Name / Email</label>
                        <input type="text" id="edit_username" name="username" class="admin-form-control" required>
                    </div>
                    <div class="admin-form-group">
                        <label class="admin-form-label">System Access Role</label>
                        <select id="edit_role" name="role" class="admin-form-control" onchange="toggleModalFields(this.value, 'edit')" required>
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    
                    <!-- Student specific fields -->
                    <div id="edit_student_fields">
                        <div class="admin-form-group">
                            <label class="admin-form-label">Class Section</label>
                            <input type="text" id="edit_class_section" name="class_section" class="admin-form-control">
                        </div>
                        <div class="admin-form-group">
                            <label class="admin-form-label">Avatar Animal</label>
                            <select id="edit_avatar_url" name="avatar_url" class="admin-form-control">
                                <option value="monkey">🐵 Monkey</option>
                                <option value="bunny">🐰 Bunny</option>
                                <option value="panda">🐼 Panda</option>
                                <option value="fox">🦊 Fox</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin" onclick="closeAllModals()">Cancel</button>
                    <button type="submit" class="btn-admin btn-admin-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="modal_reset" class="admin-modal-overlay">
        <div class="admin-modal-box">
            <div class="admin-modal-header">
                <span>🔑 Reset Password or PIN</span>
                <button type="button" class="admin-modal-close" onclick="closeAllModals()">×</button>
            </div>
            <form method="POST" action="manage_users.php">
                <input type="hidden" name="action" value="reset_pass">
                <input type="hidden" name="user_id" id="reset_user_id" value="">
                
                <div class="admin-modal-body">
                    <p style="font-size: 0.85rem; color: var(--color-slate-600); margin-bottom: 15px;">
                        Resetting credentials for: <strong id="reset_student_name">...</strong>
                    </p>
                    <div class="admin-form-group">
                        <label class="admin-form-label">New Password or 4-digit PIN</label>
                        <input type="text" name="password" class="admin-form-control" placeholder="Enter new password/PIN" required>
                    </div>
                </div>
                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin" onclick="closeAllModals()">Cancel</button>
                    <button type="submit" class="btn-admin btn-admin-primary">Override Credentials</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-side Interactive Modal Toggles & Search Filters -->
    <script>
        // Modal Controls
        function openCreateModal() {
            document.getElementById('modal_create').style.display = 'flex';
            toggleModalFields('student', 'create');
        }
        
        function openEditModal(id, name, username, role, classSection, avatar) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_class_section').value = classSection;
            document.getElementById('edit_avatar_url').value = avatar ? avatar : 'monkey';
            
            document.getElementById('modal_edit').style.display = 'flex';
            toggleModalFields(role, 'edit');
        }
        
        function openResetModal(id, name) {
            document.getElementById('reset_user_id').value = id;
            document.getElementById('reset_student_name').innerText = name;
            document.getElementById('modal_reset').style.display = 'flex';
        }
        
        function closeAllModals() {
            document.querySelectorAll('.admin-modal-overlay').forEach(el => {
                el.style.display = 'none';
            });
        }
        
        function toggleModalFields(role, formPrefix) {
            const fieldsDiv = document.getElementById(formPrefix + '_student_fields');
            if (role === 'student') {
                fieldsDiv.style.display = 'block';
            } else {
                fieldsDiv.style.display = 'none';
            }
        }
        
        // Search & Filter Directory Rows
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('user_search');
            const roleFilter = document.getElementById('user_role_filter');
            const rows = document.querySelectorAll('.user-row');
            
            function filterUsers() {
                const searchVal = searchInput.value.toLowerCase().trim();
                const roleVal = roleFilter.value;
                
                rows.forEach(row => {
                    const name = row.dataset.name;
                    const username = row.dataset.username;
                    const role = row.dataset.role;
                    
                    const matchesSearch = name.includes(searchVal) || username.includes(searchVal);
                    const matchesRole = (roleVal === "") || (role === roleVal);
                    
                    if (matchesSearch && matchesRole) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
            
            searchInput.addEventListener('input', filterUsers);
            roleFilter.addEventListener('change', filterUsers);
        });
    </script>
</body>
</html>

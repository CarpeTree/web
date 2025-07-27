<?php
// START NEW - Admin Dashboard
session_start();
require_once '../server/config/database-simple.php';

// Login page function
function include_login() {
    global $error;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Carpe Tree'em</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 40px;
            background: var(--background-color);
            border: 2px solid var(--deep-forest-green);
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--deep-forest-green);
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--deep-forest-green);
            border-radius: 4px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: var(--deep-forest-green);
            color: var(--background-color);
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
        }
        
        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>
<?php
}

// Basic authentication
if (!isset($_SESSION['admin_logged_in'])) {
    if (isset($_POST['username']) && isset($_POST['password'])) {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($_POST['password'], $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
        } else {
            $error = "Invalid credentials";
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        include_login();
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Get quotes with customer info
$stmt = $pdo->query("
    SELECT * FROM quote_summary 
    ORDER BY quote_created_at DESC
");
$quotes = $stmt->fetchAll();

// Get summary stats
$stats_query = $pdo->query("
    SELECT 
        COUNT(*) as total_quotes,
        SUM(CASE WHEN quote_status = 'submitted' THEN 1 ELSE 0 END) as new_quotes,
        SUM(CASE WHEN quote_status = 'draft_ready' THEN 1 ELSE 0 END) as ready_quotes,
        SUM(CASE WHEN quote_status = 'accepted' THEN 1 ELSE 0 END) as accepted_quotes,
        SUM(CASE WHEN scheduled_at IS NOT NULL AND scheduled_at > NOW() THEN 1 ELSE 0 END) as scheduled_jobs,
        SUM(total_estimate) as total_pipeline_value
    FROM quotes 
    WHERE quote_created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stats_query->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Carpe Tree'em</title>
    <link rel="stylesheet" href="../style.css">
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--background-color);
            border: 2px solid var(--deep-forest-green);
            border-radius: 8px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--background-color);
            border: 2px solid var(--deep-forest-green);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: var(--deep-forest-green);
        }
        
        .quotes-table {
            background: var(--background-color);
            border: 2px solid var(--deep-forest-green);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table-header {
            background: var(--deep-forest-green);
            color: var(--background-color);
            padding: 20px;
            font-weight: bold;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background: var(--deep-forest-green);
            color: var(--background-color);
        }
        
        .status-chip {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-submitted { background: #fff3cd; color: #856404; }
        .status-ai_processing { background: #d4edda; color: #155724; }
        .status-draft_ready { background: #d1ecf1; color: #0c5460; }
        .status-sent_to_client { background: #e2e3e5; color: #383d41; }
        .status-accepted { background: #d4edda; color: #155724; }
        .status-expired { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border: 1px solid var(--deep-forest-green);
            background: var(--background-color);
            color: var(--deep-forest-green);
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.8em;
        }
        
        .btn:hover {
            background: var(--deep-forest-green);
            color: var(--background-color);
        }
        
        .btn-primary {
            background: var(--deep-forest-green);
            color: var(--background-color);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: var(--background-color);
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--deep-forest-green);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--deep-forest-green);
            border-radius: 4px;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            table {
                font-size: 0.8em;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body x-data="adminDashboard()">
    <div class="admin-container">
        <!-- Header -->
        <div class="admin-header">
            <div>
                <h1>Admin Dashboard</h1>
                <p>Carpe Tree'em Quote & Job Management</p>
            </div>
            <div>
                <a href="?logout=1" class="btn">Logout</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['total_quotes'] ?></div>
                <div>Total Quotes (30 days)</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['new_quotes'] ?></div>
                <div>New Quotes</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['ready_quotes'] ?></div>
                <div>Ready to Send</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['accepted_quotes'] ?></div>
                <div>Accepted</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['scheduled_jobs'] ?></div>
                <div>Scheduled Jobs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?= number_format($stats['total_pipeline_value']) ?></div>
                <div>Pipeline Value</div>
            </div>
        </div>

        <!-- Quotes Table -->
        <div class="quotes-table">
            <div class="table-header">
                Recent Quotes & Jobs
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Estimate</th>
                        <th>Trees</th>
                        <th>Submitted</th>
                        <th>Scheduled</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><?= $quote['quote_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($quote['customer_name'] ?: 'No name') ?></strong><br>
                            <small><?= htmlspecialchars($quote['customer_email']) ?></small>
                        </td>
                        <td>
                            <span class="status-chip status-<?= $quote['quote_status'] ?>">
                                <?= ucwords(str_replace('_', ' ', $quote['quote_status'])) ?>
                            </span>
                        </td>
                        <td>$<?= number_format($quote['total_estimate'], 2) ?></td>
                        <td><?= $quote['tree_count'] ?></td>
                        <td><?= date('M j, Y', strtotime($quote['quote_created_at'])) ?></td>
                        <td>
                            <?= $quote['scheduled_at'] ? date('M j, Y g:i A', strtotime($quote['scheduled_at'])) : '-' ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn" @click="viewQuote(<?= $quote['quote_id'] ?>)">View</button>
                                <?php if ($quote['quote_status'] === 'accepted' && !$quote['scheduled_at']): ?>
                                <button class="btn btn-primary" @click="scheduleJob(<?= $quote['quote_id'] ?>)">Schedule</button>
                                <?php endif; ?>
                                <?php if ($quote['scheduled_at'] && strtotime($quote['scheduled_at']) < time()): ?>
                                <button class="btn btn-primary" @click="markJobDone(<?= $quote['quote_id'] ?>)">Mark Done</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Schedule Job Modal -->
    <div class="modal" id="scheduleModal" x-show="showScheduleModal" x-transition>
        <div class="modal-content">
            <h3>Schedule Job</h3>
            <form @submit.prevent="submitSchedule()">
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" x-model="scheduleForm.datetime" required>
                </div>
                <div class="form-group">
                    <label>Notes</label>
                    <textarea x-model="scheduleForm.notes" rows="3" placeholder="Special instructions, equipment needed, etc."></textarea>
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" @click="closeScheduleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Job</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark Done Modal -->
    <div class="modal" id="doneModal" x-show="showDoneModal" x-transition>
        <div class="modal-content">
            <h3>Mark Job Complete</h3>
            <form @submit.prevent="submitJobDone()">
                <div class="form-group">
                    <label>Actual Duration (minutes)</label>
                    <input type="number" x-model="doneForm.duration" placeholder="Total time spent">
                </div>
                <div class="form-group">
                    <label>Completion Notes</label>
                    <textarea x-model="doneForm.notes" rows="4" placeholder="Work completed, any issues, follow-up needed..."></textarea>
                </div>
                <div class="form-group">
                    <label>Final Photos (optional)</label>
                    <input type="file" multiple accept="image/*" @change="handleFinalPhotos($event)">
                </div>
                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" class="btn" @click="closeDoneModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete & Generate Invoice</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function adminDashboard() {
            return {
                showScheduleModal: false,
                showDoneModal: false,
                selectedQuoteId: null,
                scheduleForm: {
                    datetime: '',
                    notes: ''
                },
                doneForm: {
                    duration: '',
                    notes: '',
                    photos: []
                },

                viewQuote(quoteId) {
                    window.open(`quote-detail.php?id=${quoteId}`, '_blank');
                },

                scheduleJob(quoteId) {
                    this.selectedQuoteId = quoteId;
                    this.showScheduleModal = true;
                    // Set default to tomorrow 9 AM
                    const tomorrow = new Date();
                    tomorrow.setDate(tomorrow.getDate() + 1);
                    tomorrow.setHours(9, 0, 0, 0);
                    this.scheduleForm.datetime = tomorrow.toISOString().slice(0, 16);
                },

                closeScheduleModal() {
                    this.showScheduleModal = false;
                    this.scheduleForm = { datetime: '', notes: '' };
                },

                async submitSchedule() {
                    const formData = new FormData();
                    formData.append('quote_id', this.selectedQuoteId);
                    formData.append('scheduled_at', this.scheduleForm.datetime);
                    formData.append('notes', this.scheduleForm.notes);

                    try {
                        const response = await fetch('../server/api/scheduleJob.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (response.ok) {
                            this.closeScheduleModal();
                            location.reload();
                        } else {
                            alert('Failed to schedule job');
                        }
                    } catch (error) {
                        alert('Error scheduling job');
                    }
                },

                markJobDone(quoteId) {
                    this.selectedQuoteId = quoteId;
                    this.showDoneModal = true;
                },

                closeDoneModal() {
                    this.showDoneModal = false;
                    this.doneForm = { duration: '', notes: '', photos: [] };
                },

                handleFinalPhotos(event) {
                    this.doneForm.photos = Array.from(event.target.files);
                },

                async submitJobDone() {
                    const formData = new FormData();
                    formData.append('quote_id', this.selectedQuoteId);
                    formData.append('duration', this.doneForm.duration);
                    formData.append('notes', this.doneForm.notes);
                    
                    this.doneForm.photos.forEach((photo, index) => {
                        formData.append(`photos[${index}]`, photo);
                    });

                    try {
                        const response = await fetch('../server/api/markJobDone.php', {
                            method: 'POST',
                            body: formData
                        });

                        if (response.ok) {
                            this.closeDoneModal();
                            alert('Job marked complete! Invoice generated and sent to customer.');
                            location.reload();
                        } else {
                            alert('Failed to complete job');
                        }
                    } catch (error) {
                        alert('Error completing job');
                    }
                }
            }
        }
    </script>
</body>
</html>

// END NEW
?> 
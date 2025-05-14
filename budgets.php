<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$current_month = date('m');
$current_year = date('Y');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'update') {
            $category_id = intval($_POST['category_id']);
            $amount = floatval($_POST['amount']);
            $month = intval($_POST['month']);
            $year = intval($_POST['year']);

            // Check if budget already exists
            $check_query = "SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND month = ? AND year = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("iiii", $user_id, $category_id, $month, $year);
            $stmt->execute();
            $existing_budget = $stmt->get_result()->fetch_assoc();

            if ($existing_budget && $_POST['action'] === 'add') {
                $message = '<div class="alert alert-warning">Budget already exists for this category and period.</div>';
            } else {
                if ($existing_budget && $_POST['action'] === 'update') {
                    $query = "UPDATE budgets SET amount = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("di", $amount, $existing_budget['id']);
                } else {
                    $query = "INSERT INTO budgets (user_id, category_id, amount, month, year) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iidii", $user_id, $category_id, $amount, $month, $year);
                }

                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Budget ' . 
                              ($_POST['action'] === 'add' ? 'added' : 'updated') . ' successfully!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Error ' . 
                              ($_POST['action'] === 'add' ? 'adding' : 'updating') . ' budget.</div>';
                }
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['budget_id'])) {
            $budget_id = intval($_POST['budget_id']);
            $query = "DELETE FROM budgets WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $budget_id, $user_id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Budget deleted successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error deleting budget.</div>';
            }
        }
    }
}

// Get expense categories
$categories_query = "SELECT * FROM categories WHERE type = 'expense' ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get current month's budgets
$budgets_query = "SELECT b.*, c.name as category_name, 
                  COALESCE(SUM(t.amount), 0) as spent_amount
                  FROM budgets b
                  LEFT JOIN categories c ON b.category_id = c.id
                  LEFT JOIN transactions t ON t.category_id = b.category_id 
                      AND t.user_id = b.user_id 
                      AND t.type = 'expense'
                      AND MONTH(t.transaction_date) = b.month
                      AND YEAR(t.transaction_date) = b.year
                  WHERE b.user_id = ? AND b.month = ? AND b.year = ?
                  GROUP BY b.id, c.name
                  ORDER BY c.name";
$stmt = $conn->prepare($budgets_query);
$stmt->bind_param("iii", $user_id, $current_month, $current_year);
$stmt->execute();
$budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total budget and spent amount
$total_budget = 0;
$total_spent = 0;
foreach ($budgets as $budget) {
    $total_budget += $budget['amount'];
    $total_spent += $budget['spent_amount'];
}
$remaining_budget = $total_budget - $total_spent;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgets - AI Savings Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .budget-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .progress {
            height: 10px;
            border-radius: 5px;
        }
        .progress-bar {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
        }
        .btn-primary {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #00cec9 0%, #00b894 100%);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #00b894;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> AI Savings Assistant
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="budgets.php">
                            <i class="fas fa-wallet"></i> Budgets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo $message; ?>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <h5>Total Budget</h5>
                    <div class="stat-number">₹<?php echo number_format($total_budget, 2); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <h5>Total Spent</h5>
                    <div class="stat-number">₹<?php echo number_format($total_spent, 2); ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <h5>Remaining</h5>
                    <div class="stat-number">₹<?php echo number_format($remaining_budget, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add Budget</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Month</label>
                                <select class="form-select" name="month" required>
                                    <?php
                                    $months = [
                                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                                    ];
                                    foreach ($months as $num => $name) {
                                        $selected = $num == $current_month ? 'selected' : '';
                                        echo "<option value=\"$num\" $selected>$name</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Year</label>
                                <input type="number" class="form-control" name="year" 
                                       value="<?php echo $current_year; ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Add Budget
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Month's Budgets</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($budgets as $budget): ?>
                            <div class="budget-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($budget['category_name']); ?></h6>
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <small class="text-muted">Spent:</small>
                                            ₹<?php echo number_format($budget['spent_amount'], 2); ?> / 
                                            ₹<?php echo number_format($budget['amount'], 2); ?>
                                        </div>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="budget_id" value="<?php echo $budget['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this budget?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="progress">
                                    <?php
                                    $percentage = $budget['amount'] > 0 ? 
                                        min(100, ($budget['spent_amount'] / $budget['amount']) * 100) : 0;
                                    $progress_class = $percentage > 100 ? 'bg-danger' : 
                                        ($percentage > 80 ? 'bg-warning' : '');
                                    ?>
                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $percentage; ?>%"
                                         aria-valuenow="<?php echo $percentage; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($budgets)): ?>
                            <p class="text-muted text-center">No budgets set for this month.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
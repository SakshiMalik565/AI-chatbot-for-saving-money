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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $amount = floatval($_POST['amount']);
            $description = trim($_POST['description']);
            $category_id = intval($_POST['category_id']);
            $type = $_POST['type'];
            $transaction_date = $_POST['transaction_date'];

            $query = "INSERT INTO transactions (user_id, category_id, amount, description, transaction_date, type) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iidsss", $user_id, $category_id, $amount, $description, $transaction_date, $type);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Transaction added successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error adding transaction.</div>';
            }
        } elseif ($_POST['action'] === 'delete' && isset($_POST['transaction_id'])) {
            $transaction_id = intval($_POST['transaction_id']);
            $query = "DELETE FROM transactions WHERE id = ? AND user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $transaction_id, $user_id);
            
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Transaction deleted successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Error deleting transaction.</div>';
            }
        }
    }
}

// Get categories
$categories_query = "SELECT * FROM categories ORDER BY type, name";
$categories_result = $conn->query($categories_query);
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Get transactions
$transactions_query = "SELECT t.*, c.name as category_name, c.type as category_type 
                      FROM transactions t 
                      LEFT JOIN categories c ON t.category_id = c.id 
                      WHERE t.user_id = ? 
                      ORDER BY t.transaction_date DESC";
$stmt = $conn->prepare($transactions_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - AI Savings Assistant</title>
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
        .transaction-card {
            background: white;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .income {
            color: #00b894;
        }
        .expense {
            color: #e74c3c;
        }
        .btn-primary {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #00cec9 0%, #00b894 100%);
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
                        <a class="nav-link active" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="budgets.php">
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

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add Transaction</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="type" id="income" value="income" checked>
                                    <label class="btn btn-outline-success" for="income">Income</label>
                                    
                                    <input type="radio" class="btn-check" name="type" id="expense" value="expense">
                                    <label class="btn btn-outline-danger" for="expense">Expense</label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" data-type="<?php echo $category['type']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="transaction_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus"></i> Add Transaction
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Transaction History</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-card">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($transaction['description']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($transaction['category_name']); ?> • 
                                            <?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?>
                                        </small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <div class="<?php echo $transaction['type'] === 'income' ? 'income' : 'expense'; ?> me-3">
                                            <?php echo $transaction['type'] === 'income' ? '+' : '-'; ?>
                                            ₹<?php echo number_format($transaction['amount'], 2); ?>
                                        </div>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                    onclick="return confirm('Are you sure you want to delete this transaction?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($transactions)): ?>
                            <p class="text-muted text-center">No transactions found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter categories based on selected type
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const type = this.value;
                const categorySelect = document.querySelector('select[name="category_id"]');
                
                categorySelect.querySelectorAll('option').forEach(option => {
                    if (option.value === '') return;
                    option.style.display = option.dataset.type === type ? '' : 'none';
                });
                
                // Reset selection
                categorySelect.value = '';
            });
        });
    </script>
</body>
</html> 